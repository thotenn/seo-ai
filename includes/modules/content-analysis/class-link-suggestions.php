<?php
/**
 * AI-Powered Internal Link Suggestions.
 *
 * Analyzes post content and suggests relevant internal links
 * from other published posts on the same site.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   0.6.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

use SeoAi\Providers\Provider_Manager;

/**
 * Class Link_Suggestions
 *
 * Uses the configured AI provider to suggest internal links
 * for a given post based on content similarity and keyword relevance.
 *
 * @since 0.6.0
 */
final class Link_Suggestions {

	/**
	 * Provider manager instance.
	 *
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * Constructor.
	 *
	 * @param Provider_Manager $provider_manager Provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->provider_manager = $provider_manager;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'seo_ai/metabox_saved', [ $this, 'maybe_generate_suggestions' ], 20, 2 );
	}

	/**
	 * Generate link suggestions after metabox save if keyword is present.
	 *
	 * @param int   $post_id   The post ID.
	 * @param array $sanitized The saved metabox data.
	 * @return void
	 */
	public function maybe_generate_suggestions( int $post_id, array $sanitized ): void {
		$keyword = $sanitized['focus_keyword'] ?? '';
		if ( '' === $keyword ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$suggestions = $this->get_suggestions( $post_id, $post->post_content, $keyword );

		if ( ! empty( $suggestions ) ) {
			update_post_meta( $post_id, '_seo_ai_link_suggestions', wp_json_encode( $suggestions ) );
		}
	}

	/**
	 * Get AI-powered internal link suggestions for a post.
	 *
	 * Fetches other published posts, sends them along with the current post's
	 * content and keyword to the AI provider, and returns structured suggestions.
	 *
	 * @param int    $post_id The current post ID (excluded from candidates).
	 * @param string $content The current post content.
	 * @param string $keyword The focus keyword.
	 * @param int    $limit   Maximum number of suggestions to return.
	 * @return array Array of suggestion objects: [target_title, target_url, anchor_text, context]
	 */
	public function get_suggestions( int $post_id, string $content, string $keyword, int $limit = 5 ): array {
		$provider = $this->provider_manager->get_active_provider();
		if ( ! $provider ) {
			return [];
		}

		// Get candidate posts (other published posts on the site).
		$candidates = $this->get_candidate_posts( $post_id, 50 );
		if ( empty( $candidates ) ) {
			return [];
		}

		// Build candidate list for the prompt.
		$candidate_list = '';
		foreach ( $candidates as $index => $candidate ) {
			$candidate_list .= sprintf(
				"%d. \"%s\" — %s\n",
				$index + 1,
				$candidate['title'],
				$candidate['url']
			);
		}

		$system = 'You are an expert SEO analyst specializing in internal linking strategy. '
			. 'Analyze the given post content and suggest internal links to other posts on the same site. '
			. 'Choose links that are contextually relevant and add value for the reader. '
			. "Return EXACTLY a JSON array of objects, each with: target_title, target_url, anchor_text, reason.\n"
			. "Return ONLY the JSON array, no explanation, no markdown fences.";

		$trimmed = wp_trim_words( wp_strip_all_tags( $content ), 500, '' );
		$user    = sprintf(
			"Focus keyword: %s\n\nPost content (excerpt):\n%s\n\nAvailable posts to link to:\n%s\n\nSuggest up to %d internal links.",
			$keyword,
			$trimmed,
			$candidate_list,
			$limit
		);

		try {
			// Prepend custom prompt if configured.
			$settings      = get_option( 'seo_ai_providers', [] );
			$active        = $settings['active_provider'] ?? '';
			$custom_prompt = trim( $settings[ $active ]['custom_prompt'] ?? '' );
			if ( '' !== $custom_prompt ) {
				$system = $custom_prompt . "\n\n" . $system;
			}

			$result = $provider->chat( $system, $user, [
				'temperature' => 0.3,
				'max_tokens'  => 800,
			] );

			return $this->parse_suggestions( $result );
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * Get candidate posts for internal linking.
	 *
	 * @param int $exclude_id Post ID to exclude.
	 * @param int $limit      Maximum candidates to fetch.
	 * @return array Array of [id, title, url].
	 */
	private function get_candidate_posts( int $exclude_id, int $limit = 50 ): array {
		$settings    = get_option( 'seo_ai_settings', [] );
		$post_types  = $settings['analysis_post_types'] ?? [ 'post', 'page' ];

		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => [ $exclude_id ],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		$post_ids   = get_posts( $args );
		$candidates = [];

		foreach ( $post_ids as $pid ) {
			$candidates[] = [
				'id'    => $pid,
				'title' => get_the_title( $pid ),
				'url'   => get_permalink( $pid ),
			];
		}

		return $candidates;
	}

	/**
	 * Parse the AI response into a structured suggestions array.
	 *
	 * @param string $response Raw AI response (expected JSON array).
	 * @return array Parsed suggestions.
	 */
	private function parse_suggestions( string $response ): array {
		// Strip markdown code fences if present.
		$response = preg_replace( '/^```(?:json)?\s*/i', '', trim( $response ) );
		$response = preg_replace( '/\s*```$/', '', $response );

		$decoded = json_decode( $response, true );

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$suggestions = [];

		foreach ( $decoded as $item ) {
			if ( empty( $item['target_url'] ) || empty( $item['anchor_text'] ) ) {
				continue;
			}

			$suggestions[] = [
				'target_title' => sanitize_text_field( $item['target_title'] ?? '' ),
				'target_url'   => esc_url_raw( $item['target_url'] ),
				'anchor_text'  => sanitize_text_field( $item['anchor_text'] ),
				'reason'       => sanitize_text_field( $item['reason'] ?? '' ),
			];
		}

		return $suggestions;
	}

	/**
	 * Get stored suggestions for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array Stored suggestions or empty array.
	 */
	public function get_stored_suggestions( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_seo_ai_link_suggestions', true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : [];
		}

		return [];
	}

	/**
	 * Find orphan posts (posts with no internal links pointing to them).
	 *
	 * Uses a simple heuristic: searches post content of all published posts
	 * for permalink patterns. Posts whose URLs appear in no other post's
	 * content are considered orphans.
	 *
	 * @param int $limit Maximum orphans to return.
	 * @return array Array of [post_id, title, url].
	 */
	public function find_orphan_posts( int $limit = 20 ): array {
		global $wpdb;

		$settings   = get_option( 'seo_ai_settings', [] );
		$post_types = $settings['analysis_post_types'] ?? [ 'post', 'page' ];
		$types_in   = implode( "','", array_map( 'esc_sql', $post_types ) );

		// Get all published posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			AND post_type IN ('{$types_in}')
			ORDER BY post_date DESC
			LIMIT 500"
		);

		if ( empty( $posts ) ) {
			return [];
		}

		$orphans = [];

		foreach ( $posts as $post ) {
			$url = get_permalink( (int) $post->ID );
			if ( ! $url ) {
				continue;
			}

			// Check if this URL appears in any other post's content.
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! $path ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_status = 'publish'
					AND post_type IN ('{$types_in}')
					AND ID != %d
					AND post_content LIKE %s",
					$post->ID,
					'%' . $wpdb->esc_like( $path ) . '%'
				)
			);

			if ( 0 === (int) $found ) {
				$orphans[] = [
					'post_id' => (int) $post->ID,
					'title'   => $post->post_title,
					'url'     => $url,
				];

				if ( count( $orphans ) >= $limit ) {
					break;
				}
			}
		}

		return $orphans;
	}
}
