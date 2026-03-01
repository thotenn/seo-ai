<?php
/**
 * Analysis REST Controller.
 *
 * Exposes the content analysis endpoint that evaluates SEO quality
 * and readability for a given post or arbitrary content.
 *
 * @package SeoAi\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Rest;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SeoAi\Modules\Content_Analysis\Keyword_Analyzer;
use SeoAi\Modules\Content_Analysis\Readability;
use SeoAi\Modules\Content_Analysis\Score;

/**
 * Class Analysis_Controller
 *
 * Handles the `POST /seo-ai/v1/analyze` endpoint.
 *
 * @since 1.0.0
 */
final class Analysis_Controller extends Rest_Controller {

	/**
	 * Register routes for content analysis.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/analyze',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'analyze' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => $this->get_analyze_args(),
			]
		);
	}

	/**
	 * Analyze content for SEO and readability.
	 *
	 * Runs keyword-based SEO checks and readability analysis, returning
	 * a scored breakdown of every individual check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function analyze( WP_REST_Request $request ) {
		$post_id     = (int) $request->get_param( 'post_id' );
		$title       = (string) $request->get_param( 'title' );
		$content     = (string) $request->get_param( 'content' );
		$description = (string) $request->get_param( 'description' );
		$keyword     = (string) $request->get_param( 'keyword' );
		$url         = (string) $request->get_param( 'url' );

		if ( '' === trim( $content ) ) {
			return $this->error(
				__( 'Content is required for analysis.', 'seo-ai' ),
				422
			);
		}

		try {
			$seo_checks         = $this->run_seo_checks( $title, $content, $description, $keyword, $url );
			$readability_result = $this->run_readability_checks( $content );

			$seo_score = Score::calculate( $seo_checks );

			// Persist scores as post meta when a post ID is provided.
			if ( $post_id > 0 ) {
				update_post_meta( $post_id, '_seo_ai_seo_score', $seo_score );
				update_post_meta( $post_id, '_seo_ai_readability_score', $readability_result['score'] );
				update_post_meta( $post_id, '_seo_ai_last_analysis', current_time( 'mysql' ) );
			}

			return $this->success( [
				'seo'         => [
					'score'  => $seo_score,
					'status' => Score::get_status( $seo_score ),
					'label'  => Score::get_label( $seo_score ),
					'color'  => Score::get_color( $seo_score ),
					'checks' => $seo_checks,
				],
				'readability' => [
					'score'  => $readability_result['score'],
					'status' => Score::get_status( $readability_result['score'] ),
					'label'  => Score::get_label( $readability_result['score'] ),
					'color'  => Score::get_color( $readability_result['score'] ),
					'checks' => $readability_result['checks'],
				],
			] );
		} catch ( \Throwable $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	// -------------------------------------------------------------------------
	// SEO Checks
	// -------------------------------------------------------------------------

	/**
	 * Run keyword and on-page SEO checks.
	 *
	 * @param string $title       Post/page title.
	 * @param string $content     Post content (HTML).
	 * @param string $description Meta description.
	 * @param string $keyword     Focus keyword.
	 * @param string $url         Permalink / slug.
	 *
	 * @return array Array of check result arrays.
	 */
	private function run_seo_checks(
		string $title,
		string $content,
		string $description,
		string $keyword,
		string $url
	): array {
		$checks   = [];
		$analyzer = new Keyword_Analyzer();

		// 1. Focus keyword set.
		$checks[] = $this->check_keyword_set( $keyword );

		if ( '' !== trim( $keyword ) ) {
			// 2. Keyword in title.
			$checks[] = $this->check_keyword_in_title( $keyword, $title );

			// 3. Keyword in meta description.
			$checks[] = $this->check_keyword_in_description( $keyword, $description );

			// 4. Keyword in URL.
			$checks[] = $this->check_keyword_in_url( $keyword, $url );

			// 5. Keyword density.
			$checks[] = $this->check_keyword_density( $analyzer, $keyword, $content );

			// 6. Keyword in first paragraph.
			$checks[] = $this->check_keyword_in_intro( $keyword, $content );

			// 7. Keyword distribution.
			$checks[] = $this->check_keyword_distribution( $analyzer, $keyword, $content );
		}

		// 8. Title length.
		$checks[] = $this->check_title_length( $title );

		// 9. Meta description length.
		$checks[] = $this->check_description_length( $description );

		// 10. Content length.
		$checks[] = $this->check_content_length( $content );

		// 11. Keyword in H2/H3 subheadings.
		if ( '' !== trim( $keyword ) ) {
			$checks[] = $this->check_keyword_in_subheadings( $keyword, $content );
		}

		// 12. Internal links.
		$checks[] = $this->check_internal_links( $content );

		// 13. External links.
		$checks[] = $this->check_external_links( $content );

		// 14. Image alt attributes.
		$checks[] = $this->check_image_alt_attributes( $content );

		return $checks;
	}

