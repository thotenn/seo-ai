<?php
/**
 * Activity Log REST Controller.
 *
 * Exposes endpoints for retrieving and managing the activity log.
 *
 * @package SeoAi\Rest
 * @since   0.1.0
 */

declare(strict_types=1);

namespace SeoAi\Rest;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SeoAi\Activity_Log;

/**
 * Class Log_Controller
 *
 * Handles `/seo-ai/v1/logs` endpoints.
 *
 * @since 0.1.0
 */
final class Log_Controller extends Rest_Controller {

	/**
	 * Register routes for activity log operations.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/logs',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_logs' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'level'     => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'operation' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'search'    => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'page'      => [
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						],
						'per_page'  => [
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_logs' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'days' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * Get paginated activity log entries.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_logs( WP_REST_Request $request ): WP_REST_Response {
		$filters = [
			'level'     => $request->get_param( 'level' ),
			'operation' => $request->get_param( 'operation' ),
			'search'    => $request->get_param( 'search' ),
			'page'      => $request->get_param( 'page' ),
			'per_page'  => $request->get_param( 'per_page' ),
		];

		$result = Activity_Log::get( array_filter( $filters ) );

		return $this->success( $result );
	}

	/**
	 * Delete log entries older than N days.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_logs( WP_REST_Request $request ) {
		$days = (int) $request->get_param( 'days' );

		if ( $days < 1 ) {
			return $this->error( __( 'Days parameter must be at least 1.', 'seo-ai' ) );
		}

		$deleted = Activity_Log::cleanup( $days );

		Activity_Log::log( 'info', 'settings_change', sprintf(
			/* translators: 1: number deleted, 2: days threshold */
			__( 'Cleared %1$d log entries older than %2$d days', 'seo-ai' ),
			$deleted,
			$days
		) );

		return $this->success( [
			'deleted' => $deleted,
		] );
	}
}
