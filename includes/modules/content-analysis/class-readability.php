<?php
/**
 * Readability Analyzer.
 *
 * Evaluates content readability using industry-standard metrics such as
 * Flesch Reading Ease, sentence length, paragraph length, passive voice,
 * transition words, and subheading distribution.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Class Readability
 *
 * Performs readability analysis on a piece of content and returns a scored
 * array of individual checks.
 *
 * @since 1.0.0
 */
final class Readability {

	/**
	 * Common English transition words and phrases.
	 *
	 * @var string[]
	 */
	private const TRANSITION_WORDS = [
		'however',
		'therefore',
		'furthermore',
		'moreover',
		'additionally',
		'consequently',
		'meanwhile',
		'nevertheless',
		'nonetheless',
		'alternatively',
		'accordingly',
		'as a result',
		'for example',
		'for instance',
		'in addition',
		'in contrast',
		'in conclusion',
		'in other words',
		'in particular',
		'in summary',
		'on the other hand',
		'on the contrary',
		'similarly',
		'likewise',
		'hence',
		'thus',
		'instead',
		'besides',
		'certainly',
		'clearly',
		'evidently',
		'obviously',
		'indeed',
		'specifically',
		'notably',
		'significantly',
		'ultimately',
		'above all',
		'after all',
		'as well as',
		'as long as',
		'as soon as',
		'because of',
		'even though',
		'first of all',
		'in fact',
		'rather than',
		'so that',
		'such as',
		'that is',
		'to begin with',
		'to sum up',
		'what is more',
		'to illustrate',
		'to clarify',
		'to put it differently',
		'equally important',
		'first',
		'second',
		'third',
		'finally',
		'next',
		'then',
		'also',
		'again',
		'further',
		'last',
		'still',
		'too',
	];

	/**
	 * Analyse the readability of a piece of content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content HTML content to analyse.
	 *
	 * @return array {
	 *     @type int   $score  Overall readability score (0-100).
	 *     @type array $checks Array of individual check results.
	 * }
	 */
	public function analyze( string $content ): array {
		$text = wp_strip_all_tags( $content );

		$checks = [];

		$checks[] = $this->check_flesch_reading_ease( $text );
		$checks[] = $this->check_sentence_length( $text );
		$checks[] = $this->check_paragraph_length( $text );
		$checks[] = $this->check_passive_voice( $text );
		$checks[] = $this->check_transition_words( $text );
		$checks[] = $this->check_consecutive_sentences( $text );
		$checks[] = $this->check_subheading_distribution( $content );

		return [
			'score'  => Score::calculate( $checks ),
			'checks' => $checks,
		];
	}

	// -------------------------------------------------------------------------
	// Individual Checks
	// -------------------------------------------------------------------------

	/**
	 * Check the Flesch Reading Ease score.
	 *
	 * Formula: 206.835 - 1.015 * (words / sentences) - 84.6 * (syllables / words)
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Check result.
	 */
	public function check_flesch_reading_ease( string $text ): array {
		$sentences = $this->split_sentences( $text );
		$words     = str_word_count( $text );

		$sentence_count = count( $sentences );

		if ( 0 === $words || 0 === $sentence_count ) {
			return $this->build_check(
				'flesch_reading_ease',
				__( 'Flesch Reading Ease', 'seo-ai' ),
				'error',
				__( 'Not enough content to calculate readability.', 'seo-ai' ),
				20,
				0
			);
		}

		// Count total syllables.
		preg_match_all( '/\b\w+\b/u', $text, $word_matches );
		$total_syllables = 0;
		foreach ( $word_matches[0] as $word ) {
			$total_syllables += $this->count_syllables( $word );
		}

		$flesch = 206.835
			- 1.015 * ( $words / $sentence_count )
			- 84.6 * ( $total_syllables / $words );

		$flesch = max( 0, min( 100, $flesch ) );
		$flesch = round( $flesch, 1 );

		if ( $flesch > 60 ) {
			return $this->build_check(
				'flesch_reading_ease',
				__( 'Flesch Reading Ease', 'seo-ai' ),
				'good',
				/* translators: %s: Flesch score */
				sprintf( __( 'Your Flesch Reading Ease score is %s, which is considered easy to read.', 'seo-ai' ), $flesch ),
				20,
				100
			);
		}

		if ( $flesch >= 40 ) {
			return $this->build_check(
				'flesch_reading_ease',
				__( 'Flesch Reading Ease', 'seo-ai' ),
				'warning',
				/* translators: %s: Flesch score */
				sprintf( __( 'Your Flesch Reading Ease score is %s. Try using shorter sentences and simpler words.', 'seo-ai' ), $flesch ),
				20,
				60
			);
		}

		return $this->build_check(
			'flesch_reading_ease',
			__( 'Flesch Reading Ease', 'seo-ai' ),
			'error',
			/* translators: %s: Flesch score */
			sprintf( __( 'Your Flesch Reading Ease score is %s, which is difficult to read. Simplify your sentences and vocabulary.', 'seo-ai' ), $flesch ),
			20,
			20
		);
	}