	/**
	 * Check that a focus keyword is set.
	 *
	 * @param string $keyword Focus keyword.
	 *
	 * @return array Check result.
	 */
	private function check_keyword_set( string $keyword ): array {
		if ( '' !== trim( $keyword ) ) {
			return $this->build_check(
				'keyword_set',
				__( 'Focus Keyword', 'seo-ai' ),
				'good',
				__( 'Focus keyword is set.', 'seo-ai' ),
				10,
				100
			);
		}

		return $this->build_check(
			'keyword_set',
			__( 'Focus Keyword', 'seo-ai' ),
			'error',
			__( 'No focus keyword set. Add a focus keyword to enable keyword-specific checks.', 'seo-ai' ),
			10,
			0
		);
	}

	/**
	 * Check if the keyword appears in the title.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $title   Post title.
	 *
	 * @return array Check result.
	 */
	private function check_keyword_in_title( string $keyword, string $title ): array {
		if ( '' === trim( $title ) ) {
			return $this->build_check(
				'keyword_in_title',
				__( 'Keyword in Title', 'seo-ai' ),
				'error',
				__( 'No title found. Add a title that contains the focus keyword.', 'seo-ai' ),
				15,
				0
			);
		}

		if ( mb_stripos( $title, $keyword ) !== false ) {
			return $this->build_check(
				'keyword_in_title',
				__( 'Keyword in Title', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the title.', 'seo-ai' ),
				15,
				100
			);
		}

		return $this->build_check(
			'keyword_in_title',
			__( 'Keyword in Title', 'seo-ai' ),
			'error',
			__( 'The focus keyword does not appear in the title. Include it for better rankings.', 'seo-ai' ),
			15,
			0
		);
	}

	/**
	 * Check if the keyword appears in the meta description.
	 *
	 * @param string $keyword     Focus keyword.
	 * @param string $description Meta description.
	 *
	 * @return array Check result.
	 */
	private function check_keyword_in_description( string $keyword, string $description ): array {
		if ( '' === trim( $description ) ) {
			return $this->build_check(
				'keyword_in_description',
				__( 'Keyword in Meta Description', 'seo-ai' ),
				'warning',
				__( 'No meta description set. Write one that includes the focus keyword.', 'seo-ai' ),
				10,
				30
			);
		}

		if ( mb_stripos( $description, $keyword ) !== false ) {
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
			__( 'The focus keyword does not appear in the meta description. Consider adding it.', 'seo-ai' ),
			10,
			40
		);
	}

	/**
	 * Check if the keyword appears in the URL.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $url     Permalink or slug.
	 *
	 * @return array Check result.
	 */
	private function check_keyword_in_url( string $keyword, string $url ): array {
		if ( '' === trim( $url ) ) {
			return $this->build_check(
				'keyword_in_url',
				__( 'Keyword in URL', 'seo-ai' ),
				'warning',
				__( 'No URL provided to check.', 'seo-ai' ),
				5,
				50
			);
		}

		$slug_keyword = sanitize_title( $keyword );
		$url_lower    = mb_strtolower( $url );

		if ( str_contains( $url_lower, $slug_keyword ) ) {
			return $this->build_check(
				'keyword_in_url',
				__( 'Keyword in URL', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the URL slug.', 'seo-ai' ),
				5,
				100
			);
		}

		return $this->build_check(
			'keyword_in_url',
			__( 'Keyword in URL', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in the URL slug. Consider updating it.', 'seo-ai' ),
			5,
			40
		);
	}

	/**
	 * Check keyword density.
	 *
	 * @param Keyword_Analyzer $analyzer Keyword analyzer instance.
	 * @param string           $keyword  Focus keyword.
	 * @param string           $content  Post content.
	 *
	 * @return array Check result.
	 */
	private function check_keyword_density( Keyword_Analyzer $analyzer, string $keyword, string $content ): array {
		$settings    = $this->plugin->options();
		$density_min = (float) $settings->get( 'keyword_density_min', 1.0 );
		$density_max = (float) $settings->get( 'keyword_density_max', 3.0 );
		$density     = $analyzer->get_density( $content, $keyword );

		if ( $density >= $density_min && $density <= $density_max ) {
			return $this->build_check(
				'keyword_density',
				__( 'Keyword Density', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %s: density percentage */
					__( 'Keyword density is %.1f%%, which is within the ideal range.', 'seo-ai' ),
					$density
				),
				10,
				100
			);
		}

		if ( $density < $density_min ) {
			return $this->build_check(
				'keyword_density',
				__( 'Keyword Density', 'seo-ai' ),
				'warning',
				sprintf(
					/* translators: 1: current density, 2: minimum density */
					__( 'Keyword density is %.1f%%, which is below the recommended minimum of %.1f%%. Use the keyword more often.', 'seo-ai' ),
					$density,
					$density_min
				),
				10,
				40
			);
		}

		return $this->build_check(
			'keyword_density',
			__( 'Keyword Density', 'seo-ai' ),
			'warning',
			sprintf(
				/* translators: 1: current density, 2: maximum density */
				__( 'Keyword density is %.1f%%, which exceeds the recommended maximum of %.1f%%. Reduce keyword usage to avoid over-optimization.', 'seo-ai' ),
				$density,
				$density_max
			),
			10,
			40
		);
	}

	/**
	 * Check if the keyword appears in the first paragraph.
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $content Post content (HTML).
	 *
	 * @return array Check result.
	 */
	private function check_keyword_in_intro( string $keyword, string $content ): array {
		// Extract the first paragraph (before the first closing </p> or first double newline).
		$first_paragraph = '';
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $matches ) ) {
			$first_paragraph = wp_strip_all_tags( $matches[1] );
		} else {
			$plain = wp_strip_all_tags( $content );
			$parts = preg_split( '/\n\s*\n/', $plain, 2 );
			$first_paragraph = $parts[0] ?? '';
		}

		if ( '' === trim( $first_paragraph ) ) {
			return $this->build_check(
				'keyword_in_intro',
				__( 'Keyword in Introduction', 'seo-ai' ),
				'warning',
				__( 'Could not identify an introduction paragraph.', 'seo-ai' ),
				5,
				50
			);
		}

		if ( mb_stripos( $first_paragraph, $keyword ) !== false ) {
			return $this->build_check(
				'keyword_in_intro',
				__( 'Keyword in Introduction', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in the introduction.', 'seo-ai' ),
				5,
				100
			);
		}

		return $this->build_check(
			'keyword_in_intro',
			__( 'Keyword in Introduction', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in the introduction. Mention it early for better SEO.', 'seo-ai' ),
			5,
			30
		);
	}

	/**
	 * Check keyword distribution throughout the content.
	 *
	 * @param Keyword_Analyzer $analyzer Keyword analyzer instance.
	 * @param string           $keyword  Focus keyword.
	 * @param string           $content  Post content.
	 *
	 * @return array Check result.
	 */
	private function check_keyword_distribution( Keyword_Analyzer $analyzer, string $keyword, string $content ): array {
		$dist = $analyzer->get_distribution( $content, $keyword );

		if ( 0 === $dist['segments'] ) {
			return $this->build_check(
				'keyword_distribution',
				__( 'Keyword Distribution', 'seo-ai' ),
				'warning',
				__( 'Not enough content to evaluate keyword distribution.', 'seo-ai' ),
				5,
				50
			);
		}

		$ratio = $dist['segments_with'] / $dist['segments'];

		if ( $ratio >= 0.6 ) {
			return $this->build_check(
				'keyword_distribution',
				__( 'Keyword Distribution', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: 1: segments with keyword, 2: total segments */
					__( 'The keyword is well distributed: found in %1$d of %2$d content segments.', 'seo-ai' ),
					$dist['segments_with'],
					$dist['segments']
				),
				5,
				100
			);
		}

		if ( $ratio >= 0.3 ) {
			return $this->build_check(
				'keyword_distribution',
				__( 'Keyword Distribution', 'seo-ai' ),
				'warning',
				sprintf(
					/* translators: 1: segments with keyword, 2: total segments */
					__( 'The keyword appears in %1$d of %2$d content segments. Spread it more evenly.', 'seo-ai' ),
					$dist['segments_with'],
					$dist['segments']
				),
				5,
				50
			);
		}

		return $this->build_check(
			'keyword_distribution',
			__( 'Keyword Distribution', 'seo-ai' ),
			'error',
			sprintf(
				/* translators: 1: segments with keyword, 2: total segments */
				__( 'The keyword only appears in %1$d of %2$d content segments. Distribute it throughout your content.', 'seo-ai' ),
				$dist['segments_with'],
				$dist['segments']
			),
			5,
			10
		);
	}

	/**
	 * Check title length.
	 *
	 * @param string $title Post title.
	 *
	 * @return array Check result.
	 */
	private function check_title_length( string $title ): array {
		$length = mb_strlen( trim( $title ) );

		if ( 0 === $length ) {
			return $this->build_check(
				'title_length',
				__( 'Title Length', 'seo-ai' ),
				'error',
				__( 'No title found. Add a descriptive title between 30 and 60 characters.', 'seo-ai' ),
				10,
				0
			);
		}

		if ( $length >= 30 && $length <= 60 ) {
			return $this->build_check(
				'title_length',
				__( 'Title Length', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %d: character count */
					__( 'Title length is %d characters, which is ideal for search results.', 'seo-ai' ),
					$length
				),
				10,
				100
			);
		}

		if ( $length < 30 ) {
			return $this->build_check(
				'title_length',
				__( 'Title Length', 'seo-ai' ),
				'warning',
				sprintf(
					/* translators: %d: character count */
					__( 'Title length is %d characters, which is too short. Aim for 30-60 characters.', 'seo-ai' ),
					$length
				),
				10,
				50
			);
		}

		return $this->build_check(
			'title_length',
			__( 'Title Length', 'seo-ai' ),
			'warning',
			sprintf(
				/* translators: %d: character count */
				__( 'Title length is %d characters, which may be truncated in search results. Keep it under 60 characters.', 'seo-ai' ),
				$length
			),
			10,
			60
		);
	}

	/**
	 * Check meta description length.
	 *
	 * @param string $description Meta description.
	 *
	 * @return array Check result.
	 */
	private function check_description_length( string $description ): array {
		$length = mb_strlen( trim( $description ) );

		if ( 0 === $length ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'warning',
				__( 'No meta description set. Write one between 120 and 160 characters.', 'seo-ai' ),
				10,
				20
			);
		}

		if ( $length >= 120 && $length <= 160 ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %d: character count */
					__( 'Meta description is %d characters, which is ideal.', 'seo-ai' ),
					$length
				),
				10,
				100
			);
		}

		if ( $length < 120 ) {
			return $this->build_check(
				'description_length',
				__( 'Meta Description Length', 'seo-ai' ),
				'warning',
				sprintf(
					/* translators: %d: character count */
					__( 'Meta description is %d characters, which is short. Aim for 120-160 characters.', 'seo-ai' ),
					$length
				),
				10,
				50
			);
		}

		return $this->build_check(
			'description_length',
			__( 'Meta Description Length', 'seo-ai' ),
			'warning',
			sprintf(
				/* translators: %d: character count */
				__( 'Meta description is %d characters, which may be truncated. Keep it under 160 characters.', 'seo-ai' ),
				$length
			),
			10,
			60
		);
	}

