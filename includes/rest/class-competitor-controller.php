<?php
/**
 * Competitor Analysis REST Controller.
 *
 * Provides endpoints for analyzing competitor URLs and comparing
 * them against own posts.
 *
 * @package SeoAi\Rest
 * @since   0.8.0
 */

declare(strict_types=1);

namespace SeoAi\Rest;

defined( 'ABSPATH' ) || exit;

use SeoAi\Modules\Content_Analysis\Competitor_Analyzer;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Competitor_Controller
 *
 * @since 0.8.0
 */
class Competitor_Controller extends Rest_Controller {

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route( $this->namespace, '/competitor/analyze', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'analyze' ],
			'permission_callback' => [ $this, 'check_edit_permission' ],
			'args'                => [
				'url'           => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				],
				'focus_keyword' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				],
			],
		] );

		register_rest_route( $this->namespace, '/competitor/compare', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'compare' ],
			'permission_callback' => [ $this, 'check_edit_permission' ],
			'args'                => [
				'post_id'       => [
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
				'url'           => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				],
				'focus_keyword' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				],
			],
		] );
	}

	/**
	 * Analyze a competitor URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function analyze( WP_REST_Request $request ) {
		$analyzer = new Competitor_Analyzer();
		$result   = $analyzer->analyze_url(
			$request->get_param( 'url' ),
			$request->get_param( 'focus_keyword' ) ?? ''
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}

	/**
	 * Compare own post against a competitor URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function compare( WP_REST_Request $request ) {
		$analyzer = new Competitor_Analyzer();
		$result   = $analyzer->compare(
			(int) $request->get_param( 'post_id' ),
			$request->get_param( 'url' ),
			$request->get_param( 'focus_keyword' ) ?? ''
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}
}
