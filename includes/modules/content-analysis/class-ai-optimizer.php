<?php
/**
 * AI-Powered SEO Optimizer.
 *
 * Uses the configured AI provider to generate and optimise meta titles,
 * meta descriptions, focus keywords, alt text, schema types, and content
 * improvement suggestions.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

use SeoAi\Providers\Provider_Manager;

/**
 * Class AI_Optimizer
 *
 * Each public method obtains the active AI provider from the Provider_Manager,
 * sends a purpose-built prompt, and returns the cleaned result.
 *
 * @since 1.0.0
 */
final class AI_Optimizer {

	/**
	 * Post meta key prefix used by the plugin.
	 *
	 * @var string
	 */
	private const META_PREFIX = '_seo_ai_';

	/**
	 * Provider manager instance.
	 *
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider_Manager $provider_manager Provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->provider_manager = $provider_manager;
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Generate an SEO-optimised meta title.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    The post content (HTML is acceptable).
	 * @param string $keyword    Optional focus keyword.
	 * @param int    $max_length Maximum title length in characters.
	 *
	 * @return string The generated meta title.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function generate_meta_title( string $content, string $keyword = '', int $max_length = 60 ): string {
		$system = sprintf(
			'You are an expert SEO copywriter. Generate an SEO-optimized meta title for the content provided. '
			. 'The title MUST be between 50 and %d characters. '
			. '%s'
			. 'The title should be compelling, accurately reflect the content, and encourage clicks. '
			. 'Return ONLY the title text, nothing else. No quotes, no explanation.',
			$max_length,
			'' !== $keyword
				? sprintf( 'Include the focus keyword "%s" naturally, preferably near the beginning. ', $keyword )
				: ''
		);

		$user = $this->prepare_content_prompt( $content );

		$result = $this->chat( $system, $user, [ 'max_tokens' => 100 ] );

		return $this->sanitize_single_line( $result, $max_length );
	}

	/**
	 * Generate an SEO-optimised meta description.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    The post content.
	 * @param string $keyword    Optional focus keyword.
	 * @param int    $max_length Maximum description length in characters.
	 *
	 * @return string The generated meta description.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function generate_meta_description( string $content, string $keyword = '', int $max_length = 160 ): string {
		$system = sprintf(
			'You are an expert SEO copywriter. Generate an SEO-optimized meta description for the content provided. '
			. 'The description MUST be between 120 and %d characters. '
			. '%s'
			. 'Make it compelling and action-oriented to encourage clicks from search results. '
			. 'Return ONLY the description text, nothing else. No quotes, no explanation.',
			$max_length,
			'' !== $keyword
				? sprintf( 'Include the focus keyword "%s" naturally. ', $keyword )
				: ''
		);

		$user = $this->prepare_content_prompt( $content );

		$result = $this->chat( $system, $user, [ 'max_tokens' => 200 ] );

		return $this->sanitize_single_line( $result, $max_length );
	}

	/**
	 * Suggest the best focus keyword for a piece of content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content.
	 *
	 * @return string The suggested focus keyword or key-phrase.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function suggest_focus_keyword( string $content ): string {
		$system = 'You are an expert SEO analyst. Analyze the content provided and determine the single most '
			. 'important focus keyword or key-phrase (2-4 words max) that this content should target. '
			. 'Consider search intent, search volume potential, and relevance. '
			. 'Return ONLY the keyword or key-phrase in lowercase, nothing else. No quotes, no explanation.';

		$user = $this->prepare_content_prompt( $content );

		$result = $this->chat( $system, $user, [ 'max_tokens' => 50 ] );

		return mb_strtolower( $this->sanitize_single_line( $result, 100 ) );
	}

	/**
	 * Suggest content improvements based on failing SEO checks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content        The post content.
	 * @param string $keyword        Focus keyword.
	 * @param array  $failing_checks Array of failing check result arrays.
	 *
	 * @return array List of improvement suggestion strings.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function suggest_improvements( string $content, string $keyword, array $failing_checks ): array {
		if ( empty( $failing_checks ) ) {
			return [];
		}

		$issues = [];
		foreach ( $failing_checks as $check ) {
			$issues[] = sprintf( '- %s: %s', $check['label'] ?? $check['id'] ?? 'Unknown', $check['message'] ?? '' );
		}

		$system = 'You are an expert SEO consultant. The user has content with several SEO issues. '
			. 'For each issue listed, provide a specific, actionable improvement suggestion. '
			. 'Return each suggestion on a new line, numbered. Be concise but specific. '
			. 'Do not include any preamble or closing remarks, only the numbered suggestions.';

		$user = sprintf(
			"Focus keyword: %s\n\nIssues found:\n%s\n\nContent summary (first 500 words):\n%s",
			$keyword,
			implode( "\n", $issues ),
			wp_trim_words( wp_strip_all_tags( $content ), 500, '' )
		);

		$result = $this->chat( $system, $user, [ 'max_tokens' => 800 ] );

		return $this->parse_numbered_list( $result );
	}

	/**
	 * Detect the most appropriate Schema.org type for the content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content   The post content.
	 * @param string $post_type WordPress post type slug.
	 *
	 * @return string Schema.org type name.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function detect_schema_type( string $content, string $post_type = 'post' ): string {
		$allowed_types = [
			'Article',
			'BlogPosting',
			'NewsArticle',
			'HowTo',
			'FAQPage',
			'Product',
			'Recipe',
			'Event',
			'JobPosting',
			'WebPage',
		];

		$system = sprintf(
			'You are an expert in structured data and Schema.org. Based on the content provided, determine which '
			. 'Schema.org type is most appropriate. '
			. 'The WordPress post type is "%s". '
			. 'Choose from ONLY these options: %s. '
			. 'Return ONLY the type name, nothing else. No quotes, no explanation.',
			$post_type,
			implode( ', ', $allowed_types )
		);

		$user = $this->prepare_content_prompt( $content );

		$result = $this->chat( $system, $user, [ 'max_tokens' => 20 ] );
		$result = trim( $result );

		// Validate against allowed types (case-insensitive match).
		foreach ( $allowed_types as $type ) {
			if ( 0 === strcasecmp( $result, $type ) ) {
				return $type;
			}
		}

		// Default fallback.
		return 'page' === $post_type ? 'WebPage' : 'Article';
	}

	/**
	 * Generate alt text for an image.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename Image filename (e.g. 'my-photo.jpg').
	 * @param string $context  Optional surrounding content context.
	 *
	 * @return string Generated alt text.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function generate_alt_text( string $filename, string $context = '' ): string {
		$system = 'You are an expert in web accessibility and SEO. Generate a concise, descriptive alt text for an image. '
			. 'The alt text should be under 125 characters, descriptive, and natural. '
			. 'Do not start with "Image of" or "Picture of". '
			. 'Return ONLY the alt text, nothing else. No quotes, no explanation.';

		$user = sprintf( 'Image filename: %s', $filename );

		if ( '' !== $context ) {
			$user .= sprintf( "\n\nContent context:\n%s", wp_trim_words( wp_strip_all_tags( $context ), 100, '' ) );
		}

		$result = $this->chat( $system, $user, [ 'max_tokens' => 80 ] );

		return $this->sanitize_single_line( $result, 125 );
	}

	/**
	 * Optimise multiple SEO fields for a post in one pass.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id The post ID.
	 * @param array $fields  Fields to optimise. Supported: 'title', 'description', 'keyword', 'schema'.
	 *
	 * @return array Associative array of field => generated value.
	 *
	 * @throws \RuntimeException When no AI provider is available or the post does not exist.
	 */
	public function optimize_post( int $post_id, array $fields = [ 'title', 'description', 'keyword', 'schema' ] ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d not found.', 'seo-ai' ),
					$post_id
				)
			);
		}

		$content   = $post->post_content;
		$post_type = $post->post_type;

		// Determine the focus keyword first (other fields may depend on it).
		$keyword = (string) get_post_meta( $post_id, self::META_PREFIX . 'keyword', true );

		$results = [];

		if ( in_array( 'keyword', $fields, true ) ) {
			$keyword            = $this->suggest_focus_keyword( $content );
			$results['keyword'] = $keyword;

			update_post_meta( $post_id, self::META_PREFIX . 'keyword', $keyword );
		}

		if ( in_array( 'title', $fields, true ) ) {
			$title            = $this->generate_meta_title( $content, $keyword );
			$results['title'] = $title;

			update_post_meta( $post_id, self::META_PREFIX . 'title', $title );
		}

		if ( in_array( 'description', $fields, true ) ) {
			$description            = $this->generate_meta_description( $content, $keyword );
			$results['description'] = $description;

			update_post_meta( $post_id, self::META_PREFIX . 'description', $description );
		}

		if ( in_array( 'schema', $fields, true ) ) {
			$schema            = $this->detect_schema_type( $content, $post_type );
			$results['schema'] = $schema;

			update_post_meta( $post_id, self::META_PREFIX . 'schema_type', $schema );
		}

		return $results;
	}

	// =========================================================================
	// Private Helpers
	// =========================================================================

	/**
	 * Send a chat request through the active AI provider.
	 *
	 * @param string $system_prompt System instruction.
	 * @param string $user_prompt   User message.
	 * @param array  $options       Additional chat options.
	 *
	 * @return string The AI response text.
	 *
	 * @throws \RuntimeException When no provider is configured.
	 */
	private function chat( string $system_prompt, string $user_prompt, array $options = [] ): string {
		$provider = $this->provider_manager->get_active_provider();

		if ( ! $provider ) {
			throw new \RuntimeException(
				__( 'No AI provider is configured. Please set up an AI provider in SEO AI settings.', 'seo-ai' )
			);
		}

		// Prepend custom prompt if configured.
		$settings = get_option( 'seo_ai_providers', [] );
		$active   = $settings['active_provider'] ?? '';
		$custom   = trim( $settings[ $active ]['custom_prompt'] ?? '' );
		if ( '' !== $custom ) {
			$system_prompt = $custom . "\n\n" . $system_prompt;
		}

		$defaults = [
			'temperature' => 0.7,
			'max_tokens'  => 200,
		];

		$options = wp_parse_args( $options, $defaults );

		return $provider->chat( $system_prompt, $user_prompt, $options );
	}

	/**
	 * Prepare a content string for use in an AI prompt.
	 *
	 * Strips HTML and trims to a reasonable length to stay within token limits.
	 *
	 * @param string $content Raw post content.
	 *
	 * @return string Cleaned content for the prompt.
	 */
	private function prepare_content_prompt( string $content ): string {
		$text = wp_strip_all_tags( $content );
		$text = wp_trim_words( $text, 1000, '' );

		if ( '' === trim( $text ) ) {
			return 'No content available.';
		}

		return $text;
	}

	/**
	 * Sanitise an AI response to a single line and enforce a maximum length.
	 *
	 * Removes wrapping quotes, newlines, and extra whitespace.
	 *
	 * @param string $text       The raw AI response.
	 * @param int    $max_length Maximum allowed character length.
	 *
	 * @return string Sanitised text.
	 */
	private function sanitize_single_line( string $text, int $max_length ): string {
		// Remove wrapping quotes (single and double).
		$text = trim( $text );
		$text = trim( $text, "\"'" );

		// Collapse whitespace and newlines.
		$text = preg_replace( '/\s+/', ' ', $text ) ?: $text;
		$text = trim( $text );

		// Enforce maximum length.
		if ( mb_strlen( $text ) > $max_length ) {
			$text = mb_substr( $text, 0, $max_length );

			// Try to avoid cutting mid-word.
			$last_space = mb_strrpos( $text, ' ' );
			if ( $last_space !== false && $last_space > $max_length * 0.7 ) {
				$text = mb_substr( $text, 0, $last_space );
			}
		}

		return sanitize_text_field( $text );
	}

	/**
	 * Parse a numbered list from an AI response into an array.
	 *
	 * Handles formats like "1. First item", "1) First item", etc.
	 *
	 * @param string $text Raw AI response.
	 *
	 * @return array Array of suggestion strings.
	 */
	private function parse_numbered_list( string $text ): array {
		$lines       = explode( "\n", $text );
		$suggestions = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			// Strip leading numbers and punctuation (e.g. "1. ", "2) ", "- ").
			$clean = preg_replace( '/^\d+[\.\)]\s*/', '', $line );
			$clean = preg_replace( '/^[-*]\s*/', '', $clean ?? $line );
			$clean = trim( $clean ?? '' );

			if ( '' !== $clean ) {
				$suggestions[] = sanitize_text_field( $clean );
			}
		}

		return $suggestions;
	}
}