	/**
	 * Check content length.
	 *
	 * @param string $content Post content.
	 *
	 * @return array Check result.
	 */
	private function check_content_length( string $content ): array {
		$word_count = str_word_count( wp_strip_all_tags( $content ) );
		$min_length = (int) $this->plugin->options()->get( 'min_content_length', 300 );

		if ( $word_count >= $min_length ) {
			return $this->build_check(
				'content_length',
				__( 'Content Length', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %d: word count */
					__( 'Content has %d words, which is sufficient for search engines.', 'seo-ai' ),
					$word_count
				),
				10,
				100
			);
		}

		if ( $word_count >= ( $min_length / 2 ) ) {
			return $this->build_check(
				'content_length',
				__( 'Content Length', 'seo-ai' ),
				'warning',
				sprintf(
					/* translators: 1: word count, 2: minimum words */
					__( 'Content has %1$d words. Aim for at least %2$d words for better SEO performance.', 'seo-ai' ),
					$word_count,
					$min_length
				),
				10,
				50
			);
		}

		return $this->build_check(
			'content_length',
			__( 'Content Length', 'seo-ai' ),
			'error',
			sprintf(
				/* translators: 1: word count, 2: minimum words */
				__( 'Content has only %1$d words. Write at least %2$d words for search engines to properly index the page.', 'seo-ai' ),
				$word_count,
				$min_length
			),
			10,
			10
		);
	}

