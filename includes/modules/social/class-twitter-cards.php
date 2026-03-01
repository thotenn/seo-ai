<?php
/**
 * Twitter Cards Module.
 *
 * Outputs Twitter Card meta tags on the WordPress frontend for rich
 * sharing previews on Twitter/X.
 *
 * @package SeoAi\Modules\Social
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SeoAi\Modules\Social;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;
use SeoAi\Modules\Meta_Tags\Meta_Tags;

/**
 * Class Twitter_Cards
 *
 * Hooks into wp_head at priority 5 and outputs twitter: prefixed meta tags
 * for Twitter Card support.
 *
 * @since 1.0.0
 */
final class Twitter_Cards {

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Open Graph module instance for fallback values.
	 *
	 * @var Open_Graph|null
	 */
	private ?Open_Graph $open_graph = null;

	/**
	 * Meta Tags module instance for fallback values.
	 *
	 * @var Meta_Tags|null
	 */
	private ?Meta_Tags $meta_tags = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->options = Options::instance();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'output_twitter_tags' ], 5 );
	}

	/**
	 * Output Twitter Card meta tags in the document <head>.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_twitter_tags(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$post_id = $this->get_current_post_id();
		$tags    = $this->get_twitter_data( $post_id );

		if ( empty( $tags ) ) {
			return;
		}

		echo "\n<!-- SEO AI Twitter Card -->\n";

		foreach ( $tags as $name => $content ) {
			if ( '' === $content || null === $content ) {
				continue;
			}

			printf(
				'<meta name="%s" content="%s" />' . "\n",
				esc_attr( $name ),
				esc_attr( (string) $content )
			);
		}

		echo "<!-- / SEO AI Twitter Card -->\n\n";
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Get the Twitter Card data array for a post or the current page.
	 *
	 * @param int|null $post_id Post ID, or null for the current page context.
	 * @return array<string, string> Associative array of Twitter Card properties.
	 */
	private function get_twitter_data( ?int $post_id ): array {
		$meta_tags  = $this->get_meta_tags_instance();
		$open_graph = $this->get_open_graph_instance();
		$og_data    = $open_graph ? $open_graph->get_og_data( $post_id ) : [];

		$data = [];

		// Card type.
		$card_type = (string) $this->options->get( 'twitter_card_type', 'summary_large_image' );

		if ( ! in_array( $card_type, [ 'summary', 'summary_large_image' ], true ) ) {
			$card_type = 'summary_large_image';
		}

		$data['twitter:card'] = $card_type;

		// Title: custom twitter title > OG title > SEO title > post title.
		$data['twitter:title'] = $this->get_twitter_title( $post_id, $og_data, $meta_tags );

		// Description: custom twitter desc > OG desc > SEO desc.
		$data['twitter:description'] = $this->get_twitter_description( $post_id, $og_data, $meta_tags );

		// Image: same as OG image.
		$data['twitter:image'] = $og_data['og:image'] ?? '';

		// Site handle.
		$site_handle = (string) $this->options->get( 'twitter_site', '' );

		if ( '' !== $site_handle ) {
			// Ensure the handle starts with @.
			if ( '@' !== substr( $site_handle, 0, 1 ) ) {
				$site_handle = '@' . $site_handle;
			}

			$data['twitter:site'] = $site_handle;
		}

		// Creator handle (from author meta if available).
		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$creator = get_the_author_meta( 'twitter', (int) $post->post_author );

				if ( is_string( $creator ) && '' !== $creator ) {
					if ( '@' !== substr( $creator, 0, 1 ) ) {
						$creator = '@' . $creator;
					}

					$data['twitter:creator'] = $creator;
				}
			}
		}

		/**
		 * Filters the Twitter Card data array.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $data    Twitter Card properties.
		 * @param int|null $post_id The post ID, or null.
		 */
		return (array) apply_filters( 'seo_ai/twitter/data', $data, $post_id );
	}

	/**
	 * Get the Twitter-specific title with fallbacks.
	 *
	 * Priority: custom twitter title > OG title > SEO title > post title > site name.
	 *
	 * @param int|null       $post_id   Post ID.
	 * @param array          $og_data   OG data for fallback.
	 * @param Meta_Tags|null $meta_tags Meta Tags instance for fallback.
	 * @return string
	 */
	private function get_twitter_title( ?int $post_id, array $og_data, ?Meta_Tags $meta_tags ): string {
		// 1. Custom Twitter title.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_twitter_title', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return $custom;
			}
		}

		// 2. OG title.
		if ( ! empty( $og_data['og:title'] ) ) {
			return $og_data['og:title'];
		}

		// 3. SEO title.
		if ( $meta_tags ) {
			$seo_title = $meta_tags->get_title( $post_id );

			if ( '' !== $seo_title ) {
				return $seo_title;
			}
		}

		// 4. Post title.
		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				return $post->post_title;
			}
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Get the Twitter-specific description with fallbacks.
	 *
	 * Priority: custom twitter desc > OG desc > SEO desc > excerpt.
	 *
	 * @param int|null       $post_id   Post ID.
	 * @param array          $og_data   OG data for fallback.
	 * @param Meta_Tags|null $meta_tags Meta Tags instance for fallback.
	 * @return string
	 */
	private function get_twitter_description( ?int $post_id, array $og_data, ?Meta_Tags $meta_tags ): string {
		// 1. Custom Twitter description.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_twitter_description', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return $this->truncate( $custom, 200 );
			}
		}

		// 2. OG description.
		if ( ! empty( $og_data['og:description'] ) ) {
			return $og_data['og:description'];
		}

		// 3. SEO description.
		if ( $meta_tags ) {
			$seo_desc = $meta_tags->get_description( $post_id );

			if ( '' !== $seo_desc ) {
				return $seo_desc;
			}
		}

		// 4. Post excerpt / content.
		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$text = $post->post_excerpt ?: $post->post_content;

				return $this->truncate( wp_strip_all_tags( strip_shortcodes( $text ) ), 200 );
			}
		}

		return get_bloginfo( 'description' );
	}

	/**
	 * Truncate text to a maximum length on a word boundary.
	 *
	 * @param string $text      The text to truncate.
	 * @param int    $max_length Maximum character length.
	 * @return string Truncated text.
	 */
	private function truncate( string $text, int $max_length ): string {
		$text = wp_strip_all_tags( $text );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text, 'UTF-8' ) <= $max_length ) {
			return $text;
		}

		$text = mb_substr( $text, 0, $max_length - 3, 'UTF-8' );
		$last = mb_strrpos( $text, ' ', 0, 'UTF-8' );

		if ( false !== $last && $last > (int) ( $max_length * 0.6 ) ) {
			$text = mb_substr( $text, 0, $last, 'UTF-8' );
		}

		return $text . '...';
	}

	/**
	 * Get the current post ID from the global query.
	 *
	 * @return int|null Post ID, or null if not applicable.
	 */
	private function get_current_post_id(): ?int {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			return $post_id > 0 ? $post_id : null;
		}

		if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
			$page_id = (int) get_option( 'page_on_front' );

			return $page_id > 0 ? $page_id : null;
		}

		if ( is_home() && ! is_front_page() ) {
			$page_id = (int) get_option( 'page_for_posts' );

			return $page_id > 0 ? $page_id : null;
		}

		return null;
	}

	/**
	 * Get or create the Open Graph module instance.
	 *
	 * @return Open_Graph|null
	 */
	private function get_open_graph_instance(): ?Open_Graph {
		if ( null === $this->open_graph ) {
			if ( class_exists( Open_Graph::class ) ) {
				$this->open_graph = new Open_Graph();
			}
		}

		return $this->open_graph;
	}

	/**
	 * Get or create the Meta Tags module instance.
	 *
	 * @return Meta_Tags|null
	 */
	private function get_meta_tags_instance(): ?Meta_Tags {
		if ( null === $this->meta_tags ) {
			if ( class_exists( Meta_Tags::class ) ) {
				$this->meta_tags = new Meta_Tags();
			}
		}

		return $this->meta_tags;
	}
}
