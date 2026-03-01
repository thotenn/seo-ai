<?php
/**
 * Search Intent Detector.
 *
 * Uses the configured AI provider to classify the search intent behind
 * a focus keyword and its associated content. Provides intent-specific
 * SEO suggestions to improve content alignment with user expectations.
 *
 * @package SeoAi\Modules\Content_Analysis
 * @since   0.5.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Content_Analysis;

defined( 'ABSPATH' ) || exit;

use SeoAi\Providers\Provider_Manager;

/**
 * Class Search_Intent
 *
 * Detects search intent (informational, transactional, navigational, commercial)
 * for a given keyword and content pair using the active AI provider, and returns
 * actionable SEO suggestions based on the detected intent.
 *
 * @since 0.5.0
 */
final class Search_Intent {

	/**
	 * Post meta key for storing detected search intent.
	 *
	 * @var string
	 */
	private const META_KEY = '_seo_ai_search_intent';

	/**
	 * Allowed intent types.
	 *
	 * @var string[]
	 */
	private const INTENT_TYPES = [
		'informational',
		'transactional',
		'navigational',
		'commercial',
	];

	/**
	 * Provider manager instance.
	 *
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Provider_Manager $provider_manager Provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->provider_manager = $provider_manager;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'seo_ai/metabox_saved', [ $this, 'on_metabox_saved' ], 10, 2 );
	}

	/**
	 * Auto-detect search intent after metabox save when a focus keyword is set.
	 *
	 * @since 0.5.0
	 *
	 * @param int   $post_id   The post ID.
	 * @param array $sanitized The sanitized meta data that was saved.
	 *
	 * @return void
	 */
	public function on_metabox_saved( int $post_id, array $sanitized ): void {
		if ( empty( $sanitized['focus_keyword'] ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$content = $post->post_content;
		$keyword = $sanitized['focus_keyword'];

		try {
			$intent = $this->detect_intent( $content, $keyword );
			update_post_meta( $post_id, self::META_KEY, $intent );
		} catch ( \RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Silently fail — intent detection is non-critical.
		}
	}

	/**
	 * Detect search intent for a keyword and content pair using AI.
	 *
	 * Classifies the keyword into one of four intent types:
	 * informational, transactional, navigational, or commercial.
	 *
	 * @since 0.5.0
	 *
	 * @param string $content The post content (HTML is acceptable).
	 * @param string $keyword The focus keyword to classify.
	 *
	 * @return string One of: informational, transactional, navigational, commercial.
	 *
	 * @throws \RuntimeException When no AI provider is available.
	 */
	public function detect_intent( string $content, string $keyword ): string {
		$system = 'You are an expert SEO analyst specialising in search intent classification. '
			. 'Analyse the focus keyword and content excerpt provided to determine the primary search intent. '
			. 'Classify the intent into exactly ONE of these categories: informational, transactional, navigational, commercial. '
			. "\n\n"
			. "Definitions:\n"
			. "- informational: The user wants to learn something or find an answer (e.g. \"how to\", \"what is\", guides, tutorials).\n"
			. "- transactional: The user wants to complete a specific action or purchase (e.g. \"buy\", \"download\", \"sign up\", pricing pages).\n"
			. "- navigational: The user wants to find a specific website or page (e.g. brand names, specific product names, login pages).\n"
			. "- commercial: The user is researching before a purchase decision (e.g. \"best\", \"review\", \"vs\", comparisons).\n"
			. "\n"
			. 'Return ONLY the intent category as a single lowercase word. No quotes, no explanation, no punctuation.';

		$excerpt = wp_trim_words( wp_strip_all_tags( $content ), 500, '' );

		if ( '' === trim( $excerpt ) ) {
			$excerpt = 'No content available.';
		}

		$user = sprintf(
			"Focus keyword: %s\n\nContent excerpt:\n%s",
			$keyword,
			$excerpt
		);

		$result = $this->chat( $system, $user, [ 'max_tokens' => 20 ] );
		$result = mb_strtolower( trim( $result ) );

		// Validate against allowed intent types.
		if ( in_array( $result, self::INTENT_TYPES, true ) ) {
			return $result;
		}

		// Attempt partial match in case the AI returned extra text.
		foreach ( self::INTENT_TYPES as $type ) {
			if ( false !== mb_strpos( $result, $type ) ) {
				return $type;
			}
		}

		// Default fallback.
		return 'informational';
	}

	/**
	 * Get SEO suggestions based on the detected search intent.
	 *
	 * Returns an array of actionable recommendation strings tailored
	 * to the specific intent type.
	 *
	 * @since 0.5.0
	 *
	 * @param string $intent The detected intent type.
	 *
	 * @return array List of SEO suggestion strings.
	 */
	public function get_intent_suggestions( string $intent ): array {
		return match ( $intent ) {
			'informational' => [
				__( 'Create comprehensive, long-form content (1,500+ words) that thoroughly answers the user\'s question.', 'seo-ai' ),
				__( 'Add a FAQ section with structured data (FAQPage schema) to target featured snippets.', 'seo-ai' ),
				__( 'Use how-to formatting with clear step-by-step instructions and HowTo schema markup.', 'seo-ai' ),
				__( 'Include a table of contents for easy navigation of longer articles.', 'seo-ai' ),
				__( 'Add supporting visuals, diagrams, or infographics to enhance explanations.', 'seo-ai' ),
			],
			'transactional' => [
				__( 'Include clear, prominent calls-to-action (CTAs) above the fold and throughout the content.', 'seo-ai' ),
				__( 'Display pricing information clearly and consider adding Product or Offer schema markup.', 'seo-ai' ),
				__( 'Add trust signals such as reviews, testimonials, security badges, and guarantees.', 'seo-ai' ),
				__( 'Optimise the page for conversion with a streamlined layout and minimal distractions.', 'seo-ai' ),
				__( 'Ensure the meta description includes action-oriented language and a value proposition.', 'seo-ai' ),
			],
			'navigational' => [
				__( 'Ensure brand name and key identifiers are prominently mentioned in the title and headings.', 'seo-ai' ),
				__( 'Implement proper sitelinks-friendly structure with clear internal navigation.', 'seo-ai' ),
				__( 'Set the canonical URL correctly to avoid duplicate content issues.', 'seo-ai' ),
				__( 'Add Organisation or WebSite schema with a SearchAction for sitelinks search box.', 'seo-ai' ),
				__( 'Ensure consistent NAP (Name, Address, Phone) information across the site.', 'seo-ai' ),
			],
			'commercial' => [
				__( 'Include detailed comparisons with competing products or alternatives.', 'seo-ai' ),
				__( 'Add genuine reviews, ratings, and user testimonials with Review schema markup.', 'seo-ai' ),
				__( 'Present clear pros and cons lists to help users make informed decisions.', 'seo-ai' ),
				__( 'Use comparison tables to highlight key feature differences at a glance.', 'seo-ai' ),
				__( 'Include expert opinions or authoritative sources to build trust and credibility.', 'seo-ai' ),
			],
			default => [],
		};
	}

	// =========================================================================
	// Private Helpers
	// =========================================================================

	/**
	 * Send a chat request through the active AI provider.
	 *
	 * @since 0.5.0
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
}
