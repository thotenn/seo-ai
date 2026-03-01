<?php
/**
 * Admin List Table Columns.
 *
 * Adds an SEO score column to post/page list tables in the admin,
 * with color-coded score indicators and sortable support.
 *
 * @package SeoAi\Admin
 * @since   1.0.0
 */

namespace SeoAi\Admin;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;

/**
 * Class Columns
 *
 * @since 1.0.0
 */
final class Columns {

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Post meta helper.
	 *
	 * @var Post_Meta
	 */
	private Post_Meta $post_meta;

	/**
	 * Constructor.
	 *
	 * @param Options   $options   Options helper instance.
	 * @param Post_Meta $post_meta Post meta helper instance.
	 */
	public function __construct( Options $options, Post_Meta $post_meta ) {
		$this->options   = $options;
		$this->post_meta = $post_meta;
	}

	/**
	 * Register hooks for each configured post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$post_types = $this->get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_columns' ] );
			add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_column' ], 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", [ $this, 'sortable_columns' ] );
		}

		add_action( 'pre_get_posts', [ $this, 'sort_by_score' ] );
	}

	/**
	 * Add the SEO score column after the title column.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $columns Existing column definitions.
	 * @return string[] Modified columns.
	 */
	public function add_columns( array $columns ): array {
		$result = [];

		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;

			// Insert our column right after the title column.
			if ( 'title' === $key ) {
				$result['seo_ai_score'] = esc_html__( 'SEO', 'seo-ai' );
			}
		}

		// Fallback if 'title' column wasn't found.
		if ( ! isset( $result['seo_ai_score'] ) ) {
			$result['seo_ai_score'] = esc_html__( 'SEO', 'seo-ai' );
		}

		return $result;
	}

	/**
	 * Render the SEO score column content.
	 *
	 * Displays a color-coded circle with the numeric score.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The column identifier.
	 * @param int    $post_id The current post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'seo_ai_score' !== $column ) {
			return;
		}

		$score = $this->post_meta->get( $post_id, 'seo_score', '' );

		if ( '' === $score || false === $score ) {
			printf(
				'<span class="seo-ai-score seo-ai-score--none" title="%s">'
				. '<span class="seo-ai-score__circle"></span>'
				. '<span class="seo-ai-score__value">&ndash;</span>'
				. '</span>',
				esc_attr__( 'Not analyzed', 'seo-ai' )
			);
			return;
		}

		$score     = (int) $score;
		$css_class = $this->get_score_class( $score );
		$label     = $this->get_score_label( $score );

		// Cornerstone indicator.
		$cornerstone = $this->post_meta->get( $post_id, 'cornerstone', '0' );
		$star        = '1' === (string) $cornerstone
			? '<span class="seo-ai-cornerstone-star" title="' . esc_attr__( 'Cornerstone Content', 'seo-ai' ) . '">&#9733;</span>'
			: '';

		printf(
			'%s<span class="seo-ai-score seo-ai-score--%s" title="%s">'
			. '<span class="seo-ai-score__circle"></span>'
			. '<span class="seo-ai-score__value">%d</span>'
			. '</span>',
			$star,
			esc_attr( $css_class ),
			esc_attr( $label ),
			$score
		);
	}

	/**
	 * Register the SEO score column as sortable.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $columns Existing sortable columns.
	 * @return string[] Modified sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		$columns['seo_ai_score'] = 'seo_ai_score';

		return $columns;
	}

	/**
	 * Modify the main query to support sorting by SEO score.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $query The current query.
	 * @return void
	 */
	public function sort_by_score( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'seo_ai_score' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', Post_Meta::prefixed_key( 'seo_score' ) );
		$query->set( 'orderby', 'meta_value_num' );

		// Include posts without a score by using a LEFT JOIN approach.
		$query->set( 'meta_query', [
			'relation' => 'OR',
			[
				'key'     => Post_Meta::prefixed_key( 'seo_score' ),
				'compare' => 'EXISTS',
			],
			[
				'key'     => Post_Meta::prefixed_key( 'seo_score' ),
				'compare' => 'NOT EXISTS',
			],
		] );
	}

	/**
	 * Get the CSS class for a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score The SEO score (0-100).
	 * @return string CSS modifier class.
	 */
	private function get_score_class( int $score ): string {
		if ( $score >= 70 ) {
			return 'good';
		}

		if ( $score >= 40 ) {
			return 'warning';
		}

		return 'error';
	}

	/**
	 * Get a human-readable label for a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score The SEO score (0-100).
	 * @return string Translated label.
	 */
	private function get_score_label( int $score ): string {
		if ( $score >= 70 ) {
			return __( 'Good', 'seo-ai' );
		}

		if ( $score >= 40 ) {
			return __( 'Needs Improvement', 'seo-ai' );
		}

		return __( 'Poor', 'seo-ai' );
	}

	/**
	 * Get the supported post types from options.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private function get_supported_post_types(): array {
		$default    = [ 'post', 'page' ];
		$configured = $this->options->get( 'analysis_post_types', $default );

		/** This filter is documented in includes/class-plugin.php */
		return (array) apply_filters( 'seo_ai/post_types', $configured );
	}
}
