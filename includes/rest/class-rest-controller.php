<?php
/**
 * Abstract Base REST Controller.
 *
 * Provides shared behaviour for all SEO AI REST API controllers,
 * including response helpers, permission callbacks, and the common
 * route namespace.
 *
 * @package SeoAi\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Rest;

defined( 'ABSPATH' ) || exit;

use SeoAi\Plugin;
use WP_Error;
use WP_REST_Response;

/**
 * Class Rest_Controller
 *
 * Abstract base class extended by every endpoint controller in the plugin.
 *
 * @since 1.0.0
 */
abstract class Rest_Controller {

	/**
	 * REST API namespace shared by all SEO AI endpoints.
	 *
	 * @var string
	 */
	protected string $namespace = 'seo-ai/v1';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register the controller's REST routes.
	 *
	 * Each concrete controller must implement this method and call
	 * `register_rest_route()` for every endpoint it exposes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Permission callback: current user can manage plugin options.
	 *
	 * Use this for admin-only endpoints such as settings or bulk operations.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback: current user can edit posts.
	 *
	 * Use this for editor-level endpoints such as content analysis or
	 * AI suggestions.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function check_edit_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Build a successful REST response.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data    Response payload.
	 * @param string $message Optional human-readable message.
	 *
	 * @return WP_REST_Response
	 */
	protected function success( array $data = [], string $message = '' ): WP_REST_Response {
		$body = [
			'success' => true,
			'data'    => $data,
		];

		if ( '' !== $message ) {
			$body['message'] = $message;
		}

		return new WP_REST_Response( $body, 200 );
	}

	/**
	 * Build an error response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Human-readable error description.
	 * @param int    $status  HTTP status code.
	 *
	 * @return WP_Error
	 */
	protected function error( string $message, int $status = 400 ): WP_Error {
		return new WP_Error(
			'seo_ai_error',
			$message,
			[ 'status' => $status ]
		);
	}
}
