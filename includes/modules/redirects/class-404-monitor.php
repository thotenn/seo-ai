<?php
/**
 * 404 Monitor.
 *
 * Logs 404 errors for analysis, deduplicates entries by URL,
 * and provides log management including cleanup of old records.
 *
 * @package SeoAi\Modules\Redirects
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Monitor_404
 *
 * Hooks into template_redirect at low priority to detect and log 404 errors
 * with referrer, user agent, and anonymized IP data.
 *
 * @since 1.0.0
 */
final class Monitor_404 {

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
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options|null $options Options helper instance.
	 */
	public function __construct( ?Options $options = null ) {
		global $wpdb;

		$this->db      = $wpdb;
		$this->table   = $wpdb->prefix . 'seo_ai_404_log';
		$this->options = $options ?? Options::instance();
	}

	/**
	 * Register WordPress hooks for 404 monitoring.
	 *
	 * Hooks into template_redirect at priority 99 (after redirects have
	 * been processed) to log any remaining 404 errors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'log_404' ], 99 );
	}

	/**
	 * Log a 404 error if the current request is a 404 page.
	 *
	 * Checks whether monitoring is enabled, deduplicates by URL (incrementing
	 * hits on existing entries), and enforces the configured log limit.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function log_404(): void {
		if ( ! is_404() ) {
			return;
		}

		// Check if 404 monitoring is enabled.
		if ( ! $this->options->get( 'redirect_404_monitoring', true ) ) {
			return;
		}

		// Do not log admin, AJAX, REST, or cron requests.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$url        = $this->get_current_url();
		$referrer   = $this->get_referrer();
		$user_agent = $this->get_user_agent();
		$ip_address = $this->get_anonymized_ip();

		if ( empty( $url ) ) {
			return;
		}

		// Ignore requests for common non-page resources.
		if ( $this->should_ignore( $url ) ) {
			return;
		}

		$now = current_time( 'mysql', true );

		// Check if this URL already exists in the log.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $this->db->get_row(
			$this->db->prepare(
				"SELECT id, hits FROM {$this->table} WHERE url = %s LIMIT 1",
				$url
			)
		);

		if ( $existing ) {
			// Increment hits and update last_hit timestamp.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db->update(
				$this->table,
				[
					'hits'       => (int) $existing->hits + 1,
					'last_hit'   => $now,
					'referrer'   => $referrer,
					'user_agent' => $user_agent,
					'ip_address' => $ip_address,
				],
				[ 'id' => (int) $existing->id ],
				[ '%d', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
		} else {
			// Insert new log entry.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db->insert(
				$this->table,
				[
					'url'        => $url,
					'referrer'   => $referrer,
					'user_agent' => $user_agent,
					'ip_address' => $ip_address,
					'hits'       => 1,
					'last_hit'   => $now,
					'created_at' => $now,
				],
				[ '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
			);
		}

		// Cleanup old entries if over the limit.
		$this->cleanup_old_logs();
	}

	/**
	 * Get log entries with pagination, filtering, and sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $search   Search term for URL or referrer.
	 *     @type string $orderby  Column to order by. Default 'last_hit'.
	 *     @type string $order    Sort direction. 'ASC' or 'DESC'. Default 'DESC'.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Current page number. Default 1.
	 * }
	 * @return array {
	 *     @type array $items Array of log row objects.
	 *     @type int   $total Total matching log entries.
	 *     @type int   $pages Total number of pages.
	 * }
	 */
	public function get_logs( array $args = [] ): array {
		$defaults = [
			'search'   => '',
			'orderby'  => 'last_hit',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		];

		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$search_like = '%' . $this->db->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]     = '(url LIKE %s OR referrer LIKE %s)';
			$values[]    = $search_like;
			$values[]    = $search_like;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		// Sorting.
		$allowed_orderby = [ 'url', 'referrer', 'hits', 'last_hit', 'created_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_hit';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $this->db->prepare( $count_sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $this->db->get_var( $count_sql );

		// Pagination.
		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$pages    = (int) ceil( $total / $per_page );

		// Query items.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$query_values   = array_merge( $values, [ $per_page, $offset ] );
		$prepared_query = $this->db->prepare( $query, ...$query_values );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $this->db->get_results( $prepared_query );

		return [
			'items' => $items ?: [],
			'total' => $total,
			'pages' => $pages,
		];
	}

	/**
	 * Delete a single log entry by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The log entry ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_log( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Delete all log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of deleted rows.
	 */
	public function clear_logs(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->query( "DELETE FROM {$this->table}" );

		return false !== $result ? $result : 0;
	}

	/**
	 * Remove log entries that exceed the configured limit.
	 *
	 * Deletes the oldest entries (by last_hit) when the total count
	 * exceeds the redirect_404_log_limit setting.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function cleanup_old_logs(): void {
		$limit = (int) $this->options->get( 'redirect_404_log_limit', 1000 );
		$limit = max( 100, $limit );

		$total = $this->get_count();

		if ( $total <= $limit ) {
			return;
		}

		$excess = $total - $limit;

		// Delete the oldest entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db->query(
			$this->db->prepare(
				"DELETE FROM {$this->table} ORDER BY last_hit ASC LIMIT %d",
				$excess
			)
		);
	}

	/**
	 * Get the total number of log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total log entry count.
	 */
	public function get_count(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Get the current request URL, stripped of query string.
	 *
	 * @return string The request URL path.
	 */
	private function get_current_url(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$url         = strtok( $request_uri, '?' );

		return false !== $url ? $url : '';
	}

	/**
	 * Get the HTTP referrer, sanitized.
	 *
	 * @return string The referrer URL, or empty string if unavailable.
	 */
	private function get_referrer(): string {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		$referrer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

		return mb_substr( $referrer, 0, 2048 );
	}

	/**
	 * Get the user agent string, sanitized and truncated.
	 *
	 * @return string The user agent string, or empty string if unavailable.
	 */
	private function get_user_agent(): string {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		return mb_substr( $user_agent, 0, 512 );
	}

	/**
	 * Get the client IP address with the last octet anonymized.
	 *
	 * For IPv4, the last octet is replaced with 0 (e.g. 192.168.1.0).
	 * For IPv6, the last group is replaced with 0.
	 *
	 * @return string The anonymized IP address.
	 */
	private function get_anonymized_ip(): string {
		$ip = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( empty( $ip ) ) {
			return '';
		}

		// Validate IP address.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		// Anonymize IPv4: zero the last octet.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		// Anonymize IPv6: zero the last group.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$expanded = inet_ntop( inet_pton( $ip ) );
			if ( false === $expanded ) {
				return '';
			}
			$parts = explode( ':', $expanded );
			if ( count( $parts ) >= 8 ) {
				$parts[7] = '0';
			}
			return implode( ':', $parts );
		}

		return '';
	}

	/**
	 * Determine whether a URL should be ignored for 404 logging.
	 *
	 * Filters out common non-page resource requests such as favicons,
	 * source maps, and known bot paths.
	 *
	 * @param string $url The request URL to check.
	 * @return bool True if the URL should be ignored.
	 */
	private function should_ignore( string $url ): bool {
		$ignore_patterns = [
			'/favicon.ico',
			'/apple-touch-icon',
			'/browserconfig.xml',
			'/site.webmanifest',
			'/.well-known/',
			'/wp-cron.php',
		];

		$ignore_extensions = [
			'.map',
			'.env',
			'.sql',
			'.bak',
		];

		foreach ( $ignore_patterns as $pattern ) {
			if ( str_starts_with( $url, $pattern ) || str_contains( $url, $pattern ) ) {
				return true;
			}
		}

		foreach ( $ignore_extensions as $ext ) {
			if ( str_ends_with( strtolower( $url ), $ext ) ) {
				return true;
			}
		}

		return false;
	}
}
