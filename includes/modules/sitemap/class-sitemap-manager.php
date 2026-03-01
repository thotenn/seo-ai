<?php
/**
 * XML Sitemap Manager.
 *
 * Generates XML sitemaps with support for multiple post types, taxonomies,
 * image extensions, and transient caching with automatic invalidation.
 *
 * @package SeoAi\Modules\Sitemap
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Sitemap;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Sitemap_Manager
 *
 * Hooks into WordPress to serve XML sitemaps, add rewrite rules,
 * and append the sitemap URL to robots.txt.
 *
 * @since 1.0.0
 */
final class Sitemap_Manager {

	/**
	 * Transient cache prefix.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'seo_ai_sitemap_';

	/**
	 * Cache duration in seconds (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

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
		$this->options = $options ?? Options::instance();
	}

	/**
	 * Register WordPress hooks for sitemap functionality.
	 *
	 * Hooks into init (rewrite rules), template_redirect (serve sitemap),
	 * do_robotstxt (append sitemap URL), and save_post (cache invalidation).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! $this->options->get( 'sitemap_enabled', true ) ) {
			return;
		}

		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
		add_filter( 'do_robotstxt', '__return_true' );
		add_filter( 'robots_txt', [ $this, 'add_to_robots_txt' ], 10, 2 );
		add_action( 'save_post', [ $this, 'invalidate_cache' ] );
		add_action( 'delete_post', [ $this, 'invalidate_cache' ] );
		add_action( 'created_term', [ $this, 'invalidate_cache' ] );
		add_action( 'edited_term', [ $this, 'invalidate_cache' ] );
		add_action( 'delete_term', [ $this, 'invalidate_cache' ] );

		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
	}

	/**
	 * Register custom query variables for sitemap routing.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'seo_ai_sitemap';
		$vars[] = 'seo_ai_sitemap_type';
		$vars[] = 'seo_ai_sitemap_page';

		return $vars;
	}

	/**
	 * Add rewrite rules for sitemap URLs.
	 *
	 * Creates rules for:
	 * - sitemap.xml (index)
	 * - sitemap-{type}-{page}.xml (individual sitemaps)
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^sitemap\.xml$',
			'index.php?seo_ai_sitemap=index',
			'top'
		);

		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)-(\d+)\.xml$',
			'index.php?seo_ai_sitemap=1&seo_ai_sitemap_type=$matches[1]&seo_ai_sitemap_page=$matches[2]',
			'top'
		);
	}

	/**
	 * Intercept sitemap requests and output the appropriate XML.
	 *
	 * Checks the seo_ai_sitemap query variable and renders either
	 * the sitemap index or an individual sub-sitemap.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_request(): void {
		$sitemap = get_query_var( 'seo_ai_sitemap' );

		if ( empty( $sitemap ) ) {
			return;
		}

		// Disable WordPress default sitemaps if we are handling it.
		remove_action( 'init', 'wp_sitemaps_get_server' );

		if ( 'index' === $sitemap ) {
			$this->render_index();
			return;
		}

		$type = get_query_var( 'seo_ai_sitemap_type', '' );
		$page = (int) get_query_var( 'seo_ai_sitemap_page', 1 );

		if ( empty( $type ) || $page < 1 ) {
			$this->send_404();
			return;
		}

		$this->render_sitemap( $type, $page );
	}

	/**
	 * Render the sitemap index listing all sub-sitemaps with lastmod dates.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_index(): void {
		$cache_key = self::CACHE_PREFIX . 'index';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$this->output_xml( $cached );
			return;
		}

		$entries = [];

		// Post type sitemaps.
		$post_types = $this->get_enabled_post_types();
		foreach ( $post_types as $post_type ) {
			$count = $this->get_post_type_count( $post_type );
			if ( $count < 1 ) {
				continue;
			}

			$per_page = $this->get_max_entries();
			$pages    = (int) ceil( $count / $per_page );

			for ( $page = 1; $page <= $pages; $page++ ) {
				$lastmod    = $this->get_post_type_lastmod( $post_type );
				$entries[] = [
					'loc'     => $this->get_sub_sitemap_url( $post_type, $page ),
					'lastmod' => $lastmod,
				];
			}
		}

		// Taxonomy sitemaps.
		$taxonomies = $this->get_enabled_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$count = wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
			if ( is_wp_error( $count ) || (int) $count < 1 ) {
				continue;
			}

			$entries[] = [
				'loc'     => $this->get_sub_sitemap_url( $taxonomy, 1 ),
				'lastmod' => $this->get_taxonomy_lastmod( $taxonomy ),
			];
		}

		/**
		 * Filters the sitemap index entries before rendering.
		 *
		 * @since 1.0.0
		 *
		 * @param array $entries Sitemap index entries.
		 */
		$entries = apply_filters( 'seo_ai/sitemap/index_entries', $entries );

