<?php
/**
 * Head Tag Coordinator.
 *
 * Outputs meta description, robots, and canonical tags in the <head>
 * section. Coordinates with other modules to prevent duplicate tags.
 *
 * @package SeoAi\Frontend
 * @since   1.0.0
 */

namespace SeoAi\Frontend;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Helpers\Post_Meta;

/**
 * Class Head
 *
 * @since 1.0.0
 */
final class Head {

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
	 * Tags that have already been output, for deduplication.
	 *
	 * @var string[]
	 */
	private array $output_tags = [];

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper instance.
	 */
	public function __construct( Options $options ) {
		$this->options   = $options;
		$this->post_meta = new Post_Meta();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'output' ], 2 );

		// Remove the default WordPress canonical to prevent duplicates.
		remove_action( 'wp_head', 'rel_canonical' );

		// Remove default WordPress robots meta (we handle it ourselves).
		remove_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );
	}

	/**
	 * Output all <head> meta tags in order.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output(): void {
		$this->output_meta_description();
		$this->output_robots();
		$this->output_canonical();
	}

	/**
	 * Output the meta description tag.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function output_meta_description(): void {
		$description = $this->get_description();

		if ( empty( $description ) ) {
			return;
		}

		$this->print_meta_tag( 'description', $description );
	}

	/**
	 * Output the robots meta tag.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function output_robots(): void {
		$directives = $this->get_robots_directives();

		if ( empty( $directives ) ) {
			return;
		}

		$content = implode( ', ', $directives );

		printf(
			'<meta name="robots" content="%s" />' . "\n",
			esc_attr( $content )
		);

		$this->output_tags[] = 'robots';
	}

	/**
	 * Output the canonical URL link tag.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function output_canonical(): void {
		$canonical = $this->get_canonical();

		if ( empty( $canonical ) ) {
			return;
		}

		printf(
			'<link rel="canonical" href="%s" />' . "\n",
			esc_url( $canonical )
		);

		$this->output_tags[] = 'canonical';
	}

	/**
	 * Get the meta description for the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		$description = '';

		if ( is_singular() ) {
			$post_id     = get_queried_object_id();
			$description = $this->post_meta->get( $post_id, 'description' );

			// Fallback to excerpt or auto-generated.
			if ( empty( $description ) ) {
				$post = get_post( $post_id );

				if ( $post instanceof \WP_Post ) {
					if ( ! empty( $post->post_excerpt ) ) {
						$description = $post->post_excerpt;
					} else {
						$description = wp_trim_words(
							wp_strip_all_tags( strip_shortcodes( $post->post_content ) ),
							30,
							'...'
						);
					}
				}
			}
		} elseif ( is_front_page() || is_home() ) {
			$description = $this->options->get( 'homepage_description', '' );

			if ( empty( $description ) ) {
				$description = get_bloginfo( 'description' );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term && ! empty( $term->description ) ) {
				$description = $term->description;
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();

			if ( $author instanceof \WP_User ) {
				$description = get_the_author_meta( 'description', $author->ID );
			}
		} elseif ( is_post_type_archive() ) {
			$post_type_obj = get_queried_object();

			if ( $post_type_obj instanceof \WP_Post_Type && ! empty( $post_type_obj->description ) ) {
				$description = $post_type_obj->description;
			}
		}

		$description = wp_strip_all_tags( $description );

		// Truncate to 160 characters.
		if ( mb_strlen( $description ) > 160 ) {
			$description = mb_substr( $description, 0, 157 ) . '...';
		}

		/**
		 * Filters the meta description for the current page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $description The meta description.
		 */
		return (string) apply_filters( 'seo_ai/meta_description', $description );
	}

	/**
	 * Get the robots meta directives for the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of robots directives (e.g. ['index', 'follow']).
	 */
	public function get_robots_directives(): array {
		$directives = [];

		// Check global settings for the current context.
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$post    = get_post( $post_id );

			// Per-post robots override.
			$post_robots = $this->post_meta->get( $post_id, 'robots', [] );

			if ( is_array( $post_robots ) && ! empty( $post_robots ) ) {
				return array_map( 'sanitize_text_field', $post_robots );
			}

			// Check post type defaults.
			if ( $post instanceof \WP_Post ) {
				$noindex = $this->options->get( "pt_{$post->post_type}_noindex", false );

				if ( $noindex ) {
					$directives[] = 'noindex';
					$directives[] = 'follow';
					return $directives;
				}
			}
		} elseif ( is_category() ) {
			if ( $this->options->get( 'tax_category_noindex', false ) ) {
				$directives[] = 'noindex';
				$directives[] = 'follow';
				return $directives;
			}
		} elseif ( is_tag() ) {
			if ( $this->options->get( 'tax_post_tag_noindex', true ) ) {
				$directives[] = 'noindex';
				$directives[] = 'follow';
				return $directives;
			}
		} elseif ( is_search() ) {
			$directives[] = 'noindex';
			$directives[] = 'follow';
			return $directives;
		} elseif ( is_404() ) {
			$directives[] = 'noindex';
			$directives[] = 'follow';
			return $directives;
		}

		// Default: index, follow — but only output if there's something meaningful to say.
		if ( ! empty( $directives ) ) {
			return $directives;
		}

		// Add max-image-preview for all indexable pages.
		$directives[] = 'index';
		$directives[] = 'follow';
		$directives[] = 'max-image-preview:large';

		/**
		 * Filters the robots meta directives.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $directives Array of robots directives.
		 */
		return (array) apply_filters( 'seo_ai/robots_directives', $directives );
	}

	/**
	 * Get the canonical URL for the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_canonical(): string {
		$canonical = '';

		if ( is_singular() ) {
			$post_id   = get_queried_object_id();
			$canonical = $this->post_meta->get( $post_id, 'canonical' );

			if ( empty( $canonical ) ) {
				$canonical = wp_get_canonical_url( $post_id );
			}
		} elseif ( is_front_page() || is_home() ) {
			$canonical = home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$canonical = get_term_link( $term );

				if ( is_wp_error( $canonical ) ) {
					$canonical = '';
				}
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();

			if ( $author instanceof \WP_User ) {
				$canonical = get_author_posts_url( $author->ID );
			}
		} elseif ( is_post_type_archive() ) {
			$post_type = get_queried_object();

			if ( $post_type instanceof \WP_Post_Type ) {
				$canonical = get_post_type_archive_link( $post_type->name );
			}
		}

		// Handle pagination.
		if ( ! empty( $canonical ) ) {
			$paged = get_query_var( 'paged', 0 );

			if ( $paged > 1 ) {
				if ( get_option( 'permalink_structure' ) ) {
					$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
				} else {
					$canonical = add_query_arg( 'paged', $paged, $canonical );
				}
			}
		}

		/**
		 * Filters the canonical URL for the current page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $canonical The canonical URL.
		 */
		return (string) apply_filters( 'seo_ai/canonical_url', $canonical );
	}

	/**
	 * Check if a specific tag type has already been output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Tag name to check (e.g. 'description', 'robots', 'canonical').
	 * @return bool
	 */
	public function has_output_tag( string $tag ): bool {
		return in_array( $tag, $this->output_tags, true );
	}

	/**
	 * Print a <meta name="..." content="..." /> tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name    The meta name attribute.
	 * @param string $content The meta content attribute.
	 * @return void
	 */
	private function print_meta_tag( string $name, string $content ): void {
		if ( in_array( $name, $this->output_tags, true ) ) {
			return;
		}

		printf(
			'<meta name="%s" content="%s" />' . "\n",
			esc_attr( $name ),
			esc_attr( $content )
		);

		$this->output_tags[] = $name;
	}
}
