<?php
/**
 * Google News XML Sitemap.
 *
 * Generates a Google News compliant XML sitemap containing articles
 * published within the last 48 hours, as required by Google News.
 *
 * @package SeoAi\Modules\Sitemap
 * @since   0.5.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Sitemap;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class News_Sitemap
 *
 * Registers rewrite rules and hooks to serve a Google News sitemap
 * at news-sitemap.xml, and adds it to the main sitemap index.
 *
 * @since 0.5.0
 */
final class News_Sitemap {

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Options|null $options Options helper instance.
	 */
	public function __construct( ?Options $options = null ) {
		$this->options = $options ?? Options::instance();
	}

	/**
	 * Register WordPress hooks for the news sitemap.
	 *
	 * Bails early if the news sitemap feature is not enabled.
	 * Otherwise registers rewrite rules, request handling, query vars,
	 * and the sitemap index filter.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! $this->options->get( 'news_sitemap_enabled', false ) ) {
			return;
		}

		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
		add_filter( 'seo_ai/sitemap/index_entries', [ $this, 'add_to_index' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
	}

	/**
	 * Add rewrite rule for the news sitemap URL.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^news-sitemap\.xml$',
			'index.php?seo_ai_sitemap=news',
			'top'
		);
	}

	/**
	 * Register the seo_ai_sitemap query variable.
	 *
	 * @since 0.5.0
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'seo_ai_sitemap';

		return $vars;
	}

	/**
	 * Handle the news sitemap request.
	 *
	 * Checks the seo_ai_sitemap query variable and renders the news
	 * sitemap XML if the value is 'news'.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function handle_request(): void {
		$sitemap = get_query_var( 'seo_ai_sitemap' );

		if ( 'news' !== $sitemap ) {
			return;
		}

		$this->render();
	}

	/**
	 * Add the news sitemap entry to the main sitemap index.
	 *
	 * @since 0.5.0
	 *
	 * @param array $entries Existing sitemap index entries.
	 * @return array Modified sitemap index entries.
	 */
	public function add_to_index( array $entries ): array {
		$entries[] = [
			'loc'     => home_url( '/news-sitemap.xml' ),
			'lastmod' => gmdate( 'c' ),
		];

		return $entries;
	}

	/**
	 * Render the news sitemap.
	 *
	 * Queries recent posts from the last 48 hours, builds the Google News
	 * compliant XML, and outputs it with proper headers.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	private function render(): void {
		$pub_name = $this->options->get(
			'news_sitemap_publication_name',
			get_bloginfo( 'name' )
		);

		$language = get_bloginfo( 'language' );

		// Google News requires a two-letter language code (e.g., 'en' not 'en-US').
		if ( str_contains( $language, '-' ) ) {
			$language = substr( $language, 0, (int) strpos( $language, '-' ) );
		}

		$post_types = $this->options->get( 'news_sitemap_post_types', [ 'post' ] );

		if ( ! is_array( $post_types ) || empty( $post_types ) ) {
			$post_types = [ 'post' ];
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 48 * HOUR_IN_SECONDS ) );

		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'date_query'     => [
				[
					'after'     => $cutoff,
					'inclusive' => true,
					'column'    => 'post_date_gmt',
				],
			],
			'meta_query'     => [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[
						'key'     => '_seo_ai_news_exclude',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_seo_ai_news_exclude',
						'value'   => '1',
						'compare' => '!=',
					],
				],
				[
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
			],
		];

		$query   = new \WP_Query( $args );
		$entries = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$entries[] = [
					'loc'              => get_permalink( $post ),
					'publication_date' => get_the_date( 'c', $post ),
					'title'            => get_the_title( $post ),
				];
			}
		}

		wp_reset_postdata();

		$xml = $this->build_xml( $entries, (string) $pub_name, $language );

		$this->output_xml( $xml );
	}

	/**
	 * Build the Google News sitemap XML.
	 *
	 * Constructs a valid XML document with the sitemaps.org and Google News
	 * namespaces. Each entry is wrapped in a <url> element containing
	 * the permalink and news-specific metadata.
	 *
	 * @since 0.5.0
	 *
	 * @param array  $entries  Array of news entries with 'loc', 'publication_date', and 'title'.
	 * @param string $pub_name The publication name.
	 * @param string $language The two-letter language code.
	 * @return string The complete news sitemap XML.
	 */
	private function build_xml( array $entries, string $pub_name, string $language ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";
			$xml .= "\t\t<news:news>\n";
			$xml .= "\t\t\t<news:publication>\n";
			$xml .= "\t\t\t\t<news:name>" . esc_xml( $pub_name ) . "</news:name>\n";
			$xml .= "\t\t\t\t<news:language>" . esc_xml( $language ) . "</news:language>\n";
			$xml .= "\t\t\t</news:publication>\n";
			$xml .= "\t\t\t<news:publication_date>" . esc_xml( $entry['publication_date'] ) . "</news:publication_date>\n";
			$xml .= "\t\t\t<news:title>" . esc_xml( $entry['title'] ) . "</news:title>\n";
			$xml .= "\t\t</news:news>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Output XML content with proper headers and terminate execution.
	 *
	 * Sets the Content-Type to text/xml and adds an X-Robots-Tag header
	 * to prevent search engines from indexing the sitemap itself.
	 *
	 * @since 0.5.0
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
}