	/**
	 * Check if the keyword appears in subheadings (H2-H6).
	 *
	 * @param string $keyword Focus keyword.
	 * @param string $content Post content (HTML).
	 *
	 * @return array Check result.
	 */
	private function check_keyword_in_subheadings( string $keyword, string $content ): array {
		preg_match_all( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $content, $matches );

		if ( empty( $matches[1] ) ) {
			return $this->build_check(
				'keyword_in_subheadings',
				__( 'Keyword in Subheadings', 'seo-ai' ),
				'warning',
				__( 'No subheadings found. Add H2-H6 headings containing the focus keyword.', 'seo-ai' ),
				5,
				30
			);
		}

		$found = false;
		foreach ( $matches[1] as $heading_text ) {
			$clean = wp_strip_all_tags( $heading_text );
			if ( mb_stripos( $clean, $keyword ) !== false ) {
				$found = true;
				break;
			}
		}

		if ( $found ) {
			return $this->build_check(
				'keyword_in_subheadings',
				__( 'Keyword in Subheadings', 'seo-ai' ),
				'good',
				__( 'The focus keyword appears in at least one subheading.', 'seo-ai' ),
				5,
				100
			);
		}

		return $this->build_check(
			'keyword_in_subheadings',
			__( 'Keyword in Subheadings', 'seo-ai' ),
			'warning',
			__( 'The focus keyword does not appear in any subheading. Include it in an H2 or H3 for better SEO.', 'seo-ai' ),
			5,
			30
		);
	}