		$xml = $this->build_index_xml( $entries );

		set_transient( $cache_key, $xml, self::CACHE_TTL );

		$this->output_xml( $xml );
	}

	/**
	 * Render an individual sitemap for a specific type and page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The sitemap type (post type slug or taxonomy slug).
	 * @param int    $page The page number.
	 * @return void
	 */
	public function render_sitemap( string $type, int $page ): void {
		$cache_key = self::CACHE_PREFIX . $type . '_' . $page;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$this->output_xml( $cached );
			return;
		}

		$entries = [];

		// Check if this is a post type.
		$post_types = $this->get_enabled_post_types();
		if ( in_array( $type, $post_types, true ) ) {
			$entries = $this->get_post_type_entries( $type, $page, $this->get_max_entries() );
		}

		// Check if this is a taxonomy.
		$taxonomies = $this->get_enabled_taxonomies();
		if ( empty( $entries ) && in_array( $type, $taxonomies, true ) ) {
			$entries = $this->get_taxonomy_entries( $type );
		}

		if ( empty( $entries ) ) {
			$this->send_404();
			return;
		}

		/**
		 * Filters the sitemap entries for a specific type before rendering.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $entries Sitemap URL entries.
		 * @param string $type    The sitemap type.
		 * @param int    $page    The page number.
		 */
		$entries = apply_filters( 'seo_ai/sitemap/entries', $entries, $type, $page );

		$xml = $this->build_urlset_xml( $entries );

		set_transient( $cache_key, $xml, self::CACHE_TTL );

		$this->output_xml( $xml );
	}

	/**
	 * Query posts for a specific post type sitemap page.
	 *
	 * Respects per-post noindex settings via `_seo_ai_robots` meta.
	 * Includes featured images when the sitemap_include_images setting is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug.
	 * @param int    $page      The page number (1-based).
	 * @param int    $per_page  Maximum entries per page.
	 * @return array Array of sitemap entry arrays.
	 */
	public function get_post_type_entries( string $post_type, int $page, int $per_page = 1000 ): array {
		$include_images = (bool) $this->options->get( 'sitemap_include_images', true );
		$offset         = ( $page - 1 ) * $per_page;

		$args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => '_seo_ai_robots',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_seo_ai_robots',
					'value'   => 'noindex',
					'compare' => 'NOT LIKE',
				],
			],
		];

		$query   = new \WP_Query( $args );
		$entries = [];

		if ( ! $query->have_posts() ) {
			return $entries;
		}

		foreach ( $query->posts as $post ) {
			$entry = [
				'loc'        => get_permalink( $post ),
				'lastmod'    => $this->format_date( $post->post_modified_gmt ),
				'changefreq' => $this->get_changefreq( $post ),
				'priority'   => $this->get_priority( $post_type, $post ),
			];

			// Include featured image if enabled and available.
			if ( $include_images && has_post_thumbnail( $post->ID ) ) {
				$image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
				if ( $image_url ) {
					$entry['images'] = [
						[ 'loc' => $image_url ],
					];
				}
			}

			$entries[] = $entry;
		}

		wp_reset_postdata();

		return $entries;
	}

	/**
	 * Query terms for a taxonomy sitemap.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return array Array of sitemap entry arrays.
	 */
	public function get_taxonomy_entries( string $taxonomy ): array {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => $this->get_max_entries(),
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$entries = [];

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$entries[] = [
				'loc'        => $link,
				'lastmod'    => $this->get_term_lastmod( $term ),
				'changefreq' => 'weekly',
				'priority'   => '0.3',
			];
		}

		return $entries;
	}

	/**
	 * Delete all transient caches for sitemaps.
	 *
	 * Called on save_post, delete_post, and term changes to ensure
	 * fresh sitemap data is generated on the next request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		global $wpdb;

		// Delete the index cache.
		delete_transient( self::CACHE_PREFIX . 'index' );

		// Delete all sub-sitemap caches.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::CACHE_PREFIX . '%',
				'_transient_timeout_' . self::CACHE_PREFIX . '%'
			)
		);
	}

	/**
	 * Get the full URL to the sitemap index.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sitemap index URL.
	 */
	public function get_sitemap_url(): string {
		return home_url( '/sitemap.xml' );
	}

	/**
	 * Append the Sitemap URL to the robots.txt output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $output The existing robots.txt content.
	 * @param bool   $public Whether the site is public (blog_public option).
	 * @return string Modified robots.txt content with Sitemap directive.
	 */
	public function add_to_robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		if ( ! $this->options->get( 'sitemap_enabled', true ) ) {
			return $output;
		}

		$sitemap_url = $this->get_sitemap_url();

		// Avoid duplicate entries.
		if ( str_contains( $output, $sitemap_url ) ) {
			return $output;
		}

		$output .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";

		return $output;
	}

	/**
	 * Build the sitemap index XML string.
	 *
	 * @param array $entries Array of index entries with 'loc' and 'lastmod' keys.
	 * @return string The complete sitemap index XML.
	 */
	private function build_index_xml( array $entries ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_xml( $entry['lastmod'] ) . "</lastmod>\n";
			}

			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Build a urlset XML string from sitemap entries.
	 *
	 * @param array $entries Array of URL entry arrays.
	 * @return string The complete urlset XML.
	 */
	private function build_urlset_xml( array $entries ): string {
		$include_images = (bool) $this->options->get( 'sitemap_include_images', true );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= $this->get_xsl_processing_instruction();
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

		if ( $include_images ) {
			$xml .= "\n        xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\"";
		}

		$xml .= ">\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_xml( $entry['lastmod'] ) . "</lastmod>\n";
			}

			if ( ! empty( $entry['changefreq'] ) ) {
				$xml .= "\t\t<changefreq>" . esc_xml( $entry['changefreq'] ) . "</changefreq>\n";
			}

			if ( ! empty( $entry['priority'] ) ) {
				$xml .= "\t\t<priority>" . esc_xml( (string) $entry['priority'] ) . "</priority>\n";
			}

			// Image sub-elements.
			if ( $include_images && ! empty( $entry['images'] ) ) {
				foreach ( $entry['images'] as $image ) {
					$xml .= "\t\t<image:image>\n";
					$xml .= "\t\t\t<image:loc>" . esc_url( $image['loc'] ) . "</image:loc>\n";

					if ( ! empty( $image['title'] ) ) {
						$xml .= "\t\t\t<image:title>" . esc_xml( $image['title'] ) . "</image:title>\n";
					}

					if ( ! empty( $image['caption'] ) ) {
						$xml .= "\t\t\t<image:caption>" . esc_xml( $image['caption'] ) . "</image:caption>\n";
					}

					$xml .= "\t\t</image:image>\n";
				}
			}

			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Output XML content with proper headers and terminate execution.
	 *
	 * @param string $xml The XML content to output.
	 * @return void
	 */
	private function output_xml( string $xml ): void {
		status_header( 200 );
		header( 'Content-Type: text/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is pre-escaped.
		echo $xml;
		die();
	}

	/**
	 * Send a 404 response for invalid sitemap requests.
	 *
	 * @return void
	 */
	private function send_404(): void {
		global $wp_query;

		if ( $wp_query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Get the XSL stylesheet processing instruction.
	 *
	 * @return string XSL processing instruction or empty string.
	 */
	private function get_xsl_processing_instruction(): string {
		/**
		 * Filters the XSL stylesheet URL for sitemap display.
		 *
		 * @since 1.0.0
		 *
		 * @param string $xsl_url URL to the XSL stylesheet, or empty to disable.
		 */
		$xsl_url = apply_filters( 'seo_ai/sitemap/xsl_url', '' );

		if ( empty( $xsl_url ) ) {
			return '';
		}

		return '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
	}

	/**
	 * Get the URL for a sub-sitemap.
	 *
	 * @param string $type The sitemap type (post type or taxonomy slug).
	 * @param int    $page The page number.
	 * @return string The sub-sitemap URL.
	 */
	private function get_sub_sitemap_url( string $type, int $page ): string {
		return home_url( '/sitemap-' . sanitize_key( $type ) . '-' . $page . '.xml' );
	}

	/**
	 * Get the enabled post types for the sitemap.
	 *
	 * @return array Array of post type slugs.
	 */
	private function get_enabled_post_types(): array {
		$post_types = $this->options->get( 'sitemap_post_types', [ 'post', 'page' ] );

		if ( ! is_array( $post_types ) ) {
			return [ 'post', 'page' ];
		}

		// Verify all post types are public and registered.
		return array_filter( $post_types, static function ( string $pt ): bool {
			$obj = get_post_type_object( $pt );
			return $obj && $obj->public;
		} );
	}

	/**
	 * Get the enabled taxonomies for the sitemap.
	 *
	 * @return array Array of taxonomy slugs.
	 */
	private function get_enabled_taxonomies(): array {
		$taxonomies = $this->options->get( 'sitemap_taxonomies', [ 'category' ] );

		if ( ! is_array( $taxonomies ) ) {
			return [ 'category' ];
		}

		// Verify all taxonomies are public and registered.
		return array_filter( $taxonomies, static function ( string $tax ): bool {
			$obj = get_taxonomy( $tax );
			return $obj && $obj->public;
		} );
	}

	/**
	 * Get the maximum number of entries per sitemap page.
	 *
	 * @return int Maximum entries per page (clamped between 100 and 50000).
	 */
	private function get_max_entries(): int {
		$max = (int) $this->options->get( 'sitemap_max_entries', 1000 );

		return max( 100, min( 50000, $max ) );
	}

	/**
	 * Count the number of indexable posts for a post type.
	 *
	 * @param string $post_type The post type slug.
	 * @return int The number of indexable posts.
	 */
	private function get_post_type_count( string $post_type ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_seo_ai_robots'
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND (pm.meta_value IS NULL OR pm.meta_value NOT LIKE %s)",
				$post_type,
				'%noindex%'
			)
		);

		return (int) $count;
	}

	/**
	 * Get the last modified date for a post type.
	 *
	 * @param string $post_type The post type slug.
	 * @return string ISO 8601 date string.
	 */
	private function get_post_type_lastmod( string $post_type ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified_gmt)
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'",
				$post_type
			)
		);

		return $this->format_date( $date ?: '' );
	}

	/**
	 * Get the last modified date for a taxonomy.
	 *
	 * Uses the most recently modified post in the taxonomy as lastmod.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return string ISO 8601 date string.
	 */
	private function get_taxonomy_lastmod( string $taxonomy ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy = %s
				AND p.post_status = 'publish'",
				$taxonomy
			)
		);

		return $this->format_date( $date ?: '' );
	}

	/**
	 * Get the last modified date for a specific term.
	 *
	 * @param \WP_Term $term The term object.
	 * @return string ISO 8601 date string.
	 */
	private function get_term_lastmod( \WP_Term $term ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				WHERE tr.term_taxonomy_id = %d
				AND p.post_status = 'publish'",
				$term->term_taxonomy_id
			)
		);

		return $this->format_date( $date ?: '' );
	}

	/**
	 * Format a date string to ISO 8601 (W3C) format.
	 *
	 * @param string $date A date string in MySQL format.
	 * @return string ISO 8601 formatted date, or empty string on failure.
	 */
	private function format_date( string $date ): string {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date . ' +0000' );

		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'c', $timestamp );
	}

	/**
	 * Determine the change frequency for a post.
	 *
	 * Posts modified within the last week are "daily", within a month "weekly",
	 * otherwise "monthly".
	 *
	 * @param \WP_Post $post The post object.
	 * @return string The changefreq value.
	 */
	private function get_changefreq( \WP_Post $post ): string {
		$modified = strtotime( $post->post_modified_gmt );

		if ( false === $modified ) {
			return 'monthly';
		}

		$diff = time() - $modified;

		if ( $diff < WEEK_IN_SECONDS ) {
			return 'daily';
		}

		if ( $diff < MONTH_IN_SECONDS ) {
			return 'weekly';
		}

		return 'monthly';
	}

	/**
	 * Determine the priority for a post based on its type.
	 *
	 * - Homepage / front page: 1.0
	 * - Pages: 0.8
	 * - Posts: 0.6
	 * - Custom post types: 0.5
	 *
	 * @param string   $post_type The post type slug.
	 * @param \WP_Post $post      The post object.
	 * @return string The priority value as a decimal string.
	 */
	private function get_priority( string $post_type, \WP_Post $post ): string {
		// Front page gets highest priority.
		$front_page_id = (int) get_option( 'page_on_front' );
		if ( $front_page_id && $post->ID === $front_page_id ) {
			return '1.0';
		}

		// Blog page.
		$blog_page_id = (int) get_option( 'page_for_posts' );
		if ( $blog_page_id && $post->ID === $blog_page_id ) {
			return '0.9';
		}

		return match ( $post_type ) {
			'page'  => '0.8',
			'post'  => '0.6',
			default => '0.5',
		};
	}
}