	/**
	 * Check average sentence length.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Check result.
	 */
	public function check_sentence_length( string $text ): array {
		$sentences = $this->split_sentences( $text );
		$count     = count( $sentences );

		if ( 0 === $count ) {
			return $this->build_check(
				'sentence_length',
				__( 'Sentence Length', 'seo-ai' ),
				'error',
				__( 'No sentences found in the content.', 'seo-ai' ),
				15,
				0
			);
		}

		$total_words = 0;
		foreach ( $sentences as $sentence ) {
			$total_words += str_word_count( $sentence );
		}

		$avg = $total_words / $count;

		if ( $avg < 20 ) {
			return $this->build_check(
				'sentence_length',
				__( 'Sentence Length', 'seo-ai' ),
				'good',
				/* translators: %s: average word count */
				sprintf( __( 'Average sentence length is %.1f words. Good job keeping it readable!', 'seo-ai' ), $avg ),
				15,
				100
			);
		}

		if ( $avg <= 25 ) {
			return $this->build_check(
				'sentence_length',
				__( 'Sentence Length', 'seo-ai' ),
				'warning',
				/* translators: %s: average word count */
				sprintf( __( 'Average sentence length is %.1f words. Try to keep sentences under 20 words for better readability.', 'seo-ai' ), $avg ),
				15,
				60
			);
		}

		return $this->build_check(
			'sentence_length',
			__( 'Sentence Length', 'seo-ai' ),
			'error',
			/* translators: %s: average word count */
			sprintf( __( 'Average sentence length is %.1f words. Break long sentences into shorter ones.', 'seo-ai' ), $avg ),
			15,
			20
		);
	}

	/**
	 * Check average paragraph length.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Check result.
	 */
	public function check_paragraph_length( string $text ): array {
		$paragraphs = $this->split_paragraphs( $text );
		$count      = count( $paragraphs );

		if ( 0 === $count ) {
			return $this->build_check(
				'paragraph_length',
				__( 'Paragraph Length', 'seo-ai' ),
				'error',
				__( 'No paragraphs found in the content.', 'seo-ai' ),
				15,
				0
			);
		}

		$total_words = 0;
		foreach ( $paragraphs as $paragraph ) {
			$total_words += str_word_count( $paragraph );
		}

		$avg = $total_words / $count;

		if ( $avg < 150 ) {
			return $this->build_check(
				'paragraph_length',
				__( 'Paragraph Length', 'seo-ai' ),
				'good',
				/* translators: %s: average word count */
				sprintf( __( 'Average paragraph length is %.0f words. Well structured!', 'seo-ai' ), $avg ),
				15,
				100
			);
		}

		if ( $avg <= 200 ) {
			return $this->build_check(
				'paragraph_length',
				__( 'Paragraph Length', 'seo-ai' ),
				'warning',
				/* translators: %s: average word count */
				sprintf( __( 'Average paragraph length is %.0f words. Consider breaking longer paragraphs into smaller ones.', 'seo-ai' ), $avg ),
				15,
				60
			);
		}

		return $this->build_check(
			'paragraph_length',
			__( 'Paragraph Length', 'seo-ai' ),
			'error',
			/* translators: %s: average word count */
			sprintf( __( 'Average paragraph length is %.0f words. Paragraphs should be under 150 words for readability.', 'seo-ai' ), $avg ),
			15,
			20
		);
	}

