<?php
/**
 * Inline AI REST Controller.
 *
 * Handles inline AI writing actions for the Gutenberg block editor.
 * Supports: write_more, improve, summarize, fix_grammar, simplify,
 * add_keywords, and custom free-form commands.
 *
 * @package SeoAi\Rest
 * @since   0.6.0
 */

declare(strict_types=1);

namespace SeoAi\Rest;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SeoAi\Providers\Provider_Manager;

/**
 * Class Inline_Ai_Controller
 *
 * Handles `/seo-ai/v1/ai/inline` endpoint for block editor AI actions.
 *
 * @since 0.6.0
 */
final class Inline_Ai_Controller extends Rest_Controller {

	/**
	 * System prompts for each action type.
	 *
	 * @var array<string, string>
	 */
	private const ACTION_PROMPTS = [
		'write_more'   => 'You are an expert content writer. Continue writing from the given text, maintaining the same style, tone, and context. Write 2-3 additional paragraphs that flow naturally from the existing content. Return ONLY the new content to append, no introduction or explanation.',
		'improve'      => 'You are an expert editor. Rewrite the given text to be clearer, more engaging, and better written. Maintain the same meaning and key points. Return ONLY the improved text, nothing else.',
		'summarize'    => 'You are an expert content editor. Summarize the given text in 1-2 concise sentences that capture the key points. Return ONLY the summary, nothing else.',
		'fix_grammar'  => 'You are a professional proofreader. Fix all grammar, spelling, and punctuation errors in the given text. Maintain the original meaning and style. Return ONLY the corrected text, nothing else.',
		'simplify'     => 'You are a content accessibility expert. Rewrite the given text to be simpler and easier to understand. Use shorter sentences and common words. Target a 6th-grade reading level. Return ONLY the simplified text, nothing else.',
		'add_keywords' => 'You are an SEO copywriter. Rewrite the given text to naturally include the focus keyword without keyword stuffing. The text should read naturally while improving keyword relevance. Return ONLY the rewritten text, nothing else.',
	];

