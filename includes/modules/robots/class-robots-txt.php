<?php
/**
 * robots.txt Management.
 *
 * Modifies the virtual robots.txt output with sensible defaults
 * and user-configurable rules.
 *
 * @package SeoAi\Modules\Robots
 * @since   1.0.0
 */

namespace SeoAi\Modules\Robots;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Robots_Txt
 *
 * @since 1.0.0
 */
final class Robots_Txt {

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options|null $options Options helper instance. Falls back to singleton.
	 */
	public function __construct( ?Options $options = null ) {
		$this->options = $options ?? Options::instance();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'robots_txt', [ $this, 'modify_robots_txt' ], 10, 2 );
	}

	/**
	 * Modify the robots.txt output.
	 *
	 * When the site is not set to public (Settings > Reading > "Discourage search engines"),
	 * returns WordPress's default "Disallow: /" output. Otherwise, appends sensible
	 * defaults and any custom rules configured in plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $output The existing robots.txt output.
	 * @param int    $public Whether the site is set to be public (1) or not (0).
	 * @return string Modified robots.txt content.
	 */
	public function modify_robots_txt( string $output, int $public ): string {
		// If the site is not public, enforce Disallow all.
		if ( 1 !== $public ) {
			$output  = "User-agent: *\n";
			$output .= "Disallow: /\n";

			return $output;
		}

		// Build a clean robots.txt from scratch with sensible defaults.
		$lines = [];

		$lines[] = 'User-agent: *';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Allow: /wp-admin/admin-ajax.php';
		$lines[] = 'Disallow: /wp-includes/';

		// Common WordPress paths that should be blocked.
		$lines[] = 'Disallow: /wp-login.php';
		$lines[] = 'Disallow: /xmlrpc.php';
		$lines[] = 'Disallow: /?s=';
		$lines[] = 'Disallow: /search/';

		// Prevent indexing of feed URLs.
		$lines[] = 'Disallow: /feed/';
		$lines[] = 'Disallow: /comments/feed/';

		// Prevent indexing of trackback URLs.
		$lines[] = 'Disallow: /trackback/';

		// Add custom rules from settings.
		$custom_rules = $this->options->get( 'robots_custom_rules', '' );

		if ( ! empty( $custom_rules ) && is_string( $custom_rules ) ) {
			$lines[] = '';
			$lines[] = '# Custom rules';

			$custom_lines = array_filter(
				array_map( 'trim', explode( "\n", $custom_rules ) )
			);

			foreach ( $custom_lines as $custom_line ) {
				// Validate that each line starts with a recognized directive.
				if ( $this->is_valid_robots_directive( $custom_line ) ) {
					$lines[] = $custom_line;
				}
			}
		}

		// Add sitemap reference.
		$lines[] = '';
		$this->add_sitemap_line( $lines );

		$lines[] = '';

		/**
		 * Filters the robots.txt lines before they are joined.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $lines  Array of robots.txt lines.
		 * @param int      $public Whether the site is public.
		 */
		$lines = (array) apply_filters( 'seo_ai/robots_txt_lines', $lines, $public );

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Add the Sitemap directive line.
	 *
	 * Checks if the sitemap module is enabled and adds the appropriate
	 * Sitemap URL. Falls back to the WordPress default sitemap.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $lines Reference to the robots.txt lines array.
	 * @return void
	 */
	private function add_sitemap_line( array &$lines ): void {
		$enabled_modules = $this->options->get( 'enabled_modules', [] );

		if ( is_array( $enabled_modules ) && in_array( 'sitemap', $enabled_modules, true ) ) {
			// Use our plugin's sitemap URL.
			$sitemap_url = home_url( '/sitemap.xml' );
		} else {
			// Fall back to WordPress core's built-in sitemap.
			$sitemap_url = home_url( '/wp-sitemap.xml' );
		}

		$lines[] = 'Sitemap: ' . esc_url_raw( $sitemap_url );
	}

	/**
	 * Validate whether a string is a recognized robots.txt directive.
	 *
	 * @since 1.0.0
	 *
	 * @param string $line The line to validate.
	 * @return bool True if the line starts with a valid directive or is a comment.
	 */
	private function is_valid_robots_directive( string $line ): bool {
		$valid_prefixes = [
			'User-agent:',
			'Disallow:',
			'Allow:',
			'Sitemap:',
			'Host:',
			'Crawl-delay:',
			'Clean-param:',
			'#', // Comments.
		];

		foreach ( $valid_prefixes as $prefix ) {
			if ( str_starts_with( $line, $prefix ) ) {
				return true;
			}
		}

		// Also allow case-insensitive matching.
		$lower = strtolower( $line );

		foreach ( $valid_prefixes as $prefix ) {
			if ( str_starts_with( $lower, strtolower( $prefix ) ) ) {
				return true;
			}
		}

		return false;
	}
}
