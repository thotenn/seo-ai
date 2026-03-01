<?php
/**
 * Image SEO Module.
 *
 * Automatically adds missing alt text and title attributes to images
 * in post content and attachment output, using intelligent generation
 * from filenames and configurable templates.
 *
 * @package SeoAi\Modules\Image_Seo
 * @since   1.0.0
 */

namespace SeoAi\Modules\Image_Seo;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Image_Seo
 *
 * @since 1.0.0
 */
final class Image_Seo {

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
		if ( $this->options->get( 'image_auto_alt', true ) || $this->options->get( 'image_auto_title', false ) ) {
			add_filter( 'the_content', [ $this, 'process_images' ], 99 );
		}

		if ( $this->options->get( 'image_auto_alt', true ) ) {
			add_filter( 'wp_get_attachment_image_attributes', [ $this, 'add_alt_text' ], 10, 2 );
		}

		// Auto-fill caption and description on upload.
		if ( $this->options->get( 'image_auto_caption', false ) || $this->options->get( 'image_auto_description', false ) ) {
			add_action( 'add_attachment', [ $this, 'fill_attachment_fields' ] );
		}
	}

	/**
	 * Process all images in post content to add missing alt and title attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content.
	 * @return string Modified content with improved image attributes.
	 */
	public function process_images( string $content ): string {
		if ( empty( $content ) ) {
			return $content;
		}

		// Match all <img> tags in the content.
		$pattern = '/<img\b[^>]*>/i';

		return preg_replace_callback( $pattern, [ $this, 'process_single_image' ], $content ) ?? $content;
	}

	/**
	 * Process a single <img> tag match to add missing attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $matches Regex match array.
	 * @return string The modified <img> tag.
	 */
	private function process_single_image( array $matches ): string {
		$img_tag = $matches[0];

		// Extract the src attribute to derive a filename.
		$src = $this->extract_attribute( $img_tag, 'src' );

		if ( empty( $src ) ) {
			return $img_tag;
		}

		$filename = $this->extract_filename_from_url( $src );

		// Handle missing alt attribute.
		if ( $this->options->get( 'image_auto_alt', true ) ) {
			$current_alt = $this->extract_attribute( $img_tag, 'alt' );

			if ( '' === $current_alt || null === $current_alt ) {
				$generated_alt = $this->generate_alt_from_filename( $filename );
				$img_tag       = $this->set_attribute( $img_tag, 'alt', $generated_alt );
			}
		}

		// Handle missing title attribute.
		if ( $this->options->get( 'image_auto_title', false ) ) {
			$current_title = $this->extract_attribute( $img_tag, 'title' );

			if ( '' === $current_title || null === $current_title ) {
				$generated_title = $this->generate_alt_from_filename( $filename );
				$img_tag         = $this->set_attribute( $img_tag, 'title', $generated_title );
			}
		}

		return $img_tag;
	}

	/**
	 * Add alt text to attachment image attributes when missing.
	 *
	 * Hooks into `wp_get_attachment_image_attributes` to ensure
	 * WordPress-generated attachment images have alt text.
	 *
	 * @since 1.0.0
	 *
	 * @param string[]     $attr       Image attributes array.
	 * @param \WP_Post     $attachment The attachment post object.
	 * @return string[] Modified attributes.
	 */
	public function add_alt_text( array $attr, \WP_Post $attachment ): array {
		// Only process if alt is empty.
		if ( ! empty( $attr['alt'] ) ) {
			return $attr;
		}

		$template = $this->options->get( 'image_alt_template', '%filename%' );

		// Try the attachment title first.
		if ( ! empty( $attachment->post_title ) && 'attachment' !== $attachment->post_title ) {
			$alt_text = $attachment->post_title;
		} else {
			// Fall back to generating from the attached file's name.
			$file = get_attached_file( $attachment->ID );

			if ( $file ) {
				$filename = pathinfo( $file, PATHINFO_FILENAME );
				$alt_text = $this->generate_alt_from_filename( $filename );
			} else {
				$alt_text = $attachment->post_title;
			}
		}

		// Apply the template.
		$attr['alt'] = $this->apply_template( $template, $alt_text, $attachment );

		return $attr;
	}

	/**
	 * Generate a human-readable alt text from a filename.
	 *
	 * Removes the file extension, replaces hyphens and underscores with spaces,
	 * removes size suffixes (e.g., "-300x200"), and applies title case.
	 *
	 * Examples:
	 *   "modern-office-desk.jpg"     => "Modern Office Desk"
	 *   "photo_2024_sunset.png"      => "Photo 2024 Sunset"
	 *   "hero-image-1920x1080.webp"  => "Hero Image"
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename The filename (with or without extension).
	 * @return string The generated alt text.
	 */
	public function generate_alt_from_filename( string $filename ): string {
		// Remove extension if present.
		$name = pathinfo( $filename, PATHINFO_FILENAME );

		if ( empty( $name ) ) {
			$name = $filename;
		}

		// Remove WordPress-style size suffixes (e.g., "-300x200", "-1024x768").
		$name = preg_replace( '/-\d+x\d+$/', '', $name );

		// Replace hyphens and underscores with spaces.
		$name = str_replace( [ '-', '_' ], ' ', $name );

		// Remove multiple consecutive spaces.
		$name = preg_replace( '/\s+/', ' ', trim( $name ) );

		// Apply case conversion based on setting.
		$case_setting = $this->options->get( 'image_alt_case', 'title' );

		$name = match ( $case_setting ) {
			'sentence'  => mb_strtoupper( mb_substr( $name, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_strtolower( mb_substr( $name, 1, null, 'UTF-8' ), 'UTF-8' ),
			'lower'     => mb_strtolower( $name, 'UTF-8' ),
			'upper'     => mb_strtoupper( $name, 'UTF-8' ),
			default     => mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' ), // title
		};

		/**
		 * Filters the auto-generated image alt text.
		 *
		 * @since 1.0.0
		 *
		 * @param string $name     The generated alt text.
		 * @param string $filename The original filename.
		 */
		return (string) apply_filters( 'seo_ai/generated_image_alt', $name, $filename );
	}

	/**
	 * Fill caption and description fields on attachment upload.
	 *
	 * @since 0.3.0
	 *
	 * @param int $attachment_id The attachment post ID.
	 * @return void
	 */
	public function fill_attachment_fields( int $attachment_id ): void {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment instanceof \WP_Post || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$file     = get_attached_file( $attachment_id );
		$filename = $file ? pathinfo( $file, PATHINFO_FILENAME ) : $attachment->post_title;
		$base_text = $this->generate_alt_from_filename( $filename );

		$updates = [];

		// Auto-fill caption (post_excerpt).
		if ( $this->options->get( 'image_auto_caption', false ) && empty( $attachment->post_excerpt ) ) {
			$caption_tpl = $this->options->get( 'image_caption_template', '%filename%' );
			$updates['post_excerpt'] = $this->apply_template( $caption_tpl, $base_text, $attachment );
		}

		// Auto-fill description (post_content).
		if ( $this->options->get( 'image_auto_description', false ) && empty( $attachment->post_content ) ) {
			$desc_tpl = $this->options->get( 'image_description_template', '%filename%' );
			$updates['post_content'] = $this->apply_template( $desc_tpl, $base_text, $attachment );
		}

		if ( ! empty( $updates ) ) {
			$updates['ID'] = $attachment_id;
			wp_update_post( $updates );
		}
	}

	/**
	 * Extract the filename from a URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The image URL.
	 * @return string The filename without extension.
	 */
	private function extract_filename_from_url( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( empty( $path ) ) {
			return '';
		}

		return pathinfo( $path, PATHINFO_FILENAME );
	}

	/**
	 * Extract an attribute value from an HTML tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag  The HTML tag string.
	 * @param string $attr The attribute name to extract.
	 * @return string|null The attribute value, or null if not present.
	 */
	private function extract_attribute( string $tag, string $attr ): ?string {
		// Match attribute with double quotes, single quotes, or no quotes.
		$pattern = '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))/i';

		if ( preg_match( $pattern, $tag, $matches ) ) {
			return $matches[1] ?? $matches[2] ?? $matches[3] ?? '';
		}

		// Check for attribute without value (e.g., <img alt>).
		if ( preg_match( '/\b' . preg_quote( $attr, '/' ) . '\b(?!\s*=)/i', $tag ) ) {
			return '';
		}

		return null;
	}

	/**
	 * Set or add an attribute on an HTML tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag   The HTML tag string.
	 * @param string $attr  The attribute name.
	 * @param string $value The attribute value.
	 * @return string The modified tag.
	 */
	private function set_attribute( string $tag, string $attr, string $value ): string {
		$escaped_value = esc_attr( $value );

		// If attribute already exists (even empty), replace it.
		$pattern = '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|\S+)/i';

		if ( preg_match( $pattern, $tag ) ) {
			return preg_replace(
				$pattern,
				$attr . '="' . $escaped_value . '"',
				$tag,
				1
			);
		}

		// Check for bare attribute (no value).
		$bare_pattern = '/\b' . preg_quote( $attr, '/' ) . '\b(?!\s*=)/i';

		if ( preg_match( $bare_pattern, $tag ) ) {
			return preg_replace(
				$bare_pattern,
				$attr . '="' . $escaped_value . '"',
				$tag,
				1
			);
		}

		// Attribute does not exist; add it before the closing >.
		return preg_replace(
			'/\/?\s*>$/',
			' ' . $attr . '="' . $escaped_value . '" />',
			$tag,
			1
		);
	}

	/**
	 * Apply the alt text template with available placeholders.
	 *
	 * Supported placeholders:
	 *   %filename%   - The cleaned-up filename
	 *   %title%      - The attachment post title
	 *   %caption%    - The attachment caption
	 *   %sitename%   - The site name
	 *
	 * @since 1.0.0
	 *
	 * @param string        $template   The alt text template string.
	 * @param string        $alt_text   The base alt text (from filename or title).
	 * @param \WP_Post|null $attachment The attachment post, if available.
	 * @return string The processed alt text.
	 */
	private function apply_template( string $template, string $alt_text, ?\WP_Post $attachment = null ): string {
		$replacements = [
			'%filename%' => $alt_text,
			'%sitename%' => get_bloginfo( 'name' ),
		];

		if ( $attachment instanceof \WP_Post ) {
			$replacements['%title%']   = $attachment->post_title;
			$replacements['%caption%'] = $attachment->post_excerpt;
		} else {
			$replacements['%title%']   = $alt_text;
			$replacements['%caption%'] = '';
		}

		$result = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$template
		);

		return trim( $result );
	}
}
