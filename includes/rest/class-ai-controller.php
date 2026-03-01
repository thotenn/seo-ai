<?php
/**
 * AI REST Controller.
 *
 * Exposes endpoints for AI-powered content optimization, meta tag
 * generation, schema detection, and bulk post optimization.
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
use SeoAi\Providers\Provider_Manager;

/**
 * Class Ai_Controller
 *
 * Handles all `/seo-ai/v1/ai/*` endpoints.
 *
 * @since 1.0.0
 */
final class Ai_Controller extends Rest_Controller {

	/**
	 * Register routes for AI operations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/ai/optimize',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'optimize' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id'        => [
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'content'        => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					],
					'keyword'        => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'failing_checks' => [
						'type'    => 'array',
						'default' => [],
						'items'   => [
							'type' => 'string',
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/ai/generate-meta',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_meta' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'content' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					],
					'keyword' => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'field'   => [
						'type'              => 'string',
						'required'          => true,
						'enum'              => [ 'title', 'description' ],
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/ai/generate-schema',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_schema' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'content' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/ai/bulk-optimize',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'bulk_optimize' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'post_ids' => [
						'type'     => 'array',
						'required' => true,
						'items'    => [
							'type' => 'integer',
						],
					],
					'fields'   => [
						'type'    => 'array',
						'default' => [ 'title', 'description' ],
						'items'   => [
							'type' => 'string',
						],
					],
				],
			]
		);
	}

	/**
	 * Get AI-powered optimization suggestions for content.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function optimize( WP_REST_Request $request ) {
		$provider_manager = $this->get_provider_manager();
		$provider         = $provider_manager->get_active_provider();

		if ( ! $provider ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$content        = (string) $request->get_param( 'content' );
		$keyword        = (string) $request->get_param( 'keyword' );
		$failing_checks = (array) $request->get_param( 'failing_checks' );

		try {
			$optimizer   = new \SeoAi\Modules\AI\AI_Optimizer( $provider_manager );
			$suggestions = $optimizer->suggest_improvements( $content, $keyword, $failing_checks );

			return $this->success( [
				'suggestions' => $suggestions,
			] );
		} catch ( \Throwable $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Generate an AI-powered meta title or description.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_meta( WP_REST_Request $request ) {
		$provider_manager = $this->get_provider_manager();
		$provider         = $provider_manager->get_active_provider();

		if ( ! $provider ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$content = (string) $request->get_param( 'content' );
		$keyword = (string) $request->get_param( 'keyword' );
		$field   = (string) $request->get_param( 'field' );

		try {
			$optimizer = new \SeoAi\Modules\AI\AI_Optimizer( $provider_manager );

			if ( 'title' === $field ) {
				$value = $optimizer->generate_meta_title( $content, $keyword );
			} else {
				$value = $optimizer->generate_meta_description( $content, $keyword );
			}

			return $this->success( [
				'value' => $value,
			] );
		} catch ( \Throwable $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Detect the most appropriate schema type for content.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_schema( WP_REST_Request $request ) {
		$provider_manager = $this->get_provider_manager();
		$provider         = $provider_manager->get_active_provider();

		if ( ! $provider ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$content = (string) $request->get_param( 'content' );

		try {
			$optimizer   = new \SeoAi\Modules\AI\AI_Optimizer( $provider_manager );
			$schema_type = $optimizer->detect_schema_type( $content );

			return $this->success( [
				'schema_type' => $schema_type,
			] );
		} catch ( \Throwable $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Bulk optimize multiple posts via AI.
	 *
	 * Iterates over the supplied post IDs and optimizes the requested
	 * fields for each one. Failures are collected and returned alongside
	 * the count of successfully optimized posts.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_optimize( WP_REST_Request $request ) {
		$provider_manager = $this->get_provider_manager();
		$provider         = $provider_manager->get_active_provider();

		if ( ! $provider ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$post_ids  = (array) $request->get_param( 'post_ids' );
		$fields    = (array) $request->get_param( 'fields' );
		$optimized = 0;
		$errors    = [];

		$optimizer = new \SeoAi\Modules\AI\AI_Optimizer( $provider_manager );

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( 0 === $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => __( 'Post not found.', 'seo-ai' ),
				];
				continue;
			}

			try {
				$optimizer->optimize_post( $post_id, $fields );
				$optimized++;
			} catch ( \Throwable $e ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => $e->getMessage(),
				];
			}
		}

		return $this->success( [
			'optimized' => $optimized,
			'errors'    => $errors,
		] );
	}

	/**
	 * Create a fresh Provider_Manager instance.
	 *
	 * @return Provider_Manager
	 */
	private function get_provider_manager(): Provider_Manager {
		return new Provider_Manager();
	}
}
