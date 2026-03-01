<?php
namespace SeoAi\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Utility helpers for common SEO AI operations.
 *
 * Provides static methods for text manipulation, content extraction,
 * and template variable replacement used throughout the plugin.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Strip HTML tags but keep the text content.
	 *
	 * Unlike PHP's strip_tags(), this also normalizes whitespace
	 * and decodes HTML entities for a clean plain-text result.
	 *
	 * @param string $html The HTML string to process.
	 * @return string
	 */
	public static function strip_tags_content( string $html ): string {
		// Remove script and style element contents entirely.
		$html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $html );

		// Strip remaining tags.
		$text = wp_strip_all_tags( $html, true );

		// Decode HTML entities.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Truncate text at a word boundary.
	 *
	 * @param string $text   The text to truncate.
	 * @param int    $length Maximum character length.
	 * @param string $suffix Suffix to append when truncated.
	 * @return string
	 */
	public static function truncate( string $text, int $length = 160, string $suffix = '...' ): string {
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		// Cut to the maximum length minus the suffix.
		$truncated = mb_substr( $text, 0, $length - mb_strlen( $suffix ) );

		// Find the last word boundary (space, tab, newline).
		$last_space = mb_strrpos( $truncated, ' ' );

		if ( false !== $last_space && $last_space > ( $length * 0.5 ) ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}

		return rtrim( $truncated ) . $suffix;
	}

	/**
	 * Count the number of words in text.
	 *
	 * Handles HTML content by stripping tags first. Uses a Unicode-aware
	 * word splitting approach for accurate multilingual counting.
	 *
	 * @param string $text The text to count words in (may contain HTML).
	 * @return int
	 */
	public static function count_words( string $text ): int {
		$text = self::strip_tags_content( $text );

		if ( '' === $text ) {
			return 0;
		}

		// Split on whitespace and filter empty strings.
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $words ) ? count( $words ) : 0;
	}

	/**
	 * Get clean post content without shortcodes rendered.
	 *
	 * Strips shortcode tags (but keeps their inner content where appropriate),
	 * removes block markup, and returns plain text.
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public static function get_post_content( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$content = $post->post_content;

		// Strip shortcode tags but keep inner content.
		$content = strip_shortcodes( $content );

		// Remove Gutenberg block comments.
		$content = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', '', $content );

		// Strip HTML tags.
		$content = self::strip_tags_content( $content );

		return $content;
	}

	/**
	 * Get an excerpt for a post, using the custom excerpt if available,
	 * or generating one from the content.
	 *
	 * @param int $post_id The post ID.
	 * @param int $length  Maximum character length for generated excerpts.
	 * @return string
	 */
	public static function get_excerpt( int $post_id, int $length = 160 ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		// Use the manual excerpt if available.
		if ( ! empty( $post->post_excerpt ) ) {
			$excerpt = self::strip_tags_content( $post->post_excerpt );
			return self::truncate( $excerpt, $length, '' );
		}

		// Generate from content.
		$content = self::get_post_content( $post_id );

		if ( '' === $content ) {
			return '';
		}

		return self::truncate( $content, $length, '' );
	}

	/**
	 * Sanitize text for use in meta tags.
	 *
	 * Removes HTML, ensures single-line output, and normalizes whitespace.
	 *
	 * @param string $text The text to sanitize.
	 * @return string
	 */
	public static function sanitize_meta_text( string $text ): string {
		// Strip all HTML tags.
		$text = self::strip_tags_content( $text );

		// Remove line breaks to ensure single-line output.
		$text = str_replace( [ "\r\n", "\r", "\n" ], ' ', $text );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		// Remove any remaining special characters that could break meta tags.
		$text = str_replace( [ '"', "\t" ], [ '&quot;', ' ' ], $text );

		return trim( $text );
	}

	/**
	 * Get the primary category for a post.
	 *
	 * Returns the first assigned category, or falls back to the default
	 * WordPress category if none are explicitly assigned.
	 *
	 * @param int $post_id The post ID.
	 * @return \WP_Term|null The primary category term, or null if none found.
	 */
	public static function get_primary_category( int $post_id ): ?\WP_Term {
		/**
		 * Filters the primary category for a post.
		 *
		 * Allows other plugins or modules to define a primary category
		 * (e.g., through a UI selector in the metabox).
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Term|null $primary_category The primary category, or null.
		 * @param int           $post_id          The post ID.
		 */
		$primary = apply_filters( 'seo_ai/primary_category', null, $post_id );

		if ( $primary instanceof \WP_Term ) {
			return $primary;
		}

		$categories = get_the_category( $post_id );

		if ( empty( $categories ) || ! is_array( $categories ) ) {
			return null;
		}

		return $categories[0];
	}

	/**
	 * Replace template variables in a string with actual post/site data.
	 *
	 * Supported variables:
	 * - %title%       - Post title
	 * - %sitename%    - Site name
	 * - %sep%         - Title separator (from settings)
	 * - %excerpt%     - Post excerpt
	 * - %category%    - Primary category name
	 * - %tag%         - First post tag
	 * - %date%        - Post published date
	 * - %author%      - Post author display name
	 * - %page%        - Current page number
	 * - %term_title%  - Term/taxonomy title (for archive pages)
	 * - %tagline%     - Site tagline/description
	 *
	 * @param string   $template The template string containing variables.
	 * @param int|null $post_id  The post ID for context. Can be null for non-post pages.
	 * @return string
	 */
	public static function replace_variables( string $template, ?int $post_id = null ): string {
		$options   = Options::instance();
		$separator = $options->get( 'title_separator', "\u{2013}" );

		// Site-level replacements (always available).
		$replacements = [
			'%sitename%' => get_bloginfo( 'name' ),
			'%tagline%'  => get_bloginfo( 'description' ),
			'%sep%'      => $separator,
			'%page%'     => self::get_page_number(),
		];

		// Post-level replacements.
		if ( null !== $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				$replacements['%title%']   = get_the_title( $post );
				$replacements['%excerpt%'] = self::get_excerpt( $post_id, 160 );
				$replacements['%date%']    = get_the_date( '', $post );

				// Author.
				$author = get_userdata( $post->post_author );
				$replacements['%author%'] = $author ? $author->display_name : '';

				// Primary category.
				$category = self::get_primary_category( $post_id );
				$replacements['%category%'] = $category ? $category->name : '';

				// First tag.
				$tags = get_the_tags( $post_id );
				$replacements['%tag%'] = ( $tags && ! is_wp_error( $tags ) ) ? $tags[0]->name : '';
			}
		}

		// Term title for taxonomy archives.
		$replacements['%term_title%'] = self::get_current_term_title();

		$result = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$template
		);

		// Clean up double separators and extra spaces from empty replacements.
		$result = preg_replace( '/\s+/', ' ', $result );
		$result = trim( $result );

		// Remove leading/trailing separators.
		$result = trim( $result, " \t\n\r\0\x0B" . $separator );
		$result = trim( $result );

		return $result;
	}

	/**
	 * Get the current page number for paginated content.
	 *
	 * @return string The page number as a string, or empty string for page 1.
	 */
	private static function get_page_number(): string {
		$page = get_query_var( 'paged', 0 );

		if ( 0 === $page ) {
			$page = get_query_var( 'page', 0 );
		}

		if ( $page > 1 ) {
			/* translators: %d: page number */
			return sprintf( __( 'Page %d', 'seo-ai' ), $page );
		}

		return '';
	}

	/**
	 * Get the current term title for taxonomy archives.
	 *
	 * @return string The term name, or empty string if not on a term archive.
	 */
	private static function get_current_term_title(): string {
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return '';
		}

		$term = get_queried_object();

		if ( $term instanceof \WP_Term ) {
			return $term->name;
		}

		return '';
	}
}
