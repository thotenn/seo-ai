<?php
/**
 * Breadcrumb Trail Generator.
 *
 * Generates semantic breadcrumb navigation trails based on the
 * current page context. Supports shortcodes and a global template function.
 *
 * @package SeoAi\Modules\Breadcrumbs
 * @since   1.0.0
 */

namespace SeoAi\Modules\Breadcrumbs;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Breadcrumbs
 *
 * @since 1.0.0
 */
final class Breadcrumbs {

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
		add_shortcode( 'seo_ai_breadcrumb', [ $this, 'shortcode_handler' ] );
	}

	/**
	 * Render the breadcrumb trail HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Breadcrumb display arguments.
	 *
	 *     @type string $separator     Separator between breadcrumb items. Default from settings.
	 *     @type bool   $show_home     Whether to show the Home link. Default from settings.
	 *     @type string $home_text     Home link text. Default from settings.
	 *     @type string $wrapper_class CSS class for the wrapper element. Default 'seo-ai-breadcrumbs'.
	 * }
	 * @return string Breadcrumb HTML.
	 */
	public function render( array $args = [] ): string {
		$defaults = [
			'separator'     => $this->options->get( 'breadcrumb_separator', "\u{00BB}" ),
			'show_home'     => (bool) $this->options->get( 'breadcrumb_show_home', true ),
			'home_text'     => $this->options->get( 'breadcrumb_home_text', 'Home' ),
			'wrapper_class' => 'seo-ai-breadcrumbs',
		];

		$args = wp_parse_args( $args, $defaults );

		$items = $this->build_trail( $args );

		if ( empty( $items ) ) {
			return '';
		}

		$separator_html = sprintf(
			' <span class="seo-ai-breadcrumb-separator">%s</span> ',
			esc_html( $args['separator'] )
		);

		$output = sprintf(
			'<nav class="%s" aria-label="%s">',
			esc_attr( $args['wrapper_class'] ),
			esc_attr__( 'Breadcrumb', 'seo-ai' )
		);

		$rendered_items = [];

		foreach ( $items as $index => $item ) {
			$is_last = ( $index === count( $items ) - 1 );

			if ( $is_last ) {
				$rendered_items[] = sprintf(
					'<span class="seo-ai-breadcrumb-current" aria-current="page">%s</span>',
					esc_html( $item['title'] )
				);
			} else {
				$rendered_items[] = sprintf(
					'<span class="seo-ai-breadcrumb-item"><a href="%s">%s</a></span>',
					esc_url( $item['url'] ),
					esc_html( $item['title'] )
				);
			}
		}

		$output .= implode( $separator_html, $rendered_items );
		$output .= '</nav>';

		/**
		 * Filters the breadcrumb HTML output.
		 *
		 * @since 1.0.0
		 *
		 * @param string $output The breadcrumb HTML.
		 * @param array  $items  The breadcrumb trail items.
		 * @param array  $args   The display arguments.
		 */
		return (string) apply_filters( 'seo_ai/breadcrumb_html', $output, $items, $args );
	}

	/**
	 * Shortcode handler for [seo_ai_breadcrumb].
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Breadcrumb HTML.
	 */
	public function shortcode_handler( $atts ): string {
		$atts = shortcode_atts( [
			'separator'     => '',
			'show_home'     => '',
			'home_text'     => '',
			'wrapper_class' => '',
		], $atts, 'seo_ai_breadcrumb' );

		$args = array_filter( $atts, function ( $value ) {
			return '' !== $value;
		} );

		// Convert show_home to boolean if provided.
		if ( isset( $args['show_home'] ) ) {
			$args['show_home'] = filter_var( $args['show_home'], FILTER_VALIDATE_BOOLEAN );
		}

		return $this->render( $args );
	}

	/**
	 * Build the breadcrumb trail based on the current page context.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Display arguments (includes show_home, home_text, etc.).
	 * @return array[] Array of breadcrumb items, each with 'title' and 'url' keys.
	 */
	private function build_trail( array $args ): array {
		$items = [];

		// Home item.
		if ( $args['show_home'] ) {
			$items[] = [
				'title' => $args['home_text'],
				'url'   => home_url( '/' ),
			];
		}

		// Front page: just the home link.
		if ( is_front_page() ) {
			if ( ! empty( $items ) ) {
				// Replace the last item's URL with empty so it becomes current.
				// Actually for front_page, just return Home as current.
				return $items;
			}
			return [];
		}

		// Home (blog) page.
		if ( is_home() ) {
			$blog_page_id = (int) get_option( 'page_for_posts' );

			if ( $blog_page_id > 0 ) {
				$items[] = [
					'title' => get_the_title( $blog_page_id ),
					'url'   => get_permalink( $blog_page_id ),
				];
			} else {
				$items[] = [
					'title' => __( 'Blog', 'seo-ai' ),
					'url'   => '',
				];
			}

			return $items;
		}

		// Single post.
		if ( is_singular( 'post' ) ) {
			$post       = get_queried_object();
			$categories = get_the_category( $post->ID );

			if ( ! empty( $categories ) ) {
				$primary_category = $this->get_primary_category( $categories );

				// Build parent category chain.
				$cat_chain = $this->get_category_ancestors( $primary_category );

				foreach ( $cat_chain as $cat ) {
					$items[] = [
						'title' => $cat->name,
						'url'   => get_category_link( $cat->term_id ),
					];
				}

				$items[] = [
					'title' => $primary_category->name,
					'url'   => get_category_link( $primary_category->term_id ),
				];
			}

			$items[] = [
				'title' => get_the_title( $post->ID ),
				'url'   => get_permalink( $post->ID ),
			];

			return $items;
		}

		// Single page (hierarchical).
		if ( is_page() ) {
			$post = get_queried_object();

			if ( $post->post_parent ) {
				$ancestors = array_reverse( get_post_ancestors( $post->ID ) );

				foreach ( $ancestors as $ancestor_id ) {
					$items[] = [
						'title' => get_the_title( $ancestor_id ),
						'url'   => get_permalink( $ancestor_id ),
					];
				}
			}

			$items[] = [
				'title' => get_the_title( $post->ID ),
				'url'   => get_permalink( $post->ID ),
			];

			return $items;
		}

		// Single custom post type.
		if ( is_singular() ) {
			$post      = get_queried_object();
			$post_type = get_post_type_object( $post->post_type );

			if ( $post_type && $post_type->has_archive ) {
				$items[] = [
					'title' => $post_type->labels->name,
					'url'   => get_post_type_archive_link( $post->post_type ),
				];
			}

			$items[] = [
				'title' => get_the_title( $post->ID ),
				'url'   => get_permalink( $post->ID ),
			];

			return $items;
		}

		// Category archive.
		if ( is_category() ) {
			$term = get_queried_object();

			// Parent categories.
			if ( $term->parent ) {
				$ancestors = $this->get_category_ancestors( $term );

				foreach ( $ancestors as $ancestor ) {
					$items[] = [
						'title' => $ancestor->name,
						'url'   => get_category_link( $ancestor->term_id ),
					];
				}
			}

			$items[] = [
				'title' => $term->name,
				'url'   => get_category_link( $term->term_id ),
			];

			return $items;
		}

		// Tag archive.
		if ( is_tag() ) {
			$term = get_queried_object();

			$items[] = [
				'title' => $term->name,
				'url'   => get_tag_link( $term->term_id ),
			];

			return $items;
		}

		// Custom taxonomy archive.
		if ( is_tax() ) {
			$term     = get_queried_object();
			$taxonomy = get_taxonomy( $term->taxonomy );

			if ( $taxonomy ) {
				$items[] = [
					'title' => $taxonomy->labels->name,
					'url'   => '',
				];
			}

			$items[] = [
				'title' => $term->name,
				'url'   => get_term_link( $term ),
			];

			return $items;
		}

		// Author archive.
		if ( is_author() ) {
			$author = get_queried_object();

			$items[] = [
				'title' => $author->display_name,
				'url'   => get_author_posts_url( $author->ID ),
			];

			return $items;
		}

		// Date archive.
		if ( is_date() ) {
			if ( is_year() ) {
				$items[] = [
					'title' => get_the_date( 'Y' ),
					'url'   => get_year_link( get_the_date( 'Y' ) ),
				];
			} elseif ( is_month() ) {
				$items[] = [
					'title' => get_the_date( 'Y' ),
					'url'   => get_year_link( get_the_date( 'Y' ) ),
				];

				$items[] = [
					'title' => get_the_date( 'F' ),
					'url'   => get_month_link( get_the_date( 'Y' ), get_the_date( 'm' ) ),
				];
			} elseif ( is_day() ) {
				$items[] = [
					'title' => get_the_date( 'Y' ),
					'url'   => get_year_link( get_the_date( 'Y' ) ),
				];

				$items[] = [
					'title' => get_the_date( 'F' ),
					'url'   => get_month_link( get_the_date( 'Y' ), get_the_date( 'm' ) ),
				];

				$items[] = [
					'title' => get_the_date( 'j' ),
					'url'   => get_day_link( get_the_date( 'Y' ), get_the_date( 'm' ), get_the_date( 'd' ) ),
				];
			}

			return $items;
		}

		// Search results.
		if ( is_search() ) {
			$items[] = [
				/* translators: %s: search query */
				'title' => sprintf( __( 'Search: %s', 'seo-ai' ), get_search_query() ),
				'url'   => '',
			];

			return $items;
		}

		// 404 page.
		if ( is_404() ) {
			$items[] = [
				'title' => __( 'Not Found', 'seo-ai' ),
				'url'   => '',
			];

			return $items;
		}

		// Post type archive.
		if ( is_post_type_archive() ) {
			$post_type = get_queried_object();

			if ( $post_type instanceof \WP_Post_Type ) {
				$items[] = [
					'title' => $post_type->labels->name,
					'url'   => get_post_type_archive_link( $post_type->name ),
				];
			}

			return $items;
		}

		return $items;
	}

	/**
	 * Get the primary category from a list of categories.
	 *
	 * Returns the first category that is not 'Uncategorized',
	 * or the first category if all are 'Uncategorized'.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term[] $categories Array of category term objects.
	 * @return \WP_Term The primary category.
	 */
	private function get_primary_category( array $categories ): \WP_Term {
		// If only one category, return it.
		if ( count( $categories ) === 1 ) {
			return $categories[0];
		}

		// Prefer categories that are not 'Uncategorized'.
		$uncategorized_id = (int) get_option( 'default_category', 1 );

		foreach ( $categories as $category ) {
			if ( $category->term_id !== $uncategorized_id ) {
				return $category;
			}
		}

		return $categories[0];
	}

	/**
	 * Get the ancestor chain for a category (parent to root).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term The category term.
	 * @return \WP_Term[] Array of ancestor terms, from root to immediate parent.
	 */
	private function get_category_ancestors( \WP_Term $term ): array {
		$ancestors    = [];
		$ancestor_ids = get_ancestors( $term->term_id, 'category', 'taxonomy' );

		if ( empty( $ancestor_ids ) ) {
			return $ancestors;
		}

		// get_ancestors returns parent-to-root order; we want root-to-parent.
		$ancestor_ids = array_reverse( $ancestor_ids );

		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor_term = get_term( $ancestor_id, 'category' );

			if ( $ancestor_term instanceof \WP_Term ) {
				$ancestors[] = $ancestor_term;
			}
		}

		return $ancestors;
	}
}

/**
 * Global template function to output breadcrumbs.
 *
 * Usage in theme templates:
 *   <?php if ( function_exists( 'seo_ai_breadcrumb' ) ) seo_ai_breadcrumb(); ?>
 *
 * @since 1.0.0
 *
 * @param array $args Optional display arguments passed to Breadcrumbs::render().
 * @return void
 */
function seo_ai_breadcrumb( array $args = [] ): void {
	$options     = Options::instance();
	$breadcrumbs = new Breadcrumbs( $options );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render().
	echo $breadcrumbs->render( $args );
}
