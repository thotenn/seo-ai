<?php
/**
 * Provider REST Controller.
 *
 * Exposes endpoints for testing AI provider connections and
 * retrieving available models (primarily for Ollama).
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
 * Class Provider_Controller
 *
 * Handles the `/seo-ai/v1/provider/*` endpoints.
 *
 * @since 1.0.0
 */
final class Provider_Controller extends Rest_Controller {

	/**
	 * Register routes for provider operations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/provider/test',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'provider' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'settings' => [
						'type'     => 'object',
						'required' => false,
						'default'  => [],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/provider/models',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_models' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'provider' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'base_url' => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					],
				],
			]
		);
	}

	/**
	 * Test a provider's API connection.
	 *
	 * When override settings are supplied, they are used for a temporary
	 * connection test without persisting them. This lets administrators
	 * verify credentials before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_connection( WP_REST_Request $request ) {
		$provider_id       = (string) $request->get_param( 'provider' );
		$override_settings = (array) $request->get_param( 'settings' );

		$pm = new Provider_Manager();

		// Sanitize override settings before passing to the provider manager.
		$sanitized_overrides = $this->sanitize_override_settings( $override_settings );

		$result = $pm->test_provider( $provider_id, $sanitized_overrides );

		if ( $result['success'] ) {
			return $this->success(
				[],
				$result['message']
			);
		}

		return $this->error( $result['message'], 422 );
	}

	/**
	 * Get available models for a provider.
	 *
	 * For Ollama, models are fetched dynamically from the running instance.
	 * For other providers, the static model list is returned.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_models( WP_REST_Request $request ) {
		$provider_id = (string) $request->get_param( 'provider' );
		$base_url    = (string) $request->get_param( 'base_url' );

		$pm       = new Provider_Manager();
		$provider = $pm->get_provider( $provider_id );

		if ( ! $provider ) {
			return $this->error(
				sprintf(
					/* translators: %s: provider identifier */
					__( 'Provider "%s" not found.', 'seo-ai' ),
					$provider_id
				),
				404
			);
		}

		// For Ollama, support fetching from a custom base URL.
		if ( 'ollama' === $provider_id && '' !== $base_url ) {
			$models = $this->fetch_ollama_models( $base_url );
		} else {
			$models = $provider->get_models();
		}

		return $this->success( [
			'models' => $models,
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch models from an Ollama instance at a custom base URL.
	 *
	 * @param string $base_url The Ollama API base URL.
	 *
	 * @return array<string, string> Model IDs mapped to display names.
	 */
	private function fetch_ollama_models( string $base_url ): array {
		$url = rtrim( $base_url, '/' ) . '/api/tags';

		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return [];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data['models'] ?? null ) ) {
			return [];
		}

		$models = [];

		foreach ( $data['models'] as $model ) {
			$name = $model['name'] ?? '';
			if ( '' === $name ) {
				continue;
			}

			$size_bytes = $model['size'] ?? 0;
			$label      = $name;

			if ( $size_bytes > 0 ) {
				$label .= sprintf( ' (%.1f GB)', $size_bytes / 1073741824 );
			}

			$models[ $name ] = $label;
		}

		return $models;
	}

	/**
	 * Sanitize override settings used for connection testing.
	 *
	 * @param array $settings Raw override settings.
	 *
	 * @return array Sanitized override settings.
	 */
	private function sanitize_override_settings( array $settings ): array {
		$sanitized = [];

		if ( isset( $settings['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( (string) $settings['api_key'] );
		}

		if ( isset( $settings['base_url'] ) ) {
			$sanitized['base_url'] = esc_url_raw( (string) $settings['base_url'] );
		}

		if ( isset( $settings['model'] ) ) {
			$sanitized['model'] = sanitize_text_field( (string) $settings['model'] );
		}

		if ( isset( $settings['temperature'] ) ) {
			$sanitized['temperature'] = max( 0.0, min( 2.0, (float) $settings['temperature'] ) );
		}

		if ( isset( $settings['max_tokens'] ) ) {
			$sanitized['max_tokens'] = absint( $settings['max_tokens'] );
		}

		return $sanitized;
	}
}
