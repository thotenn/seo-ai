<?php
/**
 * Meta Tags Module.
 *
 * Manages SEO meta tag output on the WordPress frontend.
 * Hooks into wp_head at priority 1 and overrides the document title
 * via the document_title_parts filter.
 *
 * @package SeoAi\Modules\Meta_Tags
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace SeoAi\Modules\Meta_Tags;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Meta_Tags
 *
 * Outputs <meta> description, robots, and canonical link tags in the document
 * head. Title is handled through the document_title_parts filter for
 * compatibility with WordPress and Gutenberg.
 *
 * @since 1.0.0
 */
final class Meta_Tags {

	/**
	 * Options helper instance.
	 *
	 * @var Options
	 */
	private Options $options;

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
		add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );
		add_filter( 'document_title_parts', [ $this, 'filter_title_parts' ], 10 );
		add_filter( 'pre_get_document_title', [ $this, 'filter_pre_get_document_title' ], 10 );
	}

	/**
	 * Output meta tags into the document <head>.
	 *
	 * Outputs description, robots, and canonical tags. Title is handled via
	 * the document_title_parts filter, not echoed here.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_meta_tags(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$post_id = $this->get_current_post_id();

		$description = $this->get_description( $post_id );
		$robots      = $this->get_robots( $post_id );
		$canonical   = $this->get_canonical( $post_id );

		echo "\n<!-- SEO AI Meta Tags -->\n";

		if ( $description ) {
			printf(
				'<meta name="description" content="%s" />' . "\n",
				esc_attr( $description )
			);
		}

		if ( $robots ) {
			printf(
				'<meta name="robots" content="%s" />' . "\n",
				esc_attr( $robots )
			);
		}

		if ( $canonical ) {
			printf(
				'<link rel="canonical" href="%s" />' . "\n",
				esc_url( $canonical )
			);
		}

		echo "<!-- / SEO AI Meta Tags -->\n\n";
	}

	/**
	 * Filter the document title parts for Gutenberg and theme compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array $title_parts The document title parts.
	 * @return array Modified title parts.
	 */
	public function filter_title_parts( array $title_parts ): array {
		if ( is_admin() ) {
			return $title_parts;
		}

		$post_id = $this->get_current_post_id();
		$title   = $this->get_title( $post_id );

		if ( $title ) {
			// Override the title entirely; WordPress will concatenate parts otherwise.
			$title_parts['title'] = $title;

			// Remove tagline and site so only our custom title renders.
			unset( $title_parts['tagline'], $title_parts['site'] );
		}

		return $title_parts;
	}

	/**
	 * Filter pre_get_document_title to supply a fully composed title string.
	 *
	 * Returning a non-empty string here bypasses wp_get_document_title() logic
	 * and uses this value directly. This ensures our custom title always wins.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The pre-existing title (empty by default).
	 * @return string
	 */
	public function filter_pre_get_document_title( string $title ): string {
		if ( is_admin() ) {
			return $title;
		}

		$post_id     = $this->get_current_post_id();
		$custom_title = $this->get_title( $post_id );

		return $custom_title ?: $title;
	}

	/**
	 * Get the SEO title for a post or the current page.
	 *
	 * Checks for a custom meta title first, then falls back to a template-based
	 * title from plugin settings with variable replacement.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID, or null for the current queried object.
	 * @return string The SEO title, or empty string if none determined.
	 */
	public function get_title( ?int $post_id = null ): string {
		// 1. Custom per-post title.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_title', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return $this->replace_variables( $custom, $post_id );
			}
		}

		// 2. Template from settings.
		$template = $this->get_title_template( $post_id );

		if ( $template ) {
			return $this->replace_variables( $template, $post_id );
		}

		return '';
	}

	/**
	 * Get the meta description for a post or the current page.
	 *
	 * Checks for a custom meta description first, then falls back to an
	 * auto-generated excerpt from the post content (first 160 characters).
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID, or null for the current queried object.
	 * @return string The meta description, sanitized and trimmed.
	 */
	public function get_description( ?int $post_id = null ): string {
		// 1. Custom per-post description.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_description', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return $this->sanitize_description( $custom );
			}
		}

		// 2. Template from settings.
		if ( $post_id ) {
			$post_type = get_post_type( $post_id );
			$template  = $this->options->get( "pt_{$post_type}_description", '' );

			if ( is_string( $template ) && '' !== $template ) {
				$resolved = $this->replace_variables( $template, $post_id );

				return $this->sanitize_description( $resolved );
			}
		}

		// 3. Homepage-specific description.
		if ( is_front_page() || is_home() ) {
			$homepage_desc = $this->options->get( 'homepage_description', '' );

			if ( is_string( $homepage_desc ) && '' !== $homepage_desc ) {
				return $this->sanitize_description(
					$this->replace_variables( $homepage_desc, $post_id )
				);
			}
		}

		// 4. Taxonomy archive description.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term && ! empty( $term->description ) ) {
				return $this->sanitize_description( $term->description );
			}
		}

		// 5. Auto-generated excerpt from content.
		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$content = $post->post_excerpt ?: $post->post_content;

				return $this->sanitize_description( $content );
			}
		}

		return '';
	}

	/**
	 * Get the robots meta directives for a post or the current page.
	 *
	 * Checks per-post robots meta (stored as JSON), then falls back to
	 * post type defaults from plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID, or null for the current queried object.
	 * @return string Robots directives string, e.g. "index, follow" or "noindex, nofollow".
	 */
	public function get_robots( ?int $post_id = null ): string {
		$directives = [];

		// 1. Per-post robots meta (JSON: {"noindex": true, "nofollow": false, ...}).
		if ( $post_id ) {
			$raw = get_post_meta( $post_id, '_seo_ai_robots', true );

			if ( is_string( $raw ) && '' !== $raw ) {
				$robots_meta = json_decode( $raw, true );

				if ( is_array( $robots_meta ) ) {
					return $this->build_robots_string( $robots_meta );
				}
			}

			// If stored as an array directly (some save paths may use arrays).
			if ( is_array( $raw ) ) {
				return $this->build_robots_string( $raw );
			}
		}

		// 2. Post type defaults from settings.
		if ( $post_id ) {
			$post_type = get_post_type( $post_id );
			$noindex   = (bool) $this->options->get( "pt_{$post_type}_noindex", false );

			if ( $noindex ) {
				$directives[] = 'noindex';
				$directives[] = 'follow';
			}
		}

		// 3. Taxonomy archive defaults.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$taxonomy = $term->taxonomy;
				$noindex  = (bool) $this->options->get( "tax_{$taxonomy}_noindex", false );

				if ( $noindex ) {
					$directives[] = 'noindex';
					$directives[] = 'follow';
				}
			}
		}

		// 4. Search and 404 pages are always noindex.
		if ( is_search() ) {
			$directives = [ 'noindex', 'follow' ];
		}

		if ( is_404() ) {
			$directives = [ 'noindex', 'nofollow' ];
		}

		if ( ! empty( $directives ) ) {
			return implode( ', ', array_unique( $directives ) );
		}

		// Default: index, follow (no tag needed, but we include it for clarity).
		return 'index, follow';
	}

	/**
	 * Get the canonical URL for a post or the current page.
	 *
	 * Checks for a custom canonical override, then falls back to the
	 * permalink. Handles pagination appending.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID, or null for the current queried object.
	 * @return string The canonical URL.
	 */
	public function get_canonical( ?int $post_id = null ): string {
		// 1. Custom canonical override.
		if ( $post_id ) {
			$custom = get_post_meta( $post_id, '_seo_ai_canonical', true );

			if ( is_string( $custom ) && '' !== $custom ) {
				return esc_url_raw( $custom );
			}
		}

		// 2. Taxonomy archives.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$canonical = get_term_link( $term );

				if ( ! is_wp_error( $canonical ) ) {
					return $this->maybe_append_pagination( $canonical );
				}
			}

			return '';
		}

		// 3. Post type archives.
		if ( is_post_type_archive() ) {
			$post_type = get_queried_object();

			if ( $post_type instanceof \WP_Post_Type ) {
				$canonical = get_post_type_archive_link( $post_type->name );

				return $canonical ? $this->maybe_append_pagination( $canonical ) : '';
			}

			return '';
		}

		// 4. Author archives.
		if ( is_author() ) {
			$author = get_queried_object();

			if ( $author instanceof \WP_User ) {
				return $this->maybe_append_pagination( get_author_posts_url( $author->ID ) );
			}

			return '';
		}

		// 5. Date archives.
		if ( is_date() ) {
			if ( is_day() ) {
				$canonical = get_day_link(
					(int) get_query_var( 'year' ),
					(int) get_query_var( 'monthnum' ),
					(int) get_query_var( 'day' )
				);
			} elseif ( is_month() ) {
				$canonical = get_month_link(
					(int) get_query_var( 'year' ),
					(int) get_query_var( 'monthnum' )
				);
			} else {
				$canonical = get_year_link( (int) get_query_var( 'year' ) );
			}

			return $this->maybe_append_pagination( $canonical );
		}

		// 6. Homepage / blog page.
		if ( is_front_page() || is_home() ) {
			return $this->maybe_append_pagination( home_url( '/' ) );
		}

		// 7. Search pages should not have canonical.
		if ( is_search() || is_404() ) {
			return '';
		}

		// 8. Singular posts/pages.
		if ( $post_id ) {
			$permalink = get_permalink( $post_id );

			if ( $permalink ) {
				return $this->maybe_append_pagination( $permalink );
			}
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

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

		// For the static front page.
		if ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
			$page_id = (int) get_option( 'page_on_front' );

			return $page_id > 0 ? $page_id : null;
		}

		// For the blog page (posts page).
		if ( is_home() && ! is_front_page() ) {
			$page_id = (int) get_option( 'page_for_posts' );

			return $page_id > 0 ? $page_id : null;
		}

		return null;
	}

	/**
	 * Get the title template for the current context.
	 *
	 * @param int|null $post_id Post ID for singular content.
	 * @return string Template string with variable placeholders.
	 */
	private function get_title_template( ?int $post_id ): string {
		// Homepage.
		if ( is_front_page() || ( is_home() && ! $post_id ) ) {
			return (string) $this->options->get( 'homepage_title', '%sitename% %sep% %tagline%' );
		}

		// Singular posts/pages.
		if ( $post_id ) {
			$post_type = get_post_type( $post_id );
			$template  = $this->options->get( "pt_{$post_type}_title", '' );

			if ( is_string( $template ) && '' !== $template ) {
				return $template;
			}

			// Generic default.
			return (string) $this->options->get( 'default_title', '%title% %sep% %sitename%' );
		}

		// Taxonomy archives.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$template = $this->options->get( "tax_{$term->taxonomy}_title", '' );

				if ( is_string( $template ) && '' !== $template ) {
					return $template;
				}
			}

			return '%term_title% %sep% %sitename%';
		}

		// Author archives.
		if ( is_author() ) {
			return '%author% %sep% %sitename%';
		}

		// Date archives.
		if ( is_date() ) {
			return '%date% %sep% %sitename%';
		}

		// Search.
		if ( is_search() ) {
			return '%search_query% %sep% %sitename%';
		}

		// Post type archives.
		if ( is_post_type_archive() ) {
			return '%pt_plural% %sep% %sitename%';
		}

		// 404.
		if ( is_404() ) {
			return '%404_title% %sep% %sitename%';
		}

		return (string) $this->options->get( 'default_title', '%title% %sep% %sitename%' );
	}

	/**
	 * Replace template variables with actual values.
	 *
	 * Supported variables:
	 *  %title%         - Post title.
	 *  %sitename%      - Site name.
	 *  %tagline%       - Site tagline.
	 *  %sep%           - Title separator from settings.
	 *  %excerpt%       - Post excerpt or auto-generated.
	 *  %term_title%    - Term/taxonomy name.
	 *  %author%        - Post author display name.
	 *  %date%          - Post publish date.
	 *  %search_query%  - Current search query.
	 *  %pt_plural%     - Post type plural label.
	 *  %404_title%     - "Page not found" text.
	 *  %page%          - Current page number (if paginated).
	 *
	 * @param string   $template The template string.
	 * @param int|null $post_id  Optional post ID for context.
	 * @return string The resolved string.
	 */
	private function replace_variables( string $template, ?int $post_id = null ): string {
		$post = $post_id ? get_post( $post_id ) : null;

		$separator = (string) $this->options->get( 'title_separator', "\u{2013}" );

		$variables = [
			'%sep%'      => $separator,
			'%sitename%' => get_bloginfo( 'name' ),
			'%tagline%'  => get_bloginfo( 'description' ),
		];

		// Post-specific variables.
		if ( $post instanceof \WP_Post ) {
			$variables['%title%']   = $post->post_title;
			$variables['%excerpt%'] = $this->get_auto_excerpt( $post );
			$variables['%date%']    = get_the_date( '', $post );
			$variables['%author%']  = get_the_author_meta( 'display_name', (int) $post->post_author );
		}

		// Taxonomy term variables.
		$queried_object = get_queried_object();

		if ( $queried_object instanceof \WP_Term ) {
			$variables['%term_title%'] = $queried_object->name;
		}

		// Author archive.
		if ( $queried_object instanceof \WP_User ) {
			$variables['%author%'] = $queried_object->display_name;
		}

		// Post type archive.
		if ( $queried_object instanceof \WP_Post_Type ) {
			$labels                  = get_post_type_labels( $queried_object );
			$variables['%pt_plural%'] = $labels->name ?? $queried_object->name;
		}

		// Search query.
		$variables['%search_query%'] = get_search_query();

		// 404 title.
		$variables['%404_title%'] = __( 'Page not found', 'seo-ai' );

		// Pagination.
		$paged = $this->get_current_page_number();

		if ( $paged > 1 ) {
			/* translators: %d: page number */
			$variables['%page%'] = sprintf( __( 'Page %d', 'seo-ai' ), $paged );
		} else {
			$variables['%page%'] = '';
		}

		$result = str_replace(
			array_keys( $variables ),
			array_values( $variables ),
			$template
		);

		// Clean up double separators and extra whitespace.
		$escaped_sep = preg_quote( $separator, '/' );
		$result      = (string) preg_replace(
			'/\s*' . $escaped_sep . '\s*' . $escaped_sep . '\s*/',
			" {$separator} ",
			$result
		);

		return trim( (string) preg_replace( '/\s+/', ' ', $result ) );
	}

	/**
	 * Sanitize a meta description string.
	 *
	 * Strips HTML tags, collapses whitespace to a single line, and trims
	 * to a maximum of 160 characters.
	 *
	 * @param string $text Raw description text.
	 * @return string Sanitized description.
	 */
	private function sanitize_description( string $text ): string {
		// Strip shortcodes.
		$text = strip_shortcodes( $text );

		// Strip HTML tags.
		$text = wp_strip_all_tags( $text );

		// Decode HTML entities.
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// Collapse whitespace to single spaces (single line).
		$text = (string) preg_replace( '/[\r\n\t]+/', ' ', $text );
		$text = (string) preg_replace( '/\s+/', ' ', $text );

		$text = trim( $text );

		// Trim to 160 characters on a word boundary.
		if ( mb_strlen( $text, 'UTF-8' ) > 160 ) {
			$text = mb_substr( $text, 0, 157, 'UTF-8' );

			// Try to break at the last space.
			$last_space = mb_strrpos( $text, ' ', 0, 'UTF-8' );

			if ( false !== $last_space && $last_space > 100 ) {
				$text = mb_substr( $text, 0, $last_space, 'UTF-8' );
			}

			$text .= '...';
		}

		return $text;
	}

	/**
	 * Build a robots directives string from an associative array of flags.
	 *
	 * @param array $flags Robots flags, e.g. ['noindex' => true, 'nofollow' => false].
	 * @return string Comma-separated directives string.
	 */
	private function build_robots_string( array $flags ): string {
		$directives = [];

		// Index / noindex.
		if ( ! empty( $flags['noindex'] ) ) {
			$directives[] = 'noindex';
		} else {
			$directives[] = 'index';
		}

		// Follow / nofollow.
		if ( ! empty( $flags['nofollow'] ) ) {
			$directives[] = 'nofollow';
		} else {
			$directives[] = 'follow';
		}

		// Additional directives.
		$additional = [ 'noarchive', 'nosnippet', 'noimageindex', 'max-snippet', 'max-image-preview', 'max-video-preview' ];

		foreach ( $additional as $directive ) {
			if ( ! empty( $flags[ $directive ] ) ) {
				if ( is_string( $flags[ $directive ] ) && $flags[ $directive ] !== '1' && $flags[ $directive ] !== 'true' ) {
					// Parameterized directive, e.g. max-snippet:-1.
					$directives[] = $directive . ':' . $flags[ $directive ];
				} else {
					$directives[] = $directive;
				}
			}
		}

		return implode( ', ', $directives );
	}

	/**
	 * Get an auto-generated excerpt from post content.
	 *
	 * Uses the manual excerpt if available, otherwise strips the content
	 * down to a plain-text snippet.
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Plain-text excerpt.
	 */
	private function get_auto_excerpt( \WP_Post $post ): string {
		if ( '' !== $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$content = (string) preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		if ( mb_strlen( $content, 'UTF-8' ) > 160 ) {
			$content = mb_substr( $content, 0, 157, 'UTF-8' );
			$last    = mb_strrpos( $content, ' ', 0, 'UTF-8' );

			if ( false !== $last && $last > 100 ) {
				$content = mb_substr( $content, 0, $last, 'UTF-8' );
			}

			$content .= '...';
		}

		return $content;
	}

	/**
	 * Append pagination to a URL if the current page is > 1.
	 *
	 * @param string $url The base URL.
	 * @return string URL with pagination appended if applicable.
	 */
	private function maybe_append_pagination( string $url ): string {
		$paged = $this->get_current_page_number();

		if ( $paged > 1 ) {
			global $wp_rewrite;

			if ( $wp_rewrite && $wp_rewrite->using_permalinks() ) {
				$url = trailingslashit( $url ) . 'page/' . $paged . '/';
			} else {
				$url = add_query_arg( 'paged', $paged, $url );
			}
		}

		return esc_url_raw( $url );
	}

	/**
	 * Get the current page number for pagination contexts.
	 *
	 * @return int Page number (1 if not paginated).
	 */
	private function get_current_page_number(): int {
		$paged = (int) get_query_var( 'paged', 0 );

		if ( $paged < 1 ) {
			$paged = (int) get_query_var( 'page', 0 );
		}

		return max( 1, $paged );
	}
}