	/**
	 * Register routes for inline AI operations.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/ai/inline',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_inline_action' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'action'        => [
						'type'              => 'string',
						'required'          => true,
						'enum'              => [ 'write_more', 'improve', 'summarize', 'fix_grammar', 'simplify', 'add_keywords', 'custom' ],
						'sanitize_callback' => 'sanitize_text_field',
					],
					'text'          => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					],
					'context'       => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'wp_kses_post',
					],
					'keyword'       => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'custom_prompt' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/ai/content-brief',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_content_brief' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'keyword' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'post_id' => [
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/ai/link-suggestions',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'get_link_suggestions' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'content' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					],
					'keyword' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Handle an inline AI writing action.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_inline_action( WP_REST_Request $request ) {
		$provider_manager = new Provider_Manager();
		$provider         = $provider_manager->get_active_provider();

		if ( ! $provider ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$action        = (string) $request->get_param( 'action' );
		$text          = (string) $request->get_param( 'text' );
		$context       = (string) $request->get_param( 'context' );
		$keyword       = (string) $request->get_param( 'keyword' );
		$custom_prompt = (string) $request->get_param( 'custom_prompt' );

		// Build the system prompt.
		if ( 'custom' === $action ) {
			if ( '' === $custom_prompt ) {
				return $this->error( __( 'Custom prompt is required for custom actions.', 'seo-ai' ) );
			}
			$system = 'You are a helpful AI writing assistant. Follow the user\'s instructions precisely. Return ONLY the resulting text, nothing else.';
			$user   = sprintf( "Instructions: %s\n\nText:\n%s", $custom_prompt, $text );
		} elseif ( 'add_keywords' === $action ) {
			$system = self::ACTION_PROMPTS['add_keywords'];
			$user   = sprintf( "Focus keyword: %s\n\nText:\n%s", $keyword, $text );
		} else {
			$system = self::ACTION_PROMPTS[ $action ] ?? self::ACTION_PROMPTS['improve'];
			$user   = $text;
		}

		// Add surrounding context if provided.
		if ( '' !== $context && 'custom' !== $action ) {
			$user .= sprintf( "\n\nSurrounding context:\n%s", wp_trim_words( wp_strip_all_tags( $context ), 200, '' ) );
		}

		// Prepend provider custom prompt if configured.
		$settings       = get_option( 'seo_ai_providers', [] );
		$active         = $settings['active_provider'] ?? '';
		$provider_prompt = trim( $settings[ $active ]['custom_prompt'] ?? '' );
		if ( '' !== $provider_prompt ) {
			$system = $provider_prompt . "\n\n" . $system;
		}

		// Determine max tokens based on action.
		$max_tokens = match ( $action ) {
			'write_more' => 600,
			'summarize'  => 150,
			'fix_grammar', 'simplify', 'improve', 'add_keywords' => 400,
			default      => 400,
		};

		try {
			$result = $provider->chat( $system, $user, [
				'temperature' => 0.7,
				'max_tokens'  => $max_tokens,
			] );

			// Clean up common AI artifacts.
			$result = trim( $result );
			$result = trim( $result, "\"'" );

			return $this->success( [
				'text'   => $result,
				'action' => $action,
			] );
		} catch ( \Throwable $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Generate an AI content brief for a keyword.
	 *
	 * Returns recommended word count, heading count, subtopics,
	 * link targets, and related keywords.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_content_brief( WP_REST_Request $request ) {
		$provider_manager = new Provider_Manager();
		$provider         = $provider_manager->get_active_provider();

		if ( ! $provider ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$keyword = (string) $request->get_param( 'keyword' );
		$post_id = (int) $request->get_param( 'post_id' );

		$system = 'You are an expert SEO content strategist. Analyze the given focus keyword and provide comprehensive content recommendations. '
			. "Return ONLY a valid JSON object with this exact structure:\n"
			. "{\n"
			. "  \"word_count\": {\"min\": 1500, \"max\": 2500},\n"
			. "  \"heading_count\": {\"min\": 6, \"max\": 10},\n"
			. "  \"subtopics\": [\"subtopic1\", \"subtopic2\"],\n"
			. "  \"link_count\": {\"internal\": 5, \"external\": 3},\n"
			. "  \"related_keywords\": [\"kw1\", \"kw2\", \"kw3\"],\n"
			. "  \"search_intent\": \"informational\",\n"
			. "  \"difficulty\": \"medium\",\n"
			. "  \"content_angle\": \"A brief content angle suggestion\"\n"
			. "}\n"
			. 'Return ONLY the JSON, no explanation, no markdown fences.';

		$user = sprintf( 'Focus keyword: "%s"', $keyword );

		// Add current post context if available.
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
				$user .= sprintf(
					"\n\nCurrent article title: \"%s\"\nCurrent word count: %d\nPost type: %s",
					$post->post_title,
					$word_count,
					$post->post_type
				);
			}
		}

		// Prepend provider custom prompt.
		$settings        = get_option( 'seo_ai_providers', [] );
		$active          = $settings['active_provider'] ?? '';
		$provider_prompt = trim( $settings[ $active ]['custom_prompt'] ?? '' );
		if ( '' !== $provider_prompt ) {
			$system = $provider_prompt . "\n\n" . $system;
		}

		try {
			$result = $provider->chat( $system, $user, [
				'temperature' => 0.5,
				'max_tokens'  => 600,
			] );

			// Strip markdown fences if present.
			$result = preg_replace( '/^```(?:json)?\s*/i', '', trim( $result ) );
			$result = preg_replace( '/\s*```$/', '', $result );

			$brief = json_decode( $result, true );

			if ( ! is_array( $brief ) ) {
				return $this->error( __( 'Failed to parse content brief. Please try again.', 'seo-ai' ), 500 );
			}

			return $this->success( [ 'brief' => $brief ] );
		} catch ( \Throwable $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Get AI-powered internal link suggestions.
	 *
	 * @param WP_REST_Request $request Full request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_link_suggestions( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$content = (string) $request->get_param( 'content' );
		$keyword = (string) $request->get_param( 'keyword' );

		if ( class_exists( 'SeoAi\\Modules\\Content_Analysis\\Link_Suggestions' ) ) {
			$provider_manager = new Provider_Manager();
			$link_suggestions = new \SeoAi\Modules\Content_Analysis\Link_Suggestions( $provider_manager );
			$suggestions      = $link_suggestions->get_suggestions( $post_id, $content, $keyword );

			return $this->success( [ 'suggestions' => $suggestions ] );
		}

		return $this->success( [ 'suggestions' => [] ] );
	}
}
