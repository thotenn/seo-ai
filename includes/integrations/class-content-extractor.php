<?php
declare(strict_types=1);

namespace SeoAi\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates content from multiple sources (ACF, Elementor, Divi)
 * for SEO analysis.
 *
 * @since 1.0.0
 */
class Content_Extractor {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'seo_ai/content_for_analysis', [ $this, 'aggregate_content' ], 10, 2 );
	}

	/**
	 * Append extracted content from ACF and page builders to the base post content.
	 *
	 * @param string $content Base post content.
	 * @param int    $post_id Post ID.
	 * @return string Combined content.
	 */
	public function aggregate_content( string $content, int $post_id ): string {
		if ( function_exists( 'get_fields' ) ) {
			$acf_content = $this->extract_acf_content( $post_id );
			if ( '' !== $acf_content ) {
				$content .= ' ' . $acf_content;
			}
		}

		if ( 0 < did_action( 'elementor/loaded' ) ) {
			$elementor_content = $this->extract_elementor_content( $post_id );
			if ( '' !== $elementor_content ) {
				$content .= ' ' . $elementor_content;
			}
		}

		if ( defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
			$divi_content = $this->extract_divi_content( $post_id );
			if ( '' !== $divi_content ) {
				$content .= ' ' . $divi_content;
			}
		}

		return $content;
	}

	/**
	 * Extract text content from all ACF fields for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Concatenated text from ACF fields.
	 */
	private function extract_acf_content( int $post_id ): string {
		$fields = get_fields( $post_id );

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return '';
		}

		$texts = [];
		$this->collect_acf_text( $fields, $texts );

		return implode( ' ', $texts );
	}

	/**
	 * Recursively collect text values from ACF field data.
	 *
	 * @param mixed    $value  Field value or nested structure.
	 * @param string[] $texts  Collected text fragments (passed by reference).
	 * @return void
	 */
	private function collect_acf_text( $value, array &$texts ): void {
		if ( $value instanceof \WP_Post ) {
			return;
		}

		if ( is_string( $value ) ) {
			$stripped = trim( wp_strip_all_tags( $value ) );
			if ( '' !== $stripped ) {
				$texts[] = $stripped;
			}
			return;
		}

		if ( is_array( $value ) ) {
			// Skip image arrays (attachment data).
			if ( isset( $value['url'], $value['alt'] ) ) {
				return;
			}

			foreach ( $value as $item ) {
				$this->collect_acf_text( $item, $texts );
			}
		}
	}

	/**
	 * Extract image data from all ACF fields for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array[] Array of images with 'url' and 'alt' keys.
	 */
	public function extract_acf_images( int $post_id ): array {
		if ( ! function_exists( 'get_fields' ) ) {
			return [];
		}

		$fields = get_fields( $post_id );

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return [];
		}

		$images = [];
		$this->collect_acf_images( $fields, $images );

		return $images;
	}

	/**
	 * Recursively collect image data from ACF field values.
	 *
	 * @param mixed   $value  Field value or nested structure.
	 * @param array[] $images Collected images (passed by reference).
	 * @return void
	 */
	private function collect_acf_images( $value, array &$images ): void {
		if ( is_int( $value ) ) {
			$url = wp_get_attachment_url( $value );
			if ( false !== $url ) {
				$alt = get_post_meta( $value, '_wp_attachment_image_alt', true );
				$images[] = [
					'url' => $url,
					'alt' => is_string( $alt ) ? $alt : '',
				];
			}
			return;
		}

		if ( is_array( $value ) ) {
			// Image array with url and alt keys.
			if ( isset( $value['url'], $value['alt'] ) ) {
				$images[] = [
					'url' => $value['url'],
					'alt' => is_string( $value['alt'] ) ? $value['alt'] : '',
				];
				return;
			}

			foreach ( $value as $item ) {
				$this->collect_acf_images( $item, $images );
			}
		}
	}

	/**
	 * Extract text content from Elementor page data.
	 *
	 * @param int $post_id Post ID.
	 * @return string Concatenated text from Elementor elements.
	 */
	private function extract_elementor_content( int $post_id ): string {
		$raw = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return '';
		}

		$texts = [];
		$this->collect_elementor_text( $data, $texts );

		return implode( ' ', $texts );
	}

	/**
	 * Recursively collect text from Elementor element settings.
	 *
	 * @param array    $elements Elementor elements array.
	 * @param string[] $texts    Collected text fragments (passed by reference).
	 * @return void
	 */
	private function collect_elementor_text( array $elements, array &$texts ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$keys = [ 'editor', 'title', 'description', 'text' ];
				foreach ( $keys as $key ) {
					if ( isset( $element['settings'][ $key ] ) && is_string( $element['settings'][ $key ] ) ) {
						$stripped = trim( wp_strip_all_tags( $element['settings'][ $key ] ) );
						if ( '' !== $stripped ) {
							$texts[] = $stripped;
						}
					}
				}
			}

			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_elementor_text( $element['elements'], $texts );
			}
		}
	}

	/**
	 * Extract text content from Divi Builder shortcodes in a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Extracted text from Divi content.
	 */
	private function extract_divi_content( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$raw_content = $post->post_content;

		if ( false === strpos( $raw_content, '[et_pb_' ) ) {
			return '';
		}

		$rendered = do_shortcode( $raw_content );
		$text     = trim( wp_strip_all_tags( $rendered ) );

		return $text;
	}
}
