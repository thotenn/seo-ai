<?php
/**
 * Post List Filters.
 *
 * Adds dropdown filters to post list tables for filtering by
 * SEO score, robots directives, and schema type.
 *
 * @package SeoAi\Admin
 * @since   0.4.0
 */

declare(strict_types=1);

namespace SeoAi\Admin;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;

/**
 * Class Post_Filters
 *
 * @since 0.4.0
 */
final class Post_Filters {

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper instance.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'restrict_manage_posts', [ $this, 'render_filters' ] );
		add_action( 'pre_get_posts', [ $this, 'apply_filters' ] );
	}

	/**
	 * Render the filter dropdowns above the post list table.
	 *
	 * @param string $post_type The current post type.
	 * @return void
	 */
	public function render_filters( string $post_type ): void {
		$supported = $this->get_supported_post_types();

		if ( ! in_array( $post_type, $supported, true ) ) {
			return;
		}

		$this->render_score_filter();
		$this->render_robots_filter();
		$this->render_schema_filter();
	}

	/**
	 * Apply the selected filters to the main query.
	 *
	 * @param \WP_Query $query The query object.
	 * @return void
	 */
	public function apply_filters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];

		// SEO Score filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$score_filter = sanitize_text_field( wp_unslash( $_GET['seo_ai_score'] ?? '' ) );

		if ( '' !== $score_filter ) {
			$score_key = Post_Meta::prefixed_key( 'seo_score' );

			switch ( $score_filter ) {
				case 'good':
					$meta_query[] = [
						'key'     => $score_key,
						'value'   => 70,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					];
					break;
				case 'needs_work':
					$meta_query[] = [
						'relation' => 'AND',
						[
							'key'     => $score_key,
							'value'   => 40,
							'compare' => '>=',
							'type'    => 'NUMERIC',
						],
						[
							'key'     => $score_key,
							'value'   => 70,
							'compare' => '<',
							'type'    => 'NUMERIC',
						],
					];
					break;
				case 'poor':
					$meta_query[] = [
						'key'     => $score_key,
						'value'   => 40,
						'compare' => '<',
						'type'    => 'NUMERIC',
					];
					break;
				case 'none':
					$meta_query[] = [
						'key'     => $score_key,
						'compare' => 'NOT EXISTS',
					];
					break;
			}
		}

		// Robots filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$robots_filter = sanitize_text_field( wp_unslash( $_GET['seo_ai_robots'] ?? '' ) );

		if ( '' !== $robots_filter ) {
			$robots_key = Post_Meta::prefixed_key( 'robots' );

			if ( 'noindex' === $robots_filter ) {
				$meta_query[] = [
					'key'     => $robots_key,
					'value'   => 'noindex',
					'compare' => 'LIKE',
				];
			} elseif ( 'index' === $robots_filter ) {
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'     => $robots_key,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => $robots_key,
						'value'   => 'noindex',
						'compare' => 'NOT LIKE',
					],
				];
			}
		}

		// Schema filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$schema_filter = sanitize_text_field( wp_unslash( $_GET['seo_ai_schema'] ?? '' ) );

		if ( '' !== $schema_filter ) {
			$schema_key = Post_Meta::prefixed_key( 'schema_type' );

			if ( 'none' === $schema_filter ) {
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'     => $schema_key,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => $schema_key,
						'value'   => '',
						'compare' => '=',
					],
				];
			} else {
				$meta_query[] = [
					'key'   => $schema_key,
					'value' => $schema_filter,
				];
			}
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Render the SEO score filter dropdown.
	 *
	 * @return void
	 */
	private function render_score_filter(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = sanitize_text_field( wp_unslash( $_GET['seo_ai_score'] ?? '' ) );

		$options = [
			''           => __( 'All SEO Scores', 'seo-ai' ),
			'good'       => __( 'Good (70+)', 'seo-ai' ),
			'needs_work' => __( 'Needs Work (40-69)', 'seo-ai' ),
			'poor'       => __( 'Poor (<40)', 'seo-ai' ),
			'none'       => __( 'Not Analyzed', 'seo-ai' ),
		];

		echo '<select name="seo_ai_score">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render the robots status filter dropdown.
	 *
	 * @return void
	 */
	private function render_robots_filter(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = sanitize_text_field( wp_unslash( $_GET['seo_ai_robots'] ?? '' ) );

		$options = [
			''        => __( 'All Robots', 'seo-ai' ),
			'index'   => __( 'Index', 'seo-ai' ),
			'noindex' => __( 'Noindex', 'seo-ai' ),
		];

		echo '<select name="seo_ai_robots">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render the schema type filter dropdown.
	 *
	 * @return void
	 */
	private function render_schema_filter(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = sanitize_text_field( wp_unslash( $_GET['seo_ai_schema'] ?? '' ) );

		$options = [
			''            => __( 'All Schema', 'seo-ai' ),
			'Article'     => 'Article',
			'BlogPosting' => 'BlogPosting',
			'WebPage'     => 'WebPage',
			'FAQPage'     => 'FAQPage',
			'HowTo'       => 'HowTo',
			'Product'     => 'Product',
			'none'        => __( 'None', 'seo-ai' ),
		];

		echo '<select name="seo_ai_schema">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Get the supported post types from options.
	 *
	 * @return string[]
	 */
	private function get_supported_post_types(): array {
		$default    = [ 'post', 'page' ];
		$configured = $this->options->get( 'analysis_post_types', $default );

		return (array) apply_filters( 'seo_ai/post_types', $configured );
	}
}
