<?php
/**
 * Queue REST Controller.
 *
 * Exposes endpoints for the optimization wizard: listing posts,
 * starting a queue, processing items one at a time, and cancelling.
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
use SeoAi\Providers\Provider_Manager;
use SeoAi\Modules\Content_Analysis\AI_Optimizer;

/**
 * Class Queue_Controller
 *
 * Handles `/seo-ai/v1/queue/*` endpoints.
 *
 * @since 0.1.0
 */
final class Queue_Controller extends Rest_Controller {

	/**
	 * Transient key for the optimization queue state.
	 *
	 * @var string
	 */
	private const QUEUE_TRANSIENT = 'seo_ai_optimize_queue';

	/**
	 * Register routes for queue operations.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/queue/posts',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_posts' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'search'    => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'post_type' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'filter'    => [
						'type'              => 'string',
						'default'           => 'all',
						'enum'              => [ 'all', 'unoptimized', 'optimized' ],
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
			]
		);

		register_rest_route(
			$this->namespace,
			'/queue/start',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'start_queue' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'post_ids' => [
						'type'     => 'array',
						'required' => true,
						'items'    => [ 'type' => 'integer' ],
					],
					'fields'   => [
						'type'    => 'array',
						'default' => [ 'title', 'description' ],
						'items'   => [ 'type' => 'string' ],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/queue/process-next',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'process_next' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/queue/cancel',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'cancel_queue' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	/**
	 * Get posts available for optimization.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_posts( WP_REST_Request $request ): WP_REST_Response {
		$search    = (string) $request->get_param( 'search' );
		$post_type = (string) $request->get_param( 'post_type' );
		$filter    = (string) $request->get_param( 'filter' );
		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );

		$supported_types = $this->plugin->get_supported_post_types();

		$query_args = [
			'post_type'      => ! empty( $post_type ) && in_array( $post_type, $supported_types, true )
				? $post_type
				: $supported_types,
			'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		// Filter by optimization status.
		if ( 'unoptimized' === $filter ) {
			$query_args['meta_query'] = [
				[
					'key'     => '_seo_ai_seo_score',
					'compare' => 'NOT EXISTS',
				],
			];
		} elseif ( 'optimized' === $filter ) {
			$query_args['meta_query'] = [
				[
					'key'     => '_seo_ai_seo_score',
					'compare' => 'EXISTS',
				],
			];
		}

		$query = new \WP_Query( $query_args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$score = (int) get_post_meta( $post->ID, '_seo_ai_seo_score', true );
			$posts[] = [
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'seo_score' => $score,
			];
		}

		return $this->success( [
			'posts' => $posts,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		] );
	}

	/**
	 * Start a new optimization queue.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_queue( WP_REST_Request $request ) {
		$post_ids = array_map( 'absint', (array) $request->get_param( 'post_ids' ) );
		$fields   = (array) $request->get_param( 'fields' );

		// Validate post_ids.
		$post_ids = array_filter( $post_ids, function ( int $id ): bool {
			return $id > 0 && get_post( $id ) instanceof \WP_Post;
		} );

		if ( empty( $post_ids ) ) {
			return $this->error( __( 'No valid posts selected.', 'seo-ai' ) );
		}

		// Validate fields.
		$allowed_fields = [ 'title', 'description', 'keyword', 'schema', 'og' ];
		$fields = array_intersect( $fields, $allowed_fields );

		if ( empty( $fields ) ) {
			return $this->error( __( 'No valid fields selected.', 'seo-ai' ) );
		}

		// Check provider availability.
		$provider_manager = new Provider_Manager();
		if ( ! $provider_manager->get_active_provider() ) {
			return $this->error(
				__( 'No AI provider is configured. Please set up a provider in Settings.', 'seo-ai' ),
				503
			);
		}

		$queue = [
			'post_ids'      => array_values( $post_ids ),
			'current_index' => 0,
			'total'         => count( $post_ids ),
			'fields'        => array_values( $fields ),
			'started_at'    => current_time( 'mysql', true ),
		];

		set_transient( self::QUEUE_TRANSIENT, $queue, HOUR_IN_SECONDS );

		Activity_Log::log( 'info', 'wizard_optimize', sprintf(
			/* translators: %d: number of posts */
			__( 'Started optimization of %d posts', 'seo-ai' ),
			count( $post_ids )
		), [
			'post_ids' => $post_ids,
			'fields'   => $fields,
		] );

		return $this->success( [
			'total'    => count( $post_ids ),
			'fields'   => $fields,
		] );
	}

	/**
	 * Process the next item in the optimization queue.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function process_next() {
		$queue = get_transient( self::QUEUE_TRANSIENT );

		if ( ! is_array( $queue ) || empty( $queue['post_ids'] ) ) {
			return $this->success( [
				'done'     => true,
				'progress' => 100,
			] );
		}

		$index   = (int) ( $queue['current_index'] ?? 0 );
		$total   = (int) ( $queue['total'] ?? count( $queue['post_ids'] ) );
		$fields  = (array) ( $queue['fields'] ?? [ 'title', 'description' ] );

		// Check if we're done.
		if ( $index >= count( $queue['post_ids'] ) ) {
			delete_transient( self::QUEUE_TRANSIENT );

			Activity_Log::log( 'info', 'wizard_optimize', sprintf(
				/* translators: %d: number of posts */
				__( 'Completed optimization of %d posts', 'seo-ai' ),
				$total
			) );

			return $this->success( [
				'done'     => true,
				'progress' => 100,
			] );
		}

		$post_id = (int) $queue['post_ids'][ $index ];
		$post    = get_post( $post_id );

		$log_entry = null;

		if ( $post instanceof \WP_Post ) {
			$old_score = (int) get_post_meta( $post_id, '_seo_ai_seo_score', true );

			try {
				$provider_manager = new Provider_Manager();
				$optimizer        = new AI_Optimizer( $provider_manager );
				$result           = $optimizer->optimize_post( $post_id, $fields );

				$new_score = (int) get_post_meta( $post_id, '_seo_ai_seo_score', true );

				$log_entry = [
					'level'   => 'info',
					'message' => sprintf(
						/* translators: 1: post title, 2: fields list */
						__( 'Optimized "%1$s" — fields: %2$s', 'seo-ai' ),
						$post->post_title,
						implode( ', ', $fields )
					),
				];

				Activity_Log::log( 'info', 'wizard_optimize', $log_entry['message'], [
					'post_id'   => $post_id,
					'fields'    => $fields,
					'old_score' => $old_score,
					'new_score' => $new_score,
					'result'    => $result,
				] );
			} catch ( \Throwable $e ) {
				$log_entry = [
					'level'   => 'error',
					'message' => sprintf(
						/* translators: 1: post title, 2: error message */
						__( 'Failed to optimize "%1$s": %2$s', 'seo-ai' ),
						$post->post_title,
						$e->getMessage()
					),
				];

				Activity_Log::log( 'error', 'wizard_optimize', $log_entry['message'], [
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				] );
			}
		} else {
			$log_entry = [
				'level'   => 'warn',
				'message' => sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d not found, skipping', 'seo-ai' ),
					$post_id
				),
			];

			Activity_Log::log( 'warn', 'wizard_optimize', $log_entry['message'], [
				'post_id' => $post_id,
			] );
		}

		// Update queue state.
		$queue['current_index'] = $index + 1;
		set_transient( self::QUEUE_TRANSIENT, $queue, HOUR_IN_SECONDS );

		$progress = (int) round( ( ( $index + 1 ) / $total ) * 100 );

		return $this->success( [
			'done'      => false,
			'progress'  => $progress,
			'current'   => $index + 1,
			'total'     => $total,
			'post_id'   => $post_id,
			'log_entry' => $log_entry,
		] );
	}

	/**
	 * Cancel a running optimization queue.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function cancel_queue(): WP_REST_Response {
		$queue = get_transient( self::QUEUE_TRANSIENT );

		delete_transient( self::QUEUE_TRANSIENT );

		$processed = 0;
		if ( is_array( $queue ) ) {
			$processed = (int) ( $queue['current_index'] ?? 0 );
		}

		Activity_Log::log( 'warn', 'wizard_optimize', sprintf(
			/* translators: %d: number of posts processed before cancel */
			__( 'Optimization cancelled after %d posts', 'seo-ai' ),
			$processed
		) );

		return $this->success( [
			'cancelled' => true,
			'processed' => $processed,
		] );
	}
}