	/**
	 * Check for internal links.
	 *
	 * @param string $content Post content (HTML).
	 *
	 * @return array Check result.
	 */
	private function check_internal_links( string $content ): array {
		$site_url = wp_parse_url( home_url(), PHP_URL_HOST );

		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		$internal_count = 0;
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $href ) {
				$href_host = wp_parse_url( $href, PHP_URL_HOST );
				// Relative URLs or same-domain URLs are internal.
				if ( null === $href_host || $href_host === $site_url ) {
					$internal_count++;
				}
			}
		}

		if ( $internal_count >= 1 ) {
			return $this->build_check(
				'internal_links',
				__( 'Internal Links', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %d: number of internal links */
					__( 'Found %d internal link(s). Good for site structure and SEO.', 'seo-ai' ),
					$internal_count
				),
				5,
				100
			);
		}

		return $this->build_check(
			'internal_links',
			__( 'Internal Links', 'seo-ai' ),
			'warning',
			__( 'No internal links found. Add links to other pages on your site to improve SEO.', 'seo-ai' ),
			5,
			20
		);
	}

	/**
	 * Check for external (outbound) links.
	 *
	 * @param string $content Post content (HTML).
	 *
	 * @return array Check result.
	 */
	private function check_external_links( string $content ): array {
		$site_url = wp_parse_url( home_url(), PHP_URL_HOST );

		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		$external_count = 0;
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $href ) {
				$href_host = wp_parse_url( $href, PHP_URL_HOST );
				if ( null !== $href_host && $href_host !== $site_url ) {
					$external_count++;
				}
			}
		}

		if ( $external_count >= 1 ) {
			return $this->build_check(
				'external_links',
				__( 'External Links', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %d: number of external links */
					__( 'Found %d external link(s). Linking to authoritative sources improves credibility.', 'seo-ai' ),
					$external_count
				),
				3,
				100
			);
		}

		return $this->build_check(
			'external_links',
			__( 'External Links', 'seo-ai' ),
			'warning',
			__( 'No external links found. Link to reputable sources to boost trustworthiness.', 'seo-ai' ),
			3,
			30
		);
	}

	/**
	 * Check that images have alt attributes.
	 *
	 * @param string $content Post content (HTML).
	 *
	 * @return array Check result.
	 */
	private function check_image_alt_attributes( string $content ): array {
		preg_match_all( '/<img\s[^>]*>/i', $content, $img_matches );

		if ( empty( $img_matches[0] ) ) {
			return $this->build_check(
				'image_alt',
				__( 'Image Alt Attributes', 'seo-ai' ),
				'good',
				__( 'No images found. This check is not applicable.', 'seo-ai' ),
				3,
				100
			);
		}

		$total   = count( $img_matches[0] );
		$missing = 0;

		foreach ( $img_matches[0] as $img_tag ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img_tag ) ) {
				$missing++;
			}
		}

		if ( 0 === $missing ) {
			return $this->build_check(
				'image_alt',
				__( 'Image Alt Attributes', 'seo-ai' ),
				'good',
				sprintf(
					/* translators: %d: number of images */
					__( 'All %d image(s) have alt attributes. Well done!', 'seo-ai' ),
					$total
				),
				3,
				100
			);
		}

		return $this->build_check(
			'image_alt',
			__( 'Image Alt Attributes', 'seo-ai' ),
			'warning',
			sprintf(
				/* translators: 1: missing count, 2: total images */
				__( '%1$d of %2$d image(s) are missing alt attributes. Add descriptive alt text for accessibility and SEO.', 'seo-ai' ),
				$missing,
				$total
			),
			3,
			(int) round( ( ( $total - $missing ) / $total ) * 100 )
		);
	}

	// -------------------------------------------------------------------------
	// Readability Checks
	// -------------------------------------------------------------------------

	/**
	 * Run readability analysis.
	 *
	 * @param string $content Post content (HTML).
	 *
	 * @return array { score: int, checks: array }
	 */
	private function run_readability_checks( string $content ): array {
		$readability = new Readability();

		return $readability->analyze( $content );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a standardised check result array.
	 *
	 * @param string $id      Unique check identifier.
	 * @param string $label   Human-readable check name.
	 * @param string $status  'good', 'warning', or 'error'.
	 * @param string $message Detailed user-facing message.
	 * @param int    $weight  Importance weight for scoring.
	 * @param int    $score   Raw score (0-100).
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

	/**
	 * Define the accepted arguments for the analyze endpoint.
	 *
	 * @return array
	 */
	private function get_analyze_args(): array {
		return [
			'post_id'     => [
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
			'title'       => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'content'     => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'wp_kses_post',
			],
			'description' => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'keyword'     => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'url'         => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			],
		];
	}
}
