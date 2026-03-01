<?php
/**
 * Competitor Analyzer.
 *
 * Fetches and analyses competitor pages to provide side-by-side SEO
 * comparisons against the user's own content.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Class Competitor_Analyzer
 *
 * Analyses a competitor URL's on-page SEO signals and compares them against
 * a local post's stored analysis data to surface actionable suggestions.
 *
 * @since 1.0.0
 */
final class Competitor_Analyzer {

	/**
	 * Transient prefix for cached competitor analyses.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'seo_ai_competitor_';

	/**
	 * Transient prefix for rate-limiting repeated fetches.
	 *
	 * @var string
	 */
	private const RATE_LIMIT_PREFIX = 'seo_ai_competitor_rl_';

	/**
	 * Cache TTL in seconds (24 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Minimum interval between fetches of the same URL in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const RATE_LIMIT_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Post meta key for the stored SEO score data.
	 *
	 * @var string
	 */
	private const SEO_SCORE_META_KEY = '_seo_ai_seo_score';

	/**
	 * Register hooks.
	 *
	 * Intentionally empty — hook registration is handled by the REST controller.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// No-op. Called by the module loader; REST controller handles routing.
	}

	/**
	 * Analyse a competitor URL and extract on-page SEO signals.
	 *
	 * Results are cached in a transient for 24 hours. Repeated requests for
	 * the same URL within 5 minutes are rejected with a WP_Error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url           The competitor URL to analyse.
	 * @param string $focus_keyword Optional focus keyword for density calculation.
	 *
	 * @return array|\WP_Error Analysis data array on success, WP_Error on failure.
	 */
	public function analyze_url( string $url, string $focus_keyword = '' ) {
		$url = esc_url_raw( $url );

		if ( '' === $url ) {
			return new \WP_Error(
				'seo_ai_invalid_url',
				__( 'A valid URL is required.', 'seo-ai' ),
				[ 'status' => 400 ]
			);
		}

		$url_hash       = md5( $url );
		$cache_key      = self::CACHE_PREFIX . $url_hash;
		$rate_limit_key = self::RATE_LIMIT_PREFIX . $url_hash;

		// Check rate limit.
		$last_fetch = get_transient( $rate_limit_key );

		if ( false !== $last_fetch ) {
			return new \WP_Error(
				'seo_ai_rate_limited',
				__( 'This URL was analysed recently. Please wait 5 minutes before re-analysing.', 'seo-ai' ),
				[ 'status' => 429 ]
			);
		}

		// Check cache.
		$cached = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Fetch the URL.
		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'SeoAi/1.0 (WordPress SEO Plugin)',
			'sslverify'  => false,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'seo_ai_fetch_failed',
				sprintf(
					/* translators: %s: error message from wp_remote_get */
					__( 'Failed to fetch URL: %s', 'seo-ai' ),
					$response->get_error_message()
				),
				[ 'status' => 502 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 400 ) {
			return new \WP_Error(
				'seo_ai_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'The URL returned HTTP status %d.', 'seo-ai' ),
					$status_code
				),
				[ 'status' => 502 ]
			);
		}

		$html = wp_remote_retrieve_body( $response );

		if ( '' === trim( $html ) ) {
			return new \WP_Error(
				'seo_ai_empty_response',
				__( 'The URL returned an empty response body.', 'seo-ai' ),
				[ 'status' => 502 ]
			);
		}

		$analysis = $this->parse_html( $html, $url, $focus_keyword );

		// Store rate limit marker.
		set_transient( $rate_limit_key, time(), self::RATE_LIMIT_TTL );

		// Cache the analysis.
		set_transient( $cache_key, $analysis, self::CACHE_TTL );