	/**
	 * Check percentage of passive voice usage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Check result.
	 */
	public function check_passive_voice( string $text ): array {
		$sentences = $this->split_sentences( $text );
		$count     = count( $sentences );

		if ( 0 === $count ) {
			return $this->build_check(
				'passive_voice',
				__( 'Passive Voice', 'seo-ai' ),
				'error',
				__( 'No sentences found to analyse.', 'seo-ai' ),
				15,
				0
			);
		}

		$passive_count = 0;
		foreach ( $sentences as $sentence ) {
			if ( $this->is_passive( $sentence ) ) {
				$passive_count++;
			}
		}

		$percentage = ( $passive_count / $count ) * 100;

		if ( $percentage < 10 ) {
			return $this->build_check(
				'passive_voice',
				__( 'Passive Voice', 'seo-ai' ),
				'good',
				/* translators: %s: percentage */
				sprintf( __( '%.1f%% of sentences use passive voice. Excellent use of active voice!', 'seo-ai' ), $percentage ),
				15,
				100
			);
		}

		if ( $percentage <= 15 ) {
			return $this->build_check(
				'passive_voice',
				__( 'Passive Voice', 'seo-ai' ),
				'warning',
				/* translators: %s: percentage */
				sprintf( __( '%.1f%% of sentences use passive voice. Try to reduce passive constructions.', 'seo-ai' ), $percentage ),
				15,
				60
			);
		}

		return $this->build_check(
			'passive_voice',
			__( 'Passive Voice', 'seo-ai' ),
			'error',
			/* translators: %s: percentage */
			sprintf( __( '%.1f%% of sentences use passive voice. Rewrite them in active voice for better readability.', 'seo-ai' ), $percentage ),
			15,
			20
		);
	}

	/**
	 * Check usage of transition words.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Check result.
	 */
	public function check_transition_words( string $text ): array {
		$sentences = $this->split_sentences( $text );
		$count     = count( $sentences );

		if ( 0 === $count ) {
			return $this->build_check(
				'transition_words',
				__( 'Transition Words', 'seo-ai' ),
				'error',
				__( 'No sentences found to analyse.', 'seo-ai' ),
				10,
				0
			);
		}

		$with_transition = 0;
		foreach ( $sentences as $sentence ) {
			if ( $this->has_transition_word( $sentence ) ) {
				$with_transition++;
			}
		}

		$percentage = ( $with_transition / $count ) * 100;

		if ( $percentage > 30 ) {
			return $this->build_check(
				'transition_words',
				__( 'Transition Words', 'seo-ai' ),
				'good',
				/* translators: %s: percentage */
				sprintf( __( '%.1f%% of sentences contain transition words. Great flow!', 'seo-ai' ), $percentage ),
				10,
				100
			);
		}

		if ( $percentage >= 20 ) {
			return $this->build_check(
				'transition_words',
				__( 'Transition Words', 'seo-ai' ),
				'warning',
				/* translators: %s: percentage */
				sprintf( __( '%.1f%% of sentences contain transition words. Try adding more for better flow.', 'seo-ai' ), $percentage ),
				10,
				60
			);
		}

		return $this->build_check(
			'transition_words',
			__( 'Transition Words', 'seo-ai' ),
			'error',
			/* translators: %s: percentage */
			sprintf( __( 'Only %.1f%% of sentences contain transition words. Use words like "however", "therefore", "furthermore" to improve flow.', 'seo-ai' ), $percentage ),
			10,
			20
		);
	}

	/**
	 * Check for consecutive sentences starting with the same word.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Check result.
	 */
	public function check_consecutive_sentences( string $text ): array {
		$sentences = $this->split_sentences( $text );

		if ( count( $sentences ) < 2 ) {
			return $this->build_check(
				'consecutive_sentences',
				__( 'Consecutive Sentences', 'seo-ai' ),
				'good',
				__( 'Not enough sentences to check for consecutive patterns.', 'seo-ai' ),
				10,
				100
			);
		}

		$occurrences   = 0;
		$prev_word     = '';
		$streak        = 1;

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( '' === $sentence ) {
				continue;
			}

			// Get the first word.
			preg_match( '/^\w+/u', $sentence, $matches );
			$first_word = isset( $matches[0] ) ? mb_strtolower( $matches[0] ) : '';

			if ( '' === $first_word ) {
				$prev_word = '';
				$streak    = 1;
				continue;
			}

			if ( $first_word === $prev_word ) {
				$streak++;
				if ( $streak > 3 ) {
					$occurrences++;
				}
			} else {
				// If the previous streak was exactly 3, that still counts as a violation.
				if ( 3 === $streak ) {
					$occurrences++;
				}
				$streak = 1;
			}

			$prev_word = $first_word;
		}

