<?php
/**
 * Analytics & Keyword Tracking Module.
 *
 * Provides keyword position tracking, search performance estimates,
 * and per-post analytics using stored SEO data and AI analysis.
 *
 * @package SeoAi\Modules\Analytics
 * @since   0.7.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Analytics;

defined( 'ABSPATH' ) || exit;

use SeoAi\Helpers\Options;

/**
 * Class Analytics
 *
 * Tracks focus keyword performance, estimates search metrics,
 * and provides an analytics dashboard with post performance data.
 *
 * @since 0.7.0
 */
final class Analytics {

	/**
	 * Custom table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE = 'seo_ai_keyword_tracking';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'seo_ai/metabox_saved', [ $this, 'track_keyword_snapshot' ], 30, 2 );
		add_action( 'seo_ai_daily_keyword_track', [ $this, 'run_daily_tracking' ] );

		// Schedule daily tracking cron if not already scheduled.
		if ( ! wp_next_scheduled( 'seo_ai_daily_keyword_track' ) ) {
			wp_schedule_event( time(), 'daily', 'seo_ai_daily_keyword_track' );
		}
	}

	/**
	 * Store a keyword tracking snapshot when metabox is saved.
	 *
	 * @param int   $post_id   The post ID.
	 * @param array $sanitized The saved metabox data.
	 * @return void
	 */
	public function track_keyword_snapshot( int $post_id, array $sanitized ): void {
		$keyword = $sanitized['focus_keyword'] ?? '';
		if ( '' === $keyword ) {
			return;
		}

		$seo_score = (int) get_post_meta( $post_id, '_seo_ai_seo_score', true );

		$this->store_snapshot( $post_id, $keyword, $seo_score );
	}

	/**
	 * Run daily keyword tracking for all posts with focus keywords.
	 *
	 * @return void
	 */
	public function run_daily_tracking(): void {
		$settings   = get_option( 'seo_ai_settings', [] );
		$post_types = $settings['analysis_post_types'] ?? [ 'post', 'page' ];

		$posts = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'meta_query'     => [
				[
					'key'     => '_seo_ai_focus_keyword',
					'value'   => '',
					'compare' => '!=',
				],
			],
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		foreach ( $posts as $post_id ) {
			$keyword   = (string) get_post_meta( $post_id, '_seo_ai_focus_keyword', true );
			$seo_score = (int) get_post_meta( $post_id, '_seo_ai_seo_score', true );

			if ( '' !== $keyword ) {
				$this->store_snapshot( $post_id, $keyword, $seo_score );
			}
		}
	}