		return $analysis;
	}

	/**
	 * Compare a local post against a competitor URL.
	 *
	 * Runs the competitor analysis and loads the post's stored SEO score data
	 * from post meta to produce a side-by-side comparison with suggestions.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id        The local post ID.
	 * @param string $competitor_url The competitor URL to compare against.
	 * @param string $focus_keyword  Optional focus keyword for density calculation.
	 *
	 * @return array|\WP_Error Comparison array with 'own', 'competitor', and 'suggestions' keys.
	 */
	public function compare( int $post_id, string $competitor_url, string $focus_keyword = '' ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'seo_ai_post_not_found',
				__( 'The specified post was not found.', 'seo-ai' ),
				[ 'status' => 404 ]
			);
		}

		// Analyse the competitor.
		$competitor = $this->analyze_url( $competitor_url, $focus_keyword );

		if ( is_wp_error( $competitor ) ) {
			return $competitor;
		}

		// Load own post's stored SEO score data.
		$own_raw = get_post_meta( $post_id, self::SEO_SCORE_META_KEY, true );
		$own     = is_array( $own_raw ) ? $own_raw : [];

		// Build the own analysis summary from stored data and live post data.
		$own_summary = $this->build_own_summary( $post, $own );

		// Generate suggestions by comparing both data sets.
		$suggestions = $this->generate_suggestions( $own_summary, $competitor );

		return [
			'own'         => $own_summary,
			'competitor'  => $competitor,
			'suggestions' => $suggestions,
		];
	}

	// =========================================================================
	// HTML Parsing
	// =========================================================================

	/**
	 * Parse HTML and extract on-page SEO signals.
	 *
	 * Uses a combination of DOMDocument and regex to extract structured data
	 * from the competitor's HTML response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html          Raw HTML response body.
	 * @param string $url           The source URL (used for link classification).
	 * @param string $focus_keyword Optional focus keyword for density calculation.
	 *
	 * @return array Extracted SEO signals.
	 */
	private function parse_html( string $html, string $url, string $focus_keyword ): array {
		$title            = $this->extract_title( $html );
		$meta_description = $this->extract_meta_description( $html );
		$headings         = $this->extract_headings( $html );
		$body_text        = $this->extract_body_text( $html );
		$word_count       = str_word_count( $body_text );
		$links            = $this->extract_links( $html, $url );
		$images           = $this->extract_images( $html );

		$images_with_alt = 0;
		foreach ( $images as $image ) {
			if ( '' !== trim( $image['alt'] ) ) {
				$images_with_alt++;
			}
		}

		$heading_counts = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$heading_counts[ 'h' . $i ] = 0;
		}
		foreach ( $headings as $heading ) {
			$key = 'h' . $heading['level'];
			$heading_counts[ $key ]++;
		}

		$analysis = [
			'url'              => $url,
			'title'            => $title,
			'meta_description' => $meta_description,
			'headings'         => [
				'counts' => $heading_counts,
				'items'  => $headings,
			],
			'word_count'       => $word_count,
			'links'            => [
				'internal_count' => count( $links['internal'] ),
				'external_count' => count( $links['external'] ),
			],
			'images'           => [
				'total'    => count( $images ),
				'with_alt' => $images_with_alt,
			],
		];

		// Keyword density if a focus keyword was provided.
		if ( '' !== trim( $focus_keyword ) ) {
			$analysis['keyword_density'] = $this->calculate_keyword_density(
				$body_text,
				$focus_keyword
			);
		}

		return $analysis;
	}

	/**
	 * Extract the page title from the <title> tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string Page title or empty string.
	 */
	private function extract_title( string $html ): string {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			return trim( html_entity_decode( wp_strip_all_tags( $matches[1] ), ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Extract the meta description from a <meta name="description"> tag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string Meta description or empty string.
	 */
	private function extract_meta_description( string $html ): string {
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/is', $html, $matches ) ) {
			return trim( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) );
		}

		// Handle reversed attribute order: content before name.
		if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\'][^>]*>/is', $html, $matches ) ) {
			return trim( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Extract all headings (H1-H6) from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return array Array of heading data: [ ['level' => int, 'text' => string], ... ]
	 */
	private function extract_headings( string $html ): array {
		$headings = [];

		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headings[] = [
					'level' => (int) $match[1],
					'text'  => trim( wp_strip_all_tags( $match[2] ) ),
				];
			}
		}

		return $headings;
	}

	/**
	 * Extract body text from HTML, stripping all tags.
	 *
	 * Attempts to isolate <body> content first to avoid counting header/footer
	 * metadata as body text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string Plain text content.
	 */
	private function extract_body_text( string $html ): string {
		$body = $html;

		// Try to isolate <body> content.
		if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $html, $matches ) ) {
			$body = $matches[1];
		}

		// Remove script and style blocks before stripping tags.
		$body = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $body );
		$body = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $body );
		$body = preg_replace( '/<nav[^>]*>.*?<\/nav>/is', '', $body );

		$text = wp_strip_all_tags( $body );

		// Normalise whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Extract and classify links as internal or external.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 * @param string $url  The page URL (used to determine internal/external).
	 *
	 * @return array {
	 *     @type string[] $internal Internal link URLs.
	 *     @type string[] $external External link URLs.
	 * }
	 */
	private function extract_links( string $html, string $url ): array {
		$result = [
			'internal' => [],
			'external' => [],
		];

		$site_host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $site_host ) {
			return $result;
		}

		$site_host = mb_strtolower( $site_host );

		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/is', $html, $matches ) ) {
			return $result;
		}

		foreach ( $matches[1] as $href ) {
			$href = trim( $href );

			// Skip anchors, javascript, mailto, tel.
			if ( '' === $href
				|| 0 === strpos( $href, '#' )
				|| 0 === strpos( $href, 'javascript:' )
				|| 0 === strpos( $href, 'mailto:' )
				|| 0 === strpos( $href, 'tel:' )
			) {
				continue;
			}

			// Relative URLs are internal.
			if ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ) {
				$result['internal'][] = $href;
				continue;
			}

			$host = wp_parse_url( $href, PHP_URL_HOST );

			if ( $host ) {
				$host = mb_strtolower( $host );

				if ( $host === $site_host || substr( $host, -( strlen( '.' . $site_host ) ) ) === '.' . $site_host ) {
					$result['internal'][] = $href;
				} else {
					$result['external'][] = $href;
				}
			}
		}

		return $result;
	}

	/**
	 * Extract images and their alt text from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return array Array of image data: [ ['src' => string, 'alt' => string], ... ]
	 */
	private function extract_images( string $html ): array {
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

	/**
	 * Calculate keyword density as a percentage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text    Plain text content.
	 * @param string $keyword The focus keyword.
	 *
	 * @return float Density percentage (e.g. 2.5 for 2.5%).
	 */
	private function calculate_keyword_density( string $text, string $keyword ): float {
		$word_count = str_word_count( $text );

		if ( 0 === $word_count || '' === trim( $keyword ) ) {
			return 0.0;
		}

		$pattern       = '/\b' . preg_quote( trim( $keyword ), '/' ) . '\b/iu';
		$keyword_count = preg_match_all( $pattern, $text );
		$keyword_count = $keyword_count ?: 0;
		$keyword_words = str_word_count( $keyword );

		return round( ( $keyword_count * $keyword_words ) / $word_count * 100, 2 );
	}

	// =========================================================================
	// Comparison Helpers
	// =========================================================================

	/**
	 * Build a summary of the local post's SEO signals from stored meta data.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post     The local post object.
	 * @param array    $seo_data The stored SEO score data from post meta.
	 *
	 * @return array Own post summary matching the competitor analysis structure.
	 */
	private function build_own_summary( \WP_Post $post, array $seo_data ): array {
		$title       = (string) get_post_meta( $post->ID, '_seo_ai_title', true );
		$description = (string) get_post_meta( $post->ID, '_seo_ai_description', true );

		if ( '' === $title ) {
			$title = $post->post_title;
		}

		$content    = $post->post_content;
		$plain_text = wp_strip_all_tags( $content );
		$word_count = str_word_count( $plain_text );

		return [
			'url'              => get_permalink( $post->ID ) ?: '',
			'title'            => $title,
			'meta_description' => $description,
			'word_count'       => $word_count,
			'seo_score'        => $seo_data,
		];
	}

	/**
	 * Generate actionable suggestions by comparing own and competitor data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $own        Own post summary.
	 * @param array $competitor Competitor analysis data.
	 *
	 * @return array List of suggestion strings.
	 */
	private function generate_suggestions( array $own, array $competitor ): array {
		$suggestions = [];

		// Compare content length.
		$own_words        = $own['word_count'] ?? 0;
		$competitor_words = $competitor['word_count'] ?? 0;

		if ( $competitor_words > 0 && $own_words < $competitor_words ) {
			$difference = $competitor_words - $own_words;
			$suggestions[] = sprintf(
				/* translators: 1: word count difference, 2: competitor word count */
				__( 'Your content is %1$d words shorter than the competitor (%2$d words). Consider expanding your content.', 'seo-ai' ),
				$difference,
				$competitor_words
			);
		}

		// Compare title length.
		$own_title_len        = mb_strlen( $own['title'] ?? '' );
		$competitor_title_len = mb_strlen( $competitor['title'] ?? '' );

		if ( 0 === $own_title_len && $competitor_title_len > 0 ) {
			$suggestions[] = __( 'Your post is missing a title. The competitor has a title set.', 'seo-ai' );
		}

		// Compare meta description.
		$own_desc_len        = mb_strlen( $own['meta_description'] ?? '' );
		$competitor_desc_len = mb_strlen( $competitor['meta_description'] ?? '' );

		if ( 0 === $own_desc_len && $competitor_desc_len > 0 ) {
			$suggestions[] = __( 'Your post is missing a meta description. The competitor has one set.', 'seo-ai' );
		}

		// Compare heading structure.
		$competitor_headings = $competitor['headings']['counts'] ?? [];
		$competitor_h2_count = $competitor_headings['h2'] ?? 0;

		if ( $competitor_h2_count > 0 ) {
			$suggestions[] = sprintf(
				/* translators: %d: number of H2 headings on competitor page */
				__( 'The competitor uses %d H2 headings to structure content. Ensure your content is well-structured with subheadings.', 'seo-ai' ),
				$competitor_h2_count
			);
		}

		// Compare image usage.
		$competitor_images = $competitor['images']['total'] ?? 0;
		$competitor_alts   = $competitor['images']['with_alt'] ?? 0;

		if ( $competitor_images > 0 ) {
			$suggestions[] = sprintf(
				/* translators: 1: total images, 2: images with alt text */
				__( 'The competitor has %1$d images (%2$d with alt text). Make sure your content includes relevant images with descriptive alt attributes.', 'seo-ai' ),
				$competitor_images,
				$competitor_alts
			);
		}

		// Compare link profiles.
		$competitor_internal = $competitor['links']['internal_count'] ?? 0;
		$competitor_external = $competitor['links']['external_count'] ?? 0;

		if ( $competitor_internal > 0 ) {
			$suggestions[] = sprintf(
				/* translators: %d: number of internal links */
				__( 'The competitor has %d internal links. Ensure your content includes relevant internal links.', 'seo-ai' ),
				$competitor_internal
			);
		}

		if ( $competitor_external > 0 ) {
			$suggestions[] = sprintf(
				/* translators: %d: number of external links */
				__( 'The competitor has %d external links to authoritative sources. Consider adding outbound links to credible references.', 'seo-ai' ),
				$competitor_external
			);
		}

		return $suggestions;
	}
}
