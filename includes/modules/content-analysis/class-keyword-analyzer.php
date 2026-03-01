<?php
/**
 * Keyword Analyzer.
 *
 * Provides keyword-specific analysis: density, prominence, distribution,
 * and related-keyword suggestions.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Class Keyword_Analyzer
 *
 * Analyses how a focus keyword is used within a given piece of content.
 *
 * @since 1.0.0
 */
final class Keyword_Analyzer {

	/**
	 * Calculate keyword density as a percentage of total words.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content  The text content (HTML is stripped internally).
	 * @param string $keyword  The focus keyword or key-phrase.
	 *
	 * @return float Density percentage (e.g. 2.5 for 2.5%).
	 */
	public function get_density( string $content, string $keyword ): float {
		$text       = wp_strip_all_tags( $content );
		$word_count = str_word_count( $text );

		if ( 0 === $word_count || '' === trim( $keyword ) ) {
			return 0.0;
		}

		$keyword_count = $this->count_keyword( $text, $keyword );
		$keyword_words = str_word_count( $keyword );

		// Each occurrence of the keyword accounts for $keyword_words words.
		return round( ( $keyword_count * $keyword_words ) / $word_count * 100, 2 );
	}

	/**
	 * Calculate keyword prominence (how early the keyword appears).
	 *
	 * Returns a value from 0 to 100, where 100 means the keyword appears at
	 * the very beginning of the content and 0 means it does not appear at all.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content  The text content.
	 * @param string $keyword  The focus keyword.
	 *
	 * @return float Prominence score from 0 to 100.
	 */
	public function get_prominence( string $content, string $keyword ): float {
		$text = wp_strip_all_tags( $content );

		if ( '' === trim( $text ) || '' === trim( $keyword ) ) {
			return 0.0;
		}

		$text_lower    = mb_strtolower( $text );
		$keyword_lower = mb_strtolower( trim( $keyword ) );
		$position      = mb_strpos( $text_lower, $keyword_lower );

		if ( false === $position ) {
			return 0.0;
		}

		$text_length = mb_strlen( $text_lower );

		if ( 0 === $text_length ) {
			return 0.0;
		}

		// The earlier the keyword appears, the higher the prominence.
		return round( ( 1 - ( $position / $text_length ) ) * 100, 2 );
	}

	/**
	 * Get keyword distribution throughout the content.
	 *
	 * Splits the content into segments and reports which segments contain
	 * the keyword, providing a map of positions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content  The text content.
	 * @param string $keyword  The focus keyword.
	 *
	 * @return array {
	 *     @type int   $total_occurrences Total keyword count.
	 *     @type int   $segments          Number of content segments.
	 *     @type int   $segments_with     Number of segments containing the keyword.
	 *     @type array $positions         Percentage positions (0-100) of each occurrence.
	 * }
	 */
	public function get_distribution( string $content, string $keyword ): array {
		$text = wp_strip_all_tags( $content );

		$result = [
			'total_occurrences' => 0,
			'segments'          => 0,
			'segments_with'     => 0,
			'positions'         => [],
		];

		if ( '' === trim( $text ) || '' === trim( $keyword ) ) {
			return $result;
		}

		// Divide content into roughly equal segments (aim for ~5 segments).
		$segment_count = 5;
		$text_length   = mb_strlen( $text );
		$segment_size  = max( 1, (int) ceil( $text_length / $segment_count ) );
		$segments      = [];

		for ( $i = 0; $i < $segment_count; $i++ ) {
			$segments[] = mb_substr( $text, $i * $segment_size, $segment_size );
		}

		$result['segments'] = count( $segments );

		$keyword_lower = mb_strtolower( trim( $keyword ) );
		$text_lower    = mb_strtolower( $text );

		// Count occurrences per segment.
		foreach ( $segments as $segment ) {
			$segment_lower = mb_strtolower( $segment );
			if ( mb_strpos( $segment_lower, $keyword_lower ) !== false ) {
				$result['segments_with']++;
			}
		}

		// Find all positions of the keyword in the full text.
		$offset = 0;
		while ( ( $pos = mb_strpos( $text_lower, $keyword_lower, $offset ) ) !== false ) {
			$result['total_occurrences']++;
			$result['positions'][] = round( ( $pos / $text_length ) * 100, 1 );
			$offset = $pos + mb_strlen( $keyword_lower );
		}

		return $result;
	}

