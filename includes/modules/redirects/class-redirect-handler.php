<?php
/**
 * Redirect Handler.
 *
 * Frontend redirect execution that intercepts incoming requests and
 * performs URL redirects based on rules stored in the database.
 *
 * @package SeoAi\Modules\Redirects
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Class Redirect_Handler
 *
 * Hooks into template_redirect at priority 1 (very early) to intercept
 * requests and execute matching redirects before WordPress processes the page.
 *
 * @since 1.0.0
 */
final class Redirect_Handler {

	/**
	 * WordPress database abstraction object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Full table name including prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'seo_ai_redirects';
	}

	/**
	 * Register WordPress hooks for redirect handling.
	 *
	 * Hooks into template_redirect at priority 1 to intercept requests
	 * as early as possible in the template loading process.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'handle_redirect' ], 1 );
	}

	/**
	 * Main redirect handler triggered on template_redirect.
	 *
	 * Determines the current request URL, searches for a matching redirect
	 * rule, increments the hit counter, and executes the redirect.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_redirect(): void {
		// Do not redirect in admin, AJAX, REST, or cron contexts.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$request_url = $this->get_request_url();

		if ( empty( $request_url ) ) {
			return;
		}

		$redirect = $this->match_url( $request_url );

		if ( null === $redirect ) {
			return;
		}

		$this->execute_redirect( $redirect, $request_url );
	}

	/**
	 * Find a matching redirect for the given request URL.
	 *
	 * Checks exact matches first (faster), then falls back to regex patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $request_url The current request URL path.
	 * @return object|null The matching redirect row, or null if no match found.
	 */
	public function match_url( string $request_url ): ?object {
		// Try exact match first (most common and fastest).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exact = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table}
				WHERE source_url = %s
				AND is_regex = 0
				AND status = 'active'
				LIMIT 1",
				$request_url
			)
		);

		if ( $exact ) {
			return $exact;
		}

		// Also try with/without trailing slash for better matching.
		$alt_url = str_ends_with( $request_url, '/' )
			? rtrim( $request_url, '/' )
			: $request_url . '/';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$alt_match = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table}
				WHERE source_url = %s
				AND is_regex = 0
				AND status = 'active'
				LIMIT 1",
				$alt_url
			)
		);

		if ( $alt_match ) {
			return $alt_match;
		}

		// Try regex patterns.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$regex_rows = $this->db->get_results(
			"SELECT * FROM {$this->table}
			WHERE is_regex = 1
			AND status = 'active'
			ORDER BY id ASC"
		);

		if ( empty( $regex_rows ) ) {
			return null;
		}

		foreach ( $regex_rows as $row ) {
			$match_result = $this->match_regex( $row->source_url, $request_url );

			if ( false !== $match_result ) {
				// If regex produced a replacement target, store it on the row.
				if ( is_string( $match_result ) && $match_result !== $request_url ) {
					$row->target_url = $match_result;
				}

				return $row;
			}
		}

		return null;
	}

	/**
	 * Execute the redirect based on the matched rule.
	 *
	 * For 410 (Gone) and 451 (Unavailable for Legal Reasons), sends the
	 * status code without a redirect location. For 301/302/307, performs
	 * a wp_redirect() with the appropriate status code.
	 *
	 * @since 1.0.0
	 *
	 * @param object $redirect    The matched redirect row object.
	 * @param string $request_url The original request URL (used for regex replacements).
	 * @return void
	 */
	public function execute_redirect( object $redirect, string $request_url = '' ): void {
		$type = (int) $redirect->type;

		// Increment hit counter.
		$this->increment_hits( (int) $redirect->id );

		/**
		 * Fires before a redirect is executed.
		 *
		 * @since 1.0.0
		 *
		 * @param object $redirect    The redirect rule object.
		 * @param string $request_url The original request URL.
		 */
		do_action( 'seo_ai/redirect/before_execute', $redirect, $request_url );

		// Handle status-only responses (no redirect location).
		if ( in_array( $type, [ 410, 451 ], true ) ) {
			$this->send_status_response( $type );
			return;
		}

		$target_url = $redirect->target_url;

		// Bail if no target URL for redirect types that require one.
		if ( empty( $target_url ) ) {
			return;
		}

		// Perform the redirect.
		wp_redirect( esc_url_raw( $target_url ), $type );
		die();
	}

	/**
	 * Test a regex pattern against a URL and optionally apply replacement.
	 *
	 * If the redirect's target_url contains backreferences (e.g. $1, $2),
	 * they will be replaced with captured groups from the match.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pattern The regex pattern (source_url from the redirect rule).
	 * @param string $url     The URL to test against the pattern.
	 * @return bool|string False if no match; true if match with no replacement;
	 *                     or the replacement string with backreferences applied.
	 */
	public function match_regex( string $pattern, string $url ): bool|string {
		// Build the regex with a safe delimiter.
		$regex = '@' . str_replace( '@', '\\@', $pattern ) . '@i';

		// Suppress warnings from invalid patterns.
		$matched = @preg_match( $regex, $url, $matches );

		if ( 1 !== $matched ) {
			return false;
		}

		// If there are no captured groups, return true (match without replacement).
		if ( count( $matches ) <= 1 ) {
			return true;
		}

		return $matches;
	}

	/**
	 * Get the current request URL path, stripped of query string.
	 *
	 * @return string The request URL path, or empty string if unavailable.
	 */
	private function get_request_url(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Strip query string.
		$url = strtok( $request_uri, '?' );

		if ( false === $url ) {
			return '';
		}

		// Decode URL-encoded characters for consistent matching.
		$url = rawurldecode( $url );

		return $url;
	}

	/**
	 * Increment the hit counter for a redirect.
	 *
	 * @param int $id The redirect ID.
	 * @return void
	 */
	private function increment_hits( int $id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET hits = hits + 1, updated_at = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			)
		);
	}

	/**
	 * Send an HTTP status response without a redirect location.
	 *
	 * Used for 410 (Gone) and 451 (Unavailable for Legal Reasons) status codes.
	 *
	 * @param int $status_code The HTTP status code.
	 * @return void
	 */
	private function send_status_response( int $status_code ): void {
		status_header( $status_code );
		nocache_headers();

		if ( 410 === $status_code ) {
			$title   = __( '410 Gone', 'seo-ai' );
			$message = __( 'The requested resource is no longer available and has been permanently removed.', 'seo-ai' );
		} else {
			$title   = __( '451 Unavailable For Legal Reasons', 'seo-ai' );
			$message = __( 'The requested resource is unavailable for legal reasons.', 'seo-ai' );
		}

		// Use wp_die for consistent error page rendering.
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>',
			esc_html( $title ),
			[ 'response' => $status_code ]
		);
	}
}
