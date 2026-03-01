<?php
/**
 * Content Analyzer.
 *
 * Main SEO content analysis engine that orchestrates all on-page SEO checks
 * and readability analysis for a given post.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Class Analyzer
 *
 * Runs a comprehensive suite of SEO and readability checks against a post's
 * content and metadata, returning a structured array of scored results.
 *
 * @since 1.0.0
 */
final class Analyzer {

	/**
	 * Post meta key prefix used by the plugin.
	 *
	 * @var string
	 */
	private const META_PREFIX = '_seo_ai_';

	/**
	 * Readability analyser instance.
	 *
	 * @var Readability
	 */
	private Readability $readability;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->readability = new Readability();
	}

	/**
	 * Run a full SEO and readability analysis for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id Post ID to analyse.
	 * @param array $data    Optional override data. Supported keys:
	 *                       - 'title'       (string) SEO title.
	 *                       - 'content'     (string) Post content (HTML).
	 *                       - 'description' (string) Meta description.
	 *                       - 'keyword'     (string) Focus keyword.
	 *                       - 'url'         (string) Post URL / slug.
	 *                       - 'keywords'    (array)  Additional keywords.
	 *
	 * @return array {
	 *     @type array $seo         SEO analysis results.
	 *     @type array $readability Readability analysis results.
	 * }
	 */
	public function analyze( int $post_id, array $data = [] ): array {
		$data = $this->resolve_data( $post_id, $data );

		$title       = $data['title'];
		$content     = $data['content'];
		$description = $data['description'];
		$keyword     = $data['keyword'];
		$url         = $data['url'];

		// Check if this is cornerstone content for stricter thresholds.
		$is_cornerstone = '1' === (string) get_post_meta( $post_id, self::META_PREFIX . 'cornerstone', true );

		// ----- SEO Checks -----
		$seo_checks = [];

		if ( '' !== $keyword ) {
			$seo_checks[] = $this->check_keyword_in_title( $title, $keyword );
			$seo_checks[] = $this->check_keyword_in_description( $description, $keyword );
			$seo_checks[] = $this->check_keyword_in_url( $url, $keyword );
			$seo_checks[] = $this->check_keyword_in_first_paragraph( $content, $keyword );
			$seo_checks[] = $this->check_keyword_in_headings( $content, $keyword );
			$seo_checks[] = $this->check_keyword_density( $content, $keyword );
		}

		$seo_checks[] = $this->check_title_length( $title );
		$seo_checks[] = $this->check_description_length( $description );
		$seo_checks[] = $this->check_content_length( $content, $is_cornerstone );
		$seo_checks[] = $this->check_internal_links( $content, $post_id, $is_cornerstone );
		$seo_checks[] = $this->check_external_links( $content, $is_cornerstone );
		$seo_checks[] = $this->check_image_alt( $content, $keyword );

		$seo_score = Score::calculate( $seo_checks );

		// ----- Readability -----
		$readability = $this->readability->analyze( $content );

		return [
			'seo'         => [
				'score'  => $seo_score,
				'checks' => $seo_checks,
			],
			'readability' => $readability,
		];
	}

	// =========================================================================
	// SEO Checks
	// =========================================================================

	/**
	 * Check whether the focus keyword appears in the SEO title.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title   SEO title.
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	public function check_keyword_in_title( string $title, string $keyword ): array {
		$found = $this->get_keyword_count( $title, $keyword ) > 0;

		if ( $found ) {
			return $this->build_check(
				'keyword_in_title',
				__( 'Keyword in Title', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the SEO title.', 'seo-ai' ),
				15,
				100
			);
		}

		return $this->build_check(
			'keyword_in_title',
			__( 'Keyword in Title', 'seo-ai' ),
			'error',
			__( 'The focus keyword does not appear in the SEO title. Add it for better rankings.', 'seo-ai' ),
			15,
			0
		);
	}

	/**
	 * Check whether the focus keyword appears in the meta description.
	 *
	 * @since 1.0.0
	 *
	 * @param string $desc    Meta description.
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	public function check_keyword_in_description( string $desc, string $keyword ): array {
		if ( '' === trim( $desc ) ) {
			return $this->build_check(
				'keyword_in_description',
				__( 'Keyword in Meta Description', 'seo-ai' ),
				'error',
				__( 'No meta description set. Add one that includes the focus keyword.', 'seo-ai' ),
				10,
				0
			);
		}

		$found = $this->get_keyword_count( $desc, $keyword ) > 0;

		if ( $found ) {
			return $this->build_check(
				'keyword_in_description',
				__( 'Keyword in Meta Description', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the meta description.', 'seo-ai' ),
				10,
				100
			);
		}

		return $this->build_check(
			'keyword_in_description',
			__( 'Keyword in Meta Description', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in the meta description. Include it for better click-through rates.', 'seo-ai' ),
			10,
			30
		);
	}

	/**
	 * Check whether the focus keyword appears in the URL slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url     Post URL or slug.
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	public function check_keyword_in_url( string $url, string $keyword ): array {
		$slug          = basename( untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) ?: $url ) );
		$slug_clean    = str_replace( '-', ' ', $slug );
		$keyword_lower = mb_strtolower( trim( $keyword ) );

		$found = mb_strpos( mb_strtolower( $slug_clean ), $keyword_lower ) !== false;

		if ( $found ) {
			return $this->build_check(
				'keyword_in_url',
				__( 'Keyword in URL', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the URL slug.', 'seo-ai' ),
				10,
				100
			);
		}

		return $this->build_check(
			'keyword_in_url',
			__( 'Keyword in URL', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in the URL slug. Consider updating the permalink.', 'seo-ai' ),
			10,
			30
		);
	}

	/**
	 * Check whether the focus keyword appears in the first 100 words.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content (HTML).
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	public function check_keyword_in_first_paragraph( string $content, string $keyword ): array {
		$text  = wp_strip_all_tags( $content );
		$words = preg_split( '/\s+/', $text, 101, PREG_SPLIT_NO_EMPTY );

		if ( empty( $words ) ) {
			return $this->build_check(
				'keyword_in_first_paragraph',
				__( 'Keyword in Introduction', 'seo-ai' ),
				'error',
				__( 'No content found.', 'seo-ai' ),
				10,
				0
			);
		}

		$first_100 = implode( ' ', array_slice( $words, 0, 100 ) );
		$found     = $this->get_keyword_count( $first_100, $keyword ) > 0;

		if ( $found ) {
			return $this->build_check(
				'keyword_in_first_paragraph',
				__( 'Keyword in Introduction', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the first 100 words.', 'seo-ai' ),
				10,
				100
			);
		}

		return $this->build_check(
			'keyword_in_first_paragraph',
			__( 'Keyword in Introduction', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in the first 100 words. Introduce it earlier in the content.', 'seo-ai' ),
			10,
			30
		);
	}

	/**
	 * Check whether the focus keyword appears in at least one subheading (H2-H6).
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content (HTML).
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	public function check_keyword_in_headings( string $content, string $keyword ): array {
		$headings = $this->extract_headings( $content );

		// Filter to H2-H6 only.
		$subheadings = array_filter( $headings, function ( array $h ): bool {
			return in_array( $h['level'], [ 2, 3, 4, 5, 6 ], true );
		} );

		if ( empty( $subheadings ) ) {
			return $this->build_check(
				'keyword_in_headings',
				__( 'Keyword in Subheadings', 'seo-ai' ),
				'warning',
				__( 'No subheadings (H2-H6) found. Add headings that include the focus keyword.', 'seo-ai' ),
				8,
				30
			);
		}

		foreach ( $subheadings as $heading ) {
			if ( $this->get_keyword_count( $heading['text'], $keyword ) > 0 ) {
				return $this->build_check(
					'keyword_in_headings',
					__( 'Keyword in Subheadings', 'seo-ai' ),
					'good',
					__( 'The focus keyword appears in at least one subheading.', 'seo-ai' ),
					8,
					100
				);
			}
		}

		return $this->build_check(
			'keyword_in_headings',
			__( 'Keyword in Subheadings', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in any subheading. Use it in at least one H2-H6 heading.', 'seo-ai' ),
			8,
			30
		);
	}

	/**
	 * Check keyword density in the content.
	 *
	 * Ideal range is 1-3%.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content (HTML).
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	public function check_keyword_density( string $content, string $keyword ): array {
		$text       = wp_strip_all_tags( $content );
		$word_count = str_word_count( $text );

		if ( 0 === $word_count ) {
			return $this->build_check(
				'keyword_density',
				__( 'Keyword Density', 'seo-ai' ),
				'error',
				__( 'No content to analyse keyword density.', 'seo-ai' ),
				10,
				0
			);
		}

		$keyword_count = $this->get_keyword_count( $text, $keyword );
		$keyword_words = str_word_count( $keyword );
		$density       = ( $keyword_count * $keyword_words ) / $word_count * 100;
		$density       = round( $density, 2 );

		if ( $density >= 1 && $density <= 3 ) {
			return $this->build_check(
				'keyword_density',
				__( 'Keyword Density', 'seo-ai' ),
				'good',
				/* translators: %s: density percentage */
				sprintf( __( 'Keyword density is %.2f%%, which is within the ideal range (1-3%%).', 'seo-ai' ), $density ),
				10,
				100
			);
		}

		if ( $density > 0 && $density < 1 ) {
			return $this->build_check(
				'keyword_density',
				__( 'Keyword Density', 'seo-ai' ),
				'warning',
				/* translators: %s: density percentage */
				sprintf( __( 'Keyword density is %.2f%%, which is below the recommended 1%%. Use the keyword a few more times.', 'seo-ai' ), $density ),
				10,
				50
			);
		}

		if ( $density > 3 ) {
			return $this->build_check(
				'keyword_density',
				__( 'Keyword Density', 'seo-ai' ),
				'warning',
				/* translators: %s: density percentage */
				sprintf( __( 'Keyword density is %.2f%%, which exceeds 3%%. Reduce usage to avoid keyword stuffing.', 'seo-ai' ), $density ),
				10,
				40
			);
		}

		// density === 0
		return $this->build_check(
			'keyword_density',
			__( 'Keyword Density', 'seo-ai' ),
			'error',
			__( 'The focus keyword was not found in the content. Include it naturally throughout the text.', 'seo-ai' ),
			10,
			0
		);
	}

	/**
	 * Check whether the SEO title length is within the ideal range.
	 *
	 * Ideal: 50-60 characters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title SEO title.
	 *
	 * @return array Check result.
	 */
	public function check_title_length( string $title ): array {
		$length = mb_strlen( trim( $title ) );

		if ( 0 === $length ) {
			return $this->build_check(
				'title_length',
				__( 'SEO Title Length', 'seo-ai' ),
				'error',
				__( 'No SEO title set. Add a title between 50 and 60 characters.', 'seo-ai' ),
				8,
				0
			);
		}

		if ( $length >= 50 && $length <= 60 ) {
			return $this->build_check(
				'title_length',
				__( 'SEO Title Length', 'seo-ai' ),
				'good',
				/* translators: %d: character count */
				sprintf( __( 'SEO title is %d characters, within the ideal 50-60 range.', 'seo-ai' ), $length ),
				8,
				100
			);
		}

		if ( $length >= 30 && $length < 50 ) {
			return $this->build_check(
				'title_length',
				__( 'SEO Title Length', 'seo-ai' ),
				'warning',
				/* translators: %d: character count */
				sprintf( __( 'SEO title is %d characters. Aim for 50-60 characters for optimal display.', 'seo-ai' ), $length ),
				8,
				60
			);
		}

		if ( $length > 60 && $length <= 70 ) {
			return $this->build_check(
				'title_length',
				__( 'SEO Title Length', 'seo-ai' ),
				'warning',
				/* translators: %d: character count */
				sprintf( __( 'SEO title is %d characters. It may be truncated in search results. Aim for 50-60 characters.', 'seo-ai' ), $length ),
				8,
				60
			);
		}

		return $this->build_check(
			'title_length',
			__( 'SEO Title Length', 'seo-ai' ),
			'error',
			/* translators: %d: character count */
			sprintf( __( 'SEO title is %d characters, which is outside the recommended range. Adjust to 50-60 characters.', 'seo-ai' ), $length ),
			8,
			20
		);
	}

	/**
	 * Check whether the meta description length is within the ideal range.
	 *
	 * Ideal: 120-160 characters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $desc Meta description.
	 *
	 * @return array Check result.
	 */
	public function check_description_length( string $desc ): array {
		$length = mb_strlen( trim( $desc ) );

		if ( 0 === $length ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'error',
				__( 'No meta description set. Add one between 120 and 160 characters.', 'seo-ai' ),
				8,
				0
			);
		}

		if ( $length >= 120 && $length <= 160 ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'good',
				/* translators: %d: character count */
				sprintf( __( 'Meta description is %d characters, within the ideal 120-160 range.', 'seo-ai' ), $length ),
				8,
				100
			);
		}

		if ( $length >= 70 && $length < 120 ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'warning',
				/* translators: %d: character count */
				sprintf( __( 'Meta description is %d characters. Expand it to 120-160 characters for better visibility.', 'seo-ai' ), $length ),
				8,
				60
			);
		}

		if ( $length > 160 && $length <= 200 ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'warning',
				/* translators: %d: character count */
				sprintf( __( 'Meta description is %d characters. It may be truncated. Aim for 120-160 characters.', 'seo-ai' ), $length ),
				8,
				60
			);
		}

		return $this->build_check(
			'description_length',
			__( 'Meta Description Length', 'seo-ai' ),
			'error',
			/* translators: %d: character count */
			sprintf( __( 'Meta description is %d characters. Adjust to 120-160 characters for optimal results.', 'seo-ai' ), $length ),
			8,
			20
		);
	}

	/**
	 * Check whether the content meets the minimum word count.
	 *
	 * Minimum: 300 words (900 for cornerstone content).
	 *
	 * @since 1.0.0
	 *
	 * @param string $content        Post content (HTML).
	 * @param bool   $is_cornerstone Whether this is cornerstone content.
	 *
	 * @return array Check result.
	 */
	public function check_content_length( string $content, bool $is_cornerstone = false ): array {
		$text       = wp_strip_all_tags( $content );
		$word_count = str_word_count( $text );
		$min_words  = $is_cornerstone ? 900 : 300;
		$warn_words = $is_cornerstone ? 600 : 200;

		if ( $word_count >= $min_words ) {
			return $this->build_check(
				'content_length',
				__( 'Content Length', 'seo-ai' ),
				'good',
				/* translators: 1: word count, 2: minimum */
				sprintf( __( 'Content has %1$d words, which meets the minimum of %2$d words.', 'seo-ai' ), $word_count, $min_words ),
				7,
				100
			);
		}

		if ( $word_count >= $warn_words ) {
			return $this->build_check(
				'content_length',
				__( 'Content Length', 'seo-ai' ),
				'warning',
				/* translators: 1: word count, 2: minimum */
				sprintf( __( 'Content has %1$d words. Aim for at least %2$d words for better SEO.', 'seo-ai' ), $word_count, $min_words ),
				7,
				50
			);
		}

		return $this->build_check(
			'content_length',
			__( 'Content Length', 'seo-ai' ),
			'error',
			/* translators: 1: word count, 2: minimum */
			sprintf( __( 'Content has only %1$d words. Write at least %2$d words for meaningful SEO value.', 'seo-ai' ), $word_count, $min_words ),
			7,
			20
		);
	}

	/**
	 * Check for internal links in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content        Post content (HTML).
	 * @param int    $post_id        Current post ID (to exclude self-links).
	 * @param bool   $is_cornerstone Whether this is cornerstone content.
	 *
	 * @return array Check result.
	 */
	public function check_internal_links( string $content, int $post_id, bool $is_cornerstone = false ): array {
		$site_url = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
		$links    = $this->extract_links( $content, $site_url );

		// Exclude links pointing to the current post.
		$post_url = get_permalink( $post_id );
		$internal = array_filter( $links['internal'], function ( string $href ) use ( $post_url ): bool {
			return untrailingslashit( $href ) !== untrailingslashit( $post_url ?: '' );
		} );

		$count     = count( $internal );
		$min_links = $is_cornerstone ? 3 : 1;

		if ( $count >= $min_links ) {
			return $this->build_check(
				'internal_links',
				__( 'Internal Links', 'seo-ai' ),
				'good',
				/* translators: %d: link count */
				sprintf( _n( 'Found %d internal link.', 'Found %d internal links.', $count, 'seo-ai' ), $count ),
				7,
				100
			);
		}

		if ( $count > 0 && $is_cornerstone ) {
			return $this->build_check(
				'internal_links',
				__( 'Internal Links', 'seo-ai' ),
				'warning',
				/* translators: 1: link count, 2: minimum required */
				sprintf( __( 'Found %1$d internal links. Cornerstone content should have at least %2$d.', 'seo-ai' ), $count, $min_links ),
				7,
				50
			);
		}

		return $this->build_check(
			'internal_links',
			__( 'Internal Links', 'seo-ai' ),
			'error',
			__( 'No internal links found. Add links to other pages on your site to improve SEO.', 'seo-ai' ),
			7,
			0
		);
	}

	/**
	 * Check for external links in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content        Post content (HTML).
	 * @param bool   $is_cornerstone Whether this is cornerstone content.
	 *
	 * @return array Check result.
	 */
	public function check_external_links( string $content, bool $is_cornerstone = false ): array {
		$site_url = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
		$links    = $this->extract_links( $content, $site_url );
		$count    = count( $links['external'] );
		$min_ext  = $is_cornerstone ? 2 : 1;

		if ( $count >= $min_ext ) {
			return $this->build_check(
				'external_links',
				__( 'External Links', 'seo-ai' ),
				'good',
				/* translators: %d: link count */
				sprintf( _n( 'Found %d external link.', 'Found %d external links.', $count, 'seo-ai' ), $count ),
				4,
				100
			);
		}

		if ( $count > 0 && $is_cornerstone ) {
			return $this->build_check(
				'external_links',
				__( 'External Links', 'seo-ai' ),
				'warning',
				/* translators: 1: link count, 2: minimum required */
				sprintf( __( 'Found %1$d external link. Cornerstone content should have at least %2$d.', 'seo-ai' ), $count, $min_ext ),
				4,
				50
			);
		}

		return $this->build_check(
			'external_links',
			__( 'External Links', 'seo-ai' ),
			'warning',
			__( 'No external links found. Linking to authoritative sources can improve credibility and SEO.', 'seo-ai' ),
			4,
			30
		);
	}

	/**
	 * Check images for alt text and focus keyword in alt attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content (HTML).
	 * @param string $keyword Focus keyword (may be empty).
	 *
	 * @return array Check result.
	 */
	public function check_image_alt( string $content, string $keyword ): array {
		$images = $this->extract_images( $content );

		if ( empty( $images ) ) {
			return $this->build_check(
				'image_alt',
				__( 'Image Alt Text', 'seo-ai' ),
				'warning',
				__( 'No images found. Consider adding relevant images to improve engagement.', 'seo-ai' ),
				5,
				50
			);
		}

		$without_alt     = 0;
		$keyword_in_alt  = false;

		foreach ( $images as $image ) {
			if ( '' === trim( $image['alt'] ) ) {
				$without_alt++;
			} elseif ( '' !== $keyword && $this->get_keyword_count( $image['alt'], $keyword ) > 0 ) {
				$keyword_in_alt = true;
			}
		}

		$total = count( $images );

		// All images have alt text and keyword is in at least one.
		if ( 0 === $without_alt && ( $keyword_in_alt || '' === $keyword ) ) {
			return $this->build_check(
				'image_alt',
				__( 'Image Alt Text', 'seo-ai' ),
				'good',
				__( 'All images have alt text and the focus keyword appears in at least one.', 'seo-ai' ),
				5,
				100
			);
		}

		// All have alt text but keyword missing.
		if ( 0 === $without_alt && '' !== $keyword && ! $keyword_in_alt ) {
			return $this->build_check(
				'image_alt',
				__( 'Image Alt Text', 'seo-ai' ),
				'warning',
				__( 'All images have alt text, but the focus keyword is not in any alt attribute. Add it to at least one image.', 'seo-ai' ),
				5,
				60
			);
		}

		// Some images missing alt text.
		return $this->build_check(
			'image_alt',
			__( 'Image Alt Text', 'seo-ai' ),
			'error',
			/* translators: %1$d: images without alt, %2$d: total images */
			sprintf( __( '%1$d of %2$d images are missing alt text. Add descriptive alt attributes for accessibility and SEO.', 'seo-ai' ), $without_alt, $total ),
			5,
			20
		);
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Count keyword occurrences in text (case-insensitive, word-boundary aware).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text    The text to search.
	 * @param string $keyword The keyword to count.
	 *
	 * @return int Number of occurrences.
	 */
	public function get_keyword_count( string $text, string $keyword ): int {
		$keyword = trim( $keyword );

		if ( '' === $keyword ) {
			return 0;
		}

		$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/iu';
		$count   = preg_match_all( $pattern, $text );

		return $count ?: 0;
	}

	/**
	 * Extract all headings (H1-H6) from HTML content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML content.
	 *
	 * @return array Array of heading data: [ ['level' => int, 'text' => string], ... ]
	 */
	public function extract_headings( string $html ): array {
		$headings = [];

		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headings[] = [
					'level' => (int) $match[1],
					'text'  => wp_strip_all_tags( $match[2] ),
				];
			}
		}

		return $headings;
	}

	/**
	 * Extract and categorise links from HTML content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html     HTML content.
	 * @param string $site_url The site's hostname (e.g. 'example.com').
	 *
	 * @return array {
	 *     @type string[] $internal Internal link URLs.
	 *     @type string[] $external External link URLs.
	 * }
	 */
	public function extract_links( string $html, string $site_url ): array {
		$result = [
			'internal' => [],
			'external' => [],
		];

		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/is', $html, $matches ) ) {
			return $result;
		}

		$site_url = mb_strtolower( $site_url );

		foreach ( $matches[1] as $href ) {
			$href = trim( $href );

			// Skip anchors, javascript, mailto, tel, etc.
			if ( '' === $href
				|| str_starts_with( $href, '#' )
				|| str_starts_with( $href, 'javascript:' )
				|| str_starts_with( $href, 'mailto:' )
				|| str_starts_with( $href, 'tel:' )
			) {
				continue;
			}

			// Relative URLs are internal.
			if ( str_starts_with( $href, '/' ) && ! str_starts_with( $href, '//' ) ) {
				$result['internal'][] = $href;
				continue;
			}

			$host = wp_parse_url( $href, PHP_URL_HOST );

			if ( $host ) {
				$host = mb_strtolower( $host );

				if ( $host === $site_url || str_ends_with( $host, '.' . $site_url ) ) {
					$result['internal'][] = $href;
				} else {
					$result['external'][] = $href;
				}
			}
		}

		return $result;
	}

	/**
	 * Extract images and their alt text from HTML content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML content.
	 *
	 * @return array Array of image data: [ ['src' => string, 'alt' => string], ... ]
	 */
	public function extract_images( string $html ): array {
		$images = [];

		if ( ! preg_match_all( '/<img\s[^>]*>/is', $html, $img_tags ) ) {
			return $images;
		}

		foreach ( $img_tags[0] as $tag ) {
			$src = '';
			$alt = '';

			if ( preg_match( '/src=["\']([^"\']+)["\']/i', $tag, $src_match ) ) {
				$src = $src_match[1];
			}

			if ( preg_match( '/alt=["\']([^"\']*?)["\']/i', $tag, $alt_match ) ) {
				$alt = $alt_match[1];
			}

			$images[] = [
				'src' => $src,
				'alt' => $alt,
			];
		}

		return $images;
	}

	// =========================================================================
	// Private Helpers
	// =========================================================================

	/**
	 * Resolve analysis data, falling back to post and meta values when needed.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Caller-supplied data overrides.
	 *
	 * @return array Resolved data with all required keys present.
	 */
	private function resolve_data( int $post_id, array $data ): array {
		$post = get_post( $post_id );

		$defaults = [
			'title'       => '',
			'content'     => '',
			'description' => '',
			'keyword'     => '',
			'url'         => '',
			'keywords'    => [],
		];

		// If a key is not supplied in $data, load from post / post meta.
		if ( ! isset( $data['title'] ) ) {
			$meta_title = get_post_meta( $post_id, self::META_PREFIX . 'title', true );
			$data['title'] = $meta_title ?: ( $post ? $post->post_title : '' );
		}

		if ( ! isset( $data['content'] ) ) {
			$data['content'] = $post ? $post->post_content : '';
		}

		if ( ! isset( $data['description'] ) ) {
			$data['description'] = (string) get_post_meta( $post_id, self::META_PREFIX . 'description', true );
		}

		if ( ! isset( $data['keyword'] ) ) {
			$data['keyword'] = (string) get_post_meta( $post_id, self::META_PREFIX . 'keyword', true );
		}

		if ( ! isset( $data['url'] ) ) {
			$data['url'] = get_permalink( $post_id ) ?: '';
		}

		if ( ! isset( $data['keywords'] ) ) {
			$raw = get_post_meta( $post_id, self::META_PREFIX . 'keywords', true );
			$data['keywords'] = is_array( $raw ) ? $raw : [];
		}

		return wp_parse_args( $data, $defaults );
	}

	/**
	 * Build a standardised check result array.
	 *
	 * @param string $id      Unique check identifier.
	 * @param string $label   Human-readable check name.
	 * @param string $status  'good', 'warning', or 'error'.
	 * @param string $message Detailed message for the user.
	 * @param int    $weight  Importance weight for scoring.
	 * @param int    $score   Raw score (0-100) for this check.
	 *
	 * @return array Check result.
	 */
	private function build_check(
		string $id,
		string $label,
		string $status,
		string $message,
		int $weight,
		int $score
	): array {
		return [
			'id'      => $id,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
			'weight'  => $weight,
			'score'   => $score,
		];
	}
}