	/**
	 * Suggest related keywords extracted from the content.
	 *
	 * Analyses word frequency (excluding stop-words and the focus keyword
	 * itself) and returns the top candidates as potential related keywords.
	 *
	 * @since 1.0.0
	 *
	 * @param string $keyword  The primary focus keyword.
	 * @param string $content  The text content.
	 *
	 * @return array Up to 5 related keyword suggestions.
	 */
	public function suggest_related( string $keyword, string $content ): array {
		$text = mb_strtolower( wp_strip_all_tags( $content ) );

		if ( '' === trim( $text ) ) {
			return [];
		}

		$stop_words    = $this->get_stop_words();
		$keyword_lower = mb_strtolower( trim( $keyword ) );
		$keyword_parts = array_map( 'trim', explode( ' ', $keyword_lower ) );

		// Tokenize into words (letters, digits, hyphens, apostrophes).
		preg_match_all( '/\b[a-z][a-z\'-]{2,}\b/u', $text, $matches );
		$words = $matches[0] ?? [];

		if ( empty( $words ) ) {
			return [];
		}

		// Count frequency, ignoring stop words and keyword parts.
		$frequency = [];
		foreach ( $words as $word ) {
			$word = rtrim( $word, "'-" );

			if ( mb_strlen( $word ) < 3 ) {
				continue;
			}

			if ( in_array( $word, $stop_words, true ) ) {
				continue;
			}

			if ( in_array( $word, $keyword_parts, true ) ) {
				continue;
			}

			if ( ! isset( $frequency[ $word ] ) ) {
				$frequency[ $word ] = 0;
			}
			$frequency[ $word ]++;
		}

		// Sort by frequency descending.
		arsort( $frequency );

		// Return the top 5.
		return array_slice( array_keys( $frequency ), 0, 5 );
	}

	/**
	 * Get a list of common English stop words.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of stop words.
	 */
	public function get_stop_words(): array {
		return [
			'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i',
			'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
			'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her',
			'she', 'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there',
			'their', 'what', 'so', 'up', 'out', 'if', 'about', 'who', 'get',
			'which', 'go', 'me', 'when', 'make', 'can', 'like', 'time', 'no',
			'just', 'him', 'know', 'take', 'people', 'into', 'year', 'your',
			'good', 'some', 'could', 'them', 'see', 'other', 'than', 'then',
			'now', 'look', 'only', 'come', 'its', 'over', 'think', 'also',
			'back', 'after', 'use', 'two', 'how', 'our', 'work', 'first',
			'well', 'way', 'even', 'new', 'want', 'because', 'any', 'these',
			'give', 'day', 'most', 'us', 'are', 'is', 'was', 'were', 'been',
			'has', 'had', 'may', 'did', 'does', 'should', 'must', 'very',
			'much', 'many', 'more', 'such', 'each', 'own', 'still', 'too',
			'here', 'where', 'why', 'how', 'same', 'being', 'between',
			'while', 'before', 'through', 'during', 'both', 'under', 'never',
			'those', 'since', 'few', 'down', 'every', 'without', 'again',
		];
	}

	/**
	 * Count keyword occurrences in text (case-insensitive, word-boundary aware).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text    The plain text to search.
	 * @param string $keyword The keyword to count.
	 *
	 * @return int Number of occurrences.
	 */
	private function count_keyword( string $text, string $keyword ): int {
		$keyword = trim( $keyword );

		if ( '' === $keyword ) {
			return 0;
		}

		$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/iu';
		$count   = preg_match_all( $pattern, $text );

		return $count ?: 0;
	}
}