	/**
	 * Store a keyword tracking snapshot in the database.
	 *
	 * Only stores one snapshot per post per day to avoid data bloat.
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $keyword   The focus keyword.
	 * @param int    $seo_score The SEO score.
	 * @return void
	 */
	private function store_snapshot( int $post_id, string $keyword, int $seo_score ): void {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$today = current_time( 'Y-m-d' );

		// Check if we already have a snapshot for today.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE post_id = %d AND tracked_date = %s LIMIT 1",
				$post_id,
				$today
			)
		);

		if ( $exists ) {
			// Update existing snapshot.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[
					'keyword'   => $keyword,
					'seo_score' => $seo_score,
				],
				[
					'id' => (int) $exists,
				],
				[ '%s', '%d' ],
				[ '%d' ]
			);
		} else {
			// Insert new snapshot.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				[
					'post_id'      => $post_id,
					'keyword'      => $keyword,
					'seo_score'    => $seo_score,
					'tracked_date' => $today,
				],
				[ '%d', '%s', '%d', '%s' ]
			);
		}
	}

	/**
	 * Get keyword tracking history for a post.
	 *
	 * @param int $post_id The post ID.
	 * @param int $days    Number of days of history to fetch.
	 * @return array Array of tracking records.
	 */
	public function get_post_history( int $post_id, int $days = 30 ): array {
		global $wpdb;

		$table     = $wpdb->prefix . self::TABLE;
		$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT keyword, seo_score, tracked_date
				FROM {$table}
				WHERE post_id = %d AND tracked_date >= %s
				ORDER BY tracked_date ASC",
				$post_id,
				$date_from
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Get top performing posts by SEO score.
	 *
	 * @param int $limit Number of posts to return.
	 * @return array Array of [post_id, title, keyword, seo_score, url].
	 */
	public function get_top_posts( int $limit = 10 ): array {
		$settings   = get_option( 'seo_ai_settings', [] );
		$post_types = $settings['analysis_post_types'] ?? [ 'post', 'page' ];

		$posts = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_key'       => '_seo_ai_seo_score',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => [
				[
					'key'     => '_seo_ai_seo_score',
					'value'   => '0',
					'compare' => '>',
					'type'    => 'NUMERIC',
				],
			],
			'no_found_rows'  => true,
		] );

		$results = [];
		foreach ( $posts as $post ) {
			$results[] = [
				'post_id'   => $post->ID,
				'title'     => $post->post_title,
				'keyword'   => (string) get_post_meta( $post->ID, '_seo_ai_focus_keyword', true ),
				'seo_score' => (int) get_post_meta( $post->ID, '_seo_ai_seo_score', true ),
				'url'       => get_permalink( $post->ID ),
			];
		}

		return $results;
	}

	/**
	 * Get posts with declining SEO scores (losing content).
	 *
	 * Compares latest score with score from 7 days ago.
	 *
	 * @param int $limit Number of posts to return.
	 * @return array Posts with score decline.
	 */
	public function get_declining_posts( int $limit = 10 ): array {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE;
		$today      = current_time( 'Y-m-d' );
		$week_ago   = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					t1.post_id,
					t1.keyword,
					t1.seo_score AS current_score,
					t2.seo_score AS previous_score,
					(t1.seo_score - t2.seo_score) AS score_change
				FROM {$table} t1
				INNER JOIN {$table} t2
					ON t1.post_id = t2.post_id
					AND t2.tracked_date = %s
				WHERE t1.tracked_date = %s
				AND t1.seo_score < t2.seo_score
				ORDER BY score_change ASC
				LIMIT %d",
				$week_ago,
				$today,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return [];
		}

		foreach ( $results as &$row ) {
			$post = get_post( (int) $row['post_id'] );
			$row['title'] = $post ? $post->post_title : '';
			$row['url']   = $post ? get_permalink( $post->ID ) : '';
		}

		return $results;
	}

	/**
	 * Get overall SEO health summary.
	 *
	 * @return array [total_posts, analyzed_posts, avg_score, good_count, needs_work_count, poor_count]
	 */
	public function get_health_summary(): array {
		$settings   = get_option( 'seo_ai_settings', [] );
		$post_types = $settings['analysis_post_types'] ?? [ 'post', 'page' ];

		$total = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total += (int) ( $counts->publish ?? 0 );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) AS analyzed,
				AVG(CAST(meta_value AS UNSIGNED)) AS avg_score,
				SUM(CASE WHEN CAST(meta_value AS UNSIGNED) >= 70 THEN 1 ELSE 0 END) AS good,
				SUM(CASE WHEN CAST(meta_value AS UNSIGNED) >= 40 AND CAST(meta_value AS UNSIGNED) < 70 THEN 1 ELSE 0 END) AS needs_work,
				SUM(CASE WHEN CAST(meta_value AS UNSIGNED) > 0 AND CAST(meta_value AS UNSIGNED) < 40 THEN 1 ELSE 0 END) AS poor
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_seo_ai_seo_score'
			AND meta_value != ''
			AND meta_value != '0'",
			ARRAY_A
		);

		return [
			'total_posts'     => $total,
			'analyzed_posts'  => (int) ( $stats['analyzed'] ?? 0 ),
			'avg_score'       => round( (float) ( $stats['avg_score'] ?? 0 ) ),
			'good_count'      => (int) ( $stats['good'] ?? 0 ),
			'needs_work_count' => (int) ( $stats['needs_work'] ?? 0 ),
			'poor_count'      => (int) ( $stats['poor'] ?? 0 ),
		];
	}

	/**
	 * Clean up old tracking data.
	 *
	 * @param int $days Keep data for this many days (default: 90).
	 * @return int Number of rows deleted.
	 */
	public function cleanup( int $days = 90 ): int {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$cutoff  = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE tracked_date < %s",
				$cutoff
			)
		);

		return (int) $deleted;
	}
}