		// Check final streak.
		if ( $streak >= 3 && 0 === $occurrences ) {
			$occurrences++;
		}

		if ( 0 === $occurrences ) {
			return $this->build_check(
				'consecutive_sentences',
				__( 'Consecutive Sentences', 'seo-ai' ),
				'good',
				__( 'No groups of 3+ consecutive sentences start with the same word.', 'seo-ai' ),
				10,
				100
			);
		}

		if ( $occurrences <= 2 ) {
			return $this->build_check(
				'consecutive_sentences',
				__( 'Consecutive Sentences', 'seo-ai' ),
				'warning',
				/* translators: %d: number of occurrences */
				sprintf( __( 'Found %d group(s) of consecutive sentences starting with the same word. Vary your sentence beginnings.', 'seo-ai' ), $occurrences ),
				10,
				60
			);
		}

		return $this->build_check(
			'consecutive_sentences',
			__( 'Consecutive Sentences', 'seo-ai' ),
			'error',
			/* translators: %d: number of occurrences */
			sprintf( __( 'Found %d groups of consecutive sentences starting with the same word. This hurts readability.', 'seo-ai' ), $occurrences ),
			10,
			20
		);
	}

	/**
	 * Check subheading distribution (content blocks between headings).
	 *
	 * Content after each heading should be under 300 words.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML content.
	 *
	 * @return array Check result.
	 */
	public function check_subheading_distribution( string $html ): array {
		// Split by headings h2-h6.
		$parts = preg_split( '/<h[2-6][^>]*>/i', $html );

		if ( ! $parts || count( $parts ) <= 1 ) {
			$word_count = str_word_count( wp_strip_all_tags( $html ) );

			if ( $word_count < 300 ) {
				return $this->build_check(
					'subheading_distribution',
					__( 'Subheading Distribution', 'seo-ai' ),
					'good',
					__( 'Content is short enough that subheadings are not required.', 'seo-ai' ),
					15,
					100
				);
			}

			return $this->build_check(
				'subheading_distribution',
				__( 'Subheading Distribution', 'seo-ai' ),
				'error',
				__( 'No subheadings found. Add H2-H6 headings to break up your content.', 'seo-ai' ),
				15,
				20
			);
		}

		$total_sections  = 0;
		$over_300        = 0;

		foreach ( $parts as $part ) {
			$clean      = wp_strip_all_tags( $part );
			$word_count = str_word_count( $clean );

			// Skip empty sections or very short preambles.
			if ( $word_count < 10 ) {
				continue;
			}

			$total_sections++;

			if ( $word_count > 300 ) {
				$over_300++;
			}
		}

		if ( 0 === $total_sections ) {
			return $this->build_check(
				'subheading_distribution',
				__( 'Subheading Distribution', 'seo-ai' ),
				'good',
				__( 'Content sections are well distributed.', 'seo-ai' ),
				15,
				100
			);
		}

		$over_ratio = $over_300 / $total_sections;

		if ( 0 === $over_300 ) {
			return $this->build_check(
				'subheading_distribution',
				__( 'Subheading Distribution', 'seo-ai' ),
				'good',
				__( 'All content sections are under 300 words. Well structured!', 'seo-ai' ),
				15,
				100
			);
		}

		if ( $over_ratio < 0.5 ) {
			return $this->build_check(
				'subheading_distribution',
				__( 'Subheading Distribution', 'seo-ai' ),
				'warning',
				/* translators: %d: number of sections over 300 words */
				sprintf( __( '%d content section(s) exceed 300 words. Consider adding more subheadings.', 'seo-ai' ), $over_300 ),
				15,
				60
			);
		}

		return $this->build_check(
			'subheading_distribution',
			__( 'Subheading Distribution', 'seo-ai' ),
			'error',
			/* translators: %1$d: sections over 300, %2$d: total sections */
			sprintf( __( '%1$d of %2$d content sections exceed 300 words. Break them up with additional subheadings.', 'seo-ai' ), $over_300, $total_sections ),
			15,
			20
		);
	}

	// -------------------------------------------------------------------------
	// Helper Methods
	// -------------------------------------------------------------------------

	/**
	 * Estimate the number of syllables in an English word.
	 *
	 * Uses a heuristic based on vowel groups, common suffixes, and
	 * the silent-e rule.
	 *
	 * @since 1.0.0
	 *
	 * @param string $word The word to analyse.
	 *
	 * @return int Estimated syllable count (minimum 1).
	 */
	public function count_syllables( string $word ): int {
		$word = strtolower( trim( $word ) );

		if ( strlen( $word ) <= 2 ) {
			return 1;
		}

		// Remove non-alpha characters.
		$word = preg_replace( '/[^a-z]/', '', $word );

		if ( '' === $word ) {
			return 1;
		}

		// Count vowel groups.
		$count = 0;
		$count += preg_match_all( '/[aeiouy]+/i', $word );

		// Subtract for silent 'e' at end.
		if ( preg_match( '/[^l]e$/i', $word ) ) {
			$count--;
		}

		// Subtract for common silent-e suffixes.
		$silent_endings = [ 'es', 'ed' ];
		foreach ( $silent_endings as $ending ) {
			if ( str_ends_with( $word, $ending ) && strlen( $word ) > 3 ) {
				// Only subtract if the preceding character is not a vowel.
				$before = substr( $word, -( strlen( $ending ) + 1 ), 1 );
				if ( ! preg_match( '/[aeiouy]/i', $before ) ) {
					$count--;
				}
			}
		}

		// Add for common multi-syllable endings.
		if ( preg_match( '/(ia|iu|io|ie[^d]|eous|ious|uous)$/i', $word ) ) {
			$count++;
		}

		return max( 1, $count );
	}

	/**
	 * Split text into individual sentences.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Array of sentence strings.
	 */
	public function split_sentences( string $text ): array {
		$text = trim( $text );

		if ( '' === $text ) {
			return [];
		}

		// Split on sentence-ending punctuation followed by whitespace or end of string.
		$sentences = preg_split(
			'/(?<=[.!?])\s+/',
			$text,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		if ( ! $sentences ) {
			return [ $text ];
		}

		return array_values( array_filter( $sentences, function ( string $s ): bool {
			return '' !== trim( $s );
		} ) );
	}

	/**
	 * Split text into paragraphs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 *
	 * @return array Array of paragraph strings.
	 */
	public function split_paragraphs( string $text ): array {
		$text = trim( $text );

		if ( '' === $text ) {
			return [];
		}

		// Split on double newlines (typical paragraph breaks).
		$paragraphs = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! $paragraphs ) {
			return [ $text ];
		}

		return array_values( array_filter( $paragraphs, function ( string $p ): bool {
			return '' !== trim( $p );
		} ) );
	}

	/**
	 * Determine whether a sentence uses passive voice.
	 *
	 * Detects patterns like "is/was/were/been/being/are/get/got + past participle".
	 *
	 * @since 1.0.0
	 *
	 * @param string $sentence The sentence to check.
	 *
	 * @return bool True if the sentence appears to use passive voice.
	 */
	public function is_passive( string $sentence ): bool {
		// Match auxiliary verb + optional adverbs + past participle (ending in -ed, -en, -t, -wn, -ng).
		$auxiliaries = 'is|are|was|were|been|being|be|get|gets|got|gotten';
		$pattern     = '/\b(?:' . $auxiliaries . ')\b\s+(?:\w+\s+){0,3}\b\w+(?:ed|en|wn|ung|ght)\b/i';

		return (bool) preg_match( $pattern, $sentence );
	}

	/**
	 * Check whether a sentence contains at least one transition word or phrase.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sentence The sentence to check.
	 *
	 * @return bool True if a transition word/phrase is found.
	 */
	public function has_transition_word( string $sentence ): bool {
		$sentence_lower = mb_strtolower( $sentence );

		foreach ( self::TRANSITION_WORDS as $word ) {
			// Use word boundaries for single words, looser match for phrases.
			if ( str_contains( $word, ' ' ) ) {
				if ( str_contains( $sentence_lower, $word ) ) {
					return true;
				}
			} else {
				if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/i', $sentence_lower ) ) {
					return true;
				}
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Private Helpers
	// -------------------------------------------------------------------------

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
