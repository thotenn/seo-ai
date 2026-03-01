<?php
/**
 * SEO Score Calculator.
 *
 * Static utility class for computing, grading, and labeling weighted SEO
 * and readability scores.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Class Score
 *
 * Provides static helpers that turn an array of check results into an
 * overall numeric score with a human-readable status, label, and colour.
 *
 * @since 1.0.0
 */
final class Score {

	/**
	 * Score threshold for "good" status.
	 *
	 * @var int
	 */
	private const GOOD_THRESHOLD = 70;

	/**
	 * Score threshold for "warning" status (below this is "error").
	 *
	 * @var int
	 */
	private const WARNING_THRESHOLD = 40;

	/**
	 * Calculate the weighted average score from an array of checks.
	 *
	 * Each check must contain at least 'score' (0-100) and 'weight' (int) keys.
	 *
	 * @since 1.0.0
	 *
	 * @param array $checks Array of check result arrays.
	 *
	 * @return int Overall score from 0 to 100.
	 */
	public static function calculate( array $checks ): int {
		if ( empty( $checks ) ) {
			return 0;
		}

		$weighted_sum  = 0;
		$total_weight  = 0;

		foreach ( $checks as $check ) {
			$score  = (int) ( $check['score'] ?? 0 );
			$weight = (int) ( $check['weight'] ?? 1 );

			$weighted_sum += $score * $weight;
			$total_weight += $weight;
		}

		if ( 0 === $total_weight ) {
			return 0;
		}

		return (int) round( $weighted_sum / $total_weight );
	}

	/**
	 * Get the status string for a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score Score from 0 to 100.
	 *
	 * @return string 'good', 'warning', or 'error'.
	 */
	public static function get_status( int $score ): string {
		if ( $score > self::GOOD_THRESHOLD ) {
			return 'good';
		}

		if ( $score >= self::WARNING_THRESHOLD ) {
			return 'warning';
		}

		return 'error';
	}

	/**
	 * Get the hex colour associated with a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score Score from 0 to 100.
	 *
	 * @return string Hex colour string.
	 */
	public static function get_color( int $score ): string {
		if ( $score > self::GOOD_THRESHOLD ) {
			return '#1e8a3e';
		}

		if ( $score >= self::WARNING_THRESHOLD ) {
			return '#f0a500';
		}

		return '#dc3545';
	}

	/**
	 * Get a human-readable label for a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score Score from 0 to 100.
	 *
	 * @return string 'Good', 'Needs Improvement', or 'Poor'.
	 */
	public static function get_label( int $score ): string {
		if ( $score > self::GOOD_THRESHOLD ) {
			return __( 'Good', 'seo-ai' );
		}

		if ( $score >= self::WARNING_THRESHOLD ) {
			return __( 'Needs Improvement', 'seo-ai' );
		}

		return __( 'Poor', 'seo-ai' );
	}
}
