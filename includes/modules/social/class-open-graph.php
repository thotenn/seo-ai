<?php
/**
 * Open Graph Module.
 *
 * Outputs Open Graph meta tags on the WordPress frontend for social media
 * sharing previews on Facebook, LinkedIn, and other platforms.
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
 * Class Open_Graph
 *
 * Hooks into wp_head at priority 5 and outputs og: prefixed meta tags
 * for Open Graph protocol support.
 *
 * @since 1.0.0
 */
final class Open_Graph {

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Meta Tags module instance for fallback title/description.
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
		add_action( 'wp_head', [ $this, 'output_og_tags' ], 5 );
	}

	/**
	 * Output Open Graph meta tags in the document <head>.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_og_tags(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! (bool) $this->options->get( 'og_enabled', true ) ) {
			return;
		}

		$post_id = $this->get_current_post_id();
		$data    = $this->get_og_data( $post_id );

		if ( empty( $data ) ) {
			return;
		}

		echo "\n<!-- SEO AI Open Graph -->\n";

		foreach ( $data as $property => $content ) {
			if ( '' === $content || null === $content ) {
				continue;
			}

			// Properties starting with "article:" or "og:" use property attribute.
			printf(
				'<meta property="%s" content="%s" />' . "\n",
				esc_attr( $property ),
				esc_attr( (string) $content )
			);
		}

		// Facebook App ID (separate from main OG data).
		$fb_app_id = (string) $this->options->get( 'facebook_app_id', '' );

		if ( '' !== $fb_app_id ) {
			printf(
				'<meta property="fb:app_id" content="%s" />' . "\n",
				esc_attr( $fb_app_id )
			);
		}

		echo "<!-- / SEO AI Open Graph -->\n\n";
	}

	/**
	 * Get the Open Graph data array for a post or the current page.
	 *
	 * Returns an associative array of OG property names to values.
	 * Properties with empty values are included but will be skipped in output.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID, or null for the current page context.
	 * @return array<string, string> Associative array of OG properties.
	 */
	public function get_og_data( ?int $post_id = null ): array {
		$meta_tags = $this->get_meta_tags_instance();

		$data = [
			'og:locale'    => get_locale(),
			'og:site_name' => get_bloginfo( 'name' ),
			'og:url'       => $meta_tags ? $meta_tags->get_canonical( $post_id ) : $this->get_current_url(),
		];

		// Type.
		$data['og:type'] = $this->get_og_type( $post_id );

		// Title.
		$data['og:title'] = $this->get_og_title( $post_id, $meta_tags );

		// Description.
		$data['og:description'] = $this->get_og_description( $post_id, $meta_tags );

		// Image.
		$image_data = $this->get_og_image( $post_id );

		$data['og:image'] = $image_data['url'] ?? '';

		if ( ! empty( $image_data['width'] ) ) {
			$data['og:image:width'] = (string) $image_data['width'];
		}

		if ( ! empty( $image_data['height'] ) ) {
			$data['og:image:height'] = (string) $image_data['height'];
		}

		if ( ! empty( $image_data['type'] ) ) {
			$data['og:image:type'] = $image_data['type'];
		}

		// Article-specific properties.
		if ( 'article' === $data['og:type'] && $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$data['article:published_time'] = get_the_date( 'c', $post );
				$data['article:modified_time']  = get_the_modified_date( 'c', $post );

				$author = get_userdata( (int) $post->post_author );

				if ( $author instanceof \WP_User ) {
					$data['article:author'] = $author->display_name;
				}

				// Article section (primary category).
				$categories = get_the_category( $post_id );

				if ( ! empty( $categories ) ) {
					$data['article:section'] = $categories[0]->name;
				}

				// Article tags.
				$tags = get_the_tags( $post_id );

				if ( is_array( $tags ) && ! empty( $tags ) ) {
					// Only output the first 5 tags to keep it reasonable.
					$tag_names = array_slice(
						array_map( fn( \WP_Term $tag ) => $tag->name, $tags ),
						0,
						5
					);

					// article:tag supports multiple values; output as separate entries.
					// We store only the first one here; additional tags handled in output.
					$data['article:tag'] = implode( ', ', $tag_names );
				}
			}
		}

		/**
		 * Filters the Open Graph data array.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $data    OG properties.
		 * @param int|null $post_id The post ID, or null.
		 */
		return (array) apply_filters( 'seo_ai/og/data', $data, $post_id );
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Get the OG title, with fallbacks.
	 *
	 * Priority: custom OG title meta > SEO title > post title > site name.
	 *
	 * @param int|null       $post_id   Post ID.
	 * @param Meta_Tags|null $meta_tags Meta Tags instance for fallback.
	 * @return string
	 */
	private function get_og_title( ?int $post_id, ?Meta_Tags $meta_tags ): string {
		// 1. Custom OG title.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_og_title', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return $custom;
			}
		}

		// 2. SEO title from Meta Tags module.
		if ( $meta_tags ) {
			$seo_title = $meta_tags->get_title( $post_id );

			if ( '' !== $seo_title ) {
				return $seo_title;
			}
		}

		// 3. Post title.
		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				return $post->post_title;
			}
		}

		// 4. Site name.
		return get_bloginfo( 'name' );
	}

	/**
	 * Get the OG description, with fallbacks.
	 *
	 * Priority: custom OG description meta > SEO description > excerpt > site tagline.
	 *
	 * @param int|null       $post_id   Post ID.
	 * @param Meta_Tags|null $meta_tags Meta Tags instance for fallback.
	 * @return string
	 */
	private function get_og_description( ?int $post_id, ?Meta_Tags $meta_tags ): string {
		// 1. Custom OG description.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_og_description', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return $this->truncate_description( $custom );
			}
		}

		// 2. SEO description from Meta Tags module.
		if ( $meta_tags ) {
			$seo_desc = $meta_tags->get_description( $post_id );

			if ( '' !== $seo_desc ) {
				return $seo_desc;
			}
		}

		// 3. Post excerpt / content.
		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$text = $post->post_excerpt ?: $post->post_content;
				$text = wp_strip_all_tags( strip_shortcodes( $text ) );

				return $this->truncate_description( $text );
			}
		}

		// 4. Site tagline.
		return get_bloginfo( 'description' );
	}

	/**
	 * Get the OG image data (URL, width, height, MIME type).
	 *
	 * Priority: custom OG image meta > featured image > default OG image from settings.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array{url?: string, width?: int, height?: int, type?: string}
	 */
	private function get_og_image( ?int $post_id ): array {
		// 1. Custom OG image (stored as attachment ID).
		if ( $post_id ) {
			$custom_image = get_post_meta( $post_id, '_seo_ai_og_image', true );

			if ( $custom_image ) {
				$image_data = $this->get_attachment_data( $custom_image );

				if ( $image_data ) {
					return $image_data;
				}
			}
		}

		// 2. Featured image.
		if ( $post_id ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );

			if ( $thumbnail_id ) {
				$image_data = $this->get_attachment_data( $thumbnail_id );

				if ( $image_data ) {
					return $image_data;
				}
			}
		}

		// 3. Default OG image from settings.
		$default_image = (string) $this->options->get( 'og_default_image', '' );

		if ( '' !== $default_image ) {
			$image_data = $this->get_attachment_data( $default_image );

			if ( $image_data ) {
				return $image_data;
			}

			// If it's a URL string rather than an attachment ID.
			if ( filter_var( $default_image, FILTER_VALIDATE_URL ) ) {
				return [ 'url' => $default_image ];
			}
		}

		return [];
	}

	/**
	 * Get attachment image data from an attachment ID or URL.
	 *
	 * @param mixed $attachment_id_or_url Attachment ID (int/string) or URL.
	 * @return array|null Image data array, or null if not resolvable.
	 */
	private function get_attachment_data( mixed $attachment_id_or_url ): ?array {
		// If it's a URL, try to find the attachment ID.
		if ( is_string( $attachment_id_or_url ) && ! is_numeric( $attachment_id_or_url ) ) {
			if ( filter_var( $attachment_id_or_url, FILTER_VALIDATE_URL ) ) {
				$attachment_id = attachment_url_to_postid( $attachment_id_or_url );

				if ( 0 === $attachment_id ) {
					return [ 'url' => $attachment_id_or_url ];
				}

				$attachment_id_or_url = $attachment_id;
			} else {
				return null;
			}
		}

		$attachment_id = (int) $attachment_id_or_url;

		if ( $attachment_id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			return null;
		}

		$data = [ 'url' => $url ];

		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $metadata ) ) {
			if ( ! empty( $metadata['width'] ) ) {
				$data['width'] = (int) $metadata['width'];
			}

			if ( ! empty( $metadata['height'] ) ) {
				$data['height'] = (int) $metadata['height'];
			}
		}

		// MIME type.
		$mime = get_post_mime_type( $attachment_id );

		if ( $mime ) {
			$data['type'] = $mime;
		}

		return $data;
	}

	/**
	 * Determine the OG type for the current page.
	 *
	 * @param int|null $post_id Post ID.
	 * @return string OG type ('article', 'website', 'profile').
	 */
	private function get_og_type( ?int $post_id ): string {
		if ( is_front_page() || is_home() ) {
			return 'website';
		}

		if ( is_author() ) {
			return 'profile';
		}

		if ( is_singular() && $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post && 'post' === $post->post_type ) {
				return 'article';
			}

			// Pages and custom post types default to 'article' as well for
			// consistent sharing previews, unless they are the front page.
			return 'article';
		}

		return 'website';
	}

	/**
	 * Truncate a description to 200 characters for OG.
	 *
	 * OG descriptions can be slightly longer than meta descriptions.
	 *
	 * @param string $text Raw text.
	 * @return string Truncated text.
	 */
	private function truncate_description( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text, 'UTF-8' ) > 200 ) {
			$text = mb_substr( $text, 0, 197, 'UTF-8' );
			$last = mb_strrpos( $text, ' ', 0, 'UTF-8' );

			if ( false !== $last && $last > 120 ) {
				$text = mb_substr( $text, 0, $last, 'UTF-8' );
			}

			$text .= '...';
		}

		return $text;
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
	 * Get the current page URL as a fallback canonical.
	 *
	 * @return string
	 */
	private function get_current_url(): string {
		if ( is_singular() ) {
			return (string) get_permalink();
		}

		// Construct from server variables.
		$protocol = is_ssl() ? 'https://' : 'http://';
		$host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return $protocol . $host . $uri;
	}

	/**
	 * Get or create the Meta Tags module instance.
	 *
	 * Lazily instantiates a Meta_Tags object for retrieving the SEO title
	 * and description as fallbacks.
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
