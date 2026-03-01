<?php
/**
 * Redirect REST Controller.
 *
 * Exposes CRUD endpoints for URL redirects, a 404 log viewer/clearer,
 * and a CSV import facility.
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

/**
 * Class Redirect_Controller
 *
 * Handles the `/seo-ai/v1/redirects` and `/seo-ai/v1/404-log` endpoints.
 *
 * @since 1.0.0
 */
final class Redirect_Controller extends Rest_Controller {

	/**
	 * Database table name for redirects (without prefix).
	 *
	 * @var string
	 */
	private const REDIRECTS_TABLE = 'seo_ai_redirects';

	/**
	 * Database table name for the 404 log (without prefix).
	 *
	 * @var string
	 */
	private const LOG_TABLE = 'seo_ai_404_log';

	/**
	 * Register routes for redirect management.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET & POST /redirects
		register_rest_route(
			$this->namespace,
			'/redirects',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_redirects' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => $this->get_collection_args(),
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_redirect' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => $this->get_redirect_args(),
				],
			]
		);

		// PUT & DELETE /redirects/{id}
		register_rest_route(
			$this->namespace,
			'/redirects/(?P<id>\d+)',
			[
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_redirect' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => array_merge(
						[
							'id' => [
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
							],
						],
						$this->get_redirect_args( false )
					),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_redirect' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'id' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// GET & DELETE /404-log
		register_rest_route(
			$this->namespace,
			'/404-log',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_404_log' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => $this->get_log_collection_args(),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'clear_404_log' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// POST /redirects/import
		register_rest_route(
			$this->namespace,
			'/redirects/import',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'import_redirects' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	// =========================================================================
	// Redirect CRUD
	// =========================================================================

	/**
	 * List redirects with optional filtering, search, and pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_redirects( WP_REST_Request $request ) {
		global $wpdb;

		$table    = $wpdb->prefix . self::REDIRECTS_TABLE;
		$search   = (string) $request->get_param( 'search' );
		$type     = $request->get_param( 'type' );
		$status   = (string) $request->get_param( 'status' );
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$orderby  = (string) $request->get_param( 'orderby' );
		$order    = (string) $request->get_param( 'order' );

		$where  = [];
		$values = [];

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(source_url LIKE %s OR target_url LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		if ( null !== $type && '' !== (string) $type ) {
			$where[]  = 'type = %d';
			$values[] = (int) $type;
		}

		if ( '' !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		// Validate orderby against allowed columns.
		$allowed_orderby = [ 'id', 'source_url', 'target_url', 'type', 'hits', 'status', 'created_at', 'updated_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'id';
		}

		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total matching rows.
		$count_query = "SELECT COUNT(*) FROM {$table} {$where_clause}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_query );
		}

		$offset = ( $page - 1 ) * $per_page;

		// Build the main query with ORDER BY and LIMIT (not user-input, so safe).
		$query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$query_values   = array_merge( $values, [ $per_page, $offset ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ), ARRAY_A );

		return $this->success( [
			'redirects'  => $results ?: [],
			'total'      => $total,
			'pages'      => (int) ceil( $total / max( 1, $per_page ) ),
			'page'       => $page,
			'per_page'   => $per_page,
		] );
	}

	/**
	 * Create a new redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_redirect( WP_REST_Request $request ) {
		$source_url = (string) $request->get_param( 'source_url' );
		$target_url = (string) $request->get_param( 'target_url' );
		$type       = (int) $request->get_param( 'type' );
		$is_regex   = (bool) $request->get_param( 'is_regex' );

		$validation = $this->validate_redirect( $source_url, $target_url, $type, $is_regex );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check for duplicates.
		$existing = $this->find_redirect_by_source( $source_url );
		if ( $existing ) {
			return $this->error(
				__( 'A redirect with this source URL already exists.', 'seo-ai' ),
				409
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . self::REDIRECTS_TABLE;

		$inserted = $wpdb->insert(
			$table,
			[
				'source_url' => $source_url,
				'target_url' => $target_url,
				'type'       => $type,
				'is_regex'   => $is_regex ? 1 : 0,
				'status'     => 'active',
				'hits'       => 0,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return $this->error(
				__( 'Failed to create redirect.', 'seo-ai' ),
				500
			);
		}

		$redirect = $this->get_redirect_by_id( (int) $wpdb->insert_id );

		return $this->success(
			[ 'redirect' => $redirect ],
			__( 'Redirect created.', 'seo-ai' )
		);
	}

	/**
	 * Update an existing redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_redirect( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$existing = $this->get_redirect_by_id( $id );
		if ( ! $existing ) {
			return $this->error( __( 'Redirect not found.', 'seo-ai' ), 404 );
		}

		$data   = [];
		$format = [];

		$source_url = $request->get_param( 'source_url' );
		$target_url = $request->get_param( 'target_url' );
		$type       = $request->get_param( 'type' );
		$is_regex   = $request->get_param( 'is_regex' );
		$status     = $request->get_param( 'status' );

		if ( null !== $source_url ) {
			$source_url = sanitize_text_field( (string) $source_url );
			if ( '' === trim( $source_url ) ) {
				return $this->error( __( 'Source URL is required.', 'seo-ai' ) );
			}

			// Check for duplicates on a different row.
			$dup = $this->find_redirect_by_source( $source_url );
			if ( $dup && (int) $dup['id'] !== $id ) {
				return $this->error(
					__( 'A redirect with this source URL already exists.', 'seo-ai' ),
					409
				);
			}

			$data['source_url'] = $source_url;
			$format[]           = '%s';
		}

		if ( null !== $target_url ) {
			$data['target_url'] = esc_url_raw( (string) $target_url );
			$format[]           = '%s';
		}

		if ( null !== $type ) {
			$type = (int) $type;
			if ( ! in_array( $type, [ 301, 302, 307, 308, 410 ], true ) ) {
				return $this->error( __( 'Invalid redirect type.', 'seo-ai' ) );
			}
			$data['type'] = $type;
			$format[]     = '%d';
		}

		if ( null !== $is_regex ) {
			$data['is_regex'] = ( (bool) $is_regex ) ? 1 : 0;
			$format[]         = '%d';
		}

		if ( null !== $status ) {
			$status = sanitize_text_field( (string) $status );
			if ( ! in_array( $status, [ 'active', 'inactive' ], true ) ) {
				return $this->error( __( 'Invalid status. Use "active" or "inactive".', 'seo-ai' ) );
			}
			$data['status'] = $status;
			$format[]       = '%s';
		}

		if ( empty( $data ) ) {
			return $this->error( __( 'No fields to update.', 'seo-ai' ) );
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		global $wpdb;
		$table = $wpdb->prefix . self::REDIRECTS_TABLE;

		$updated = $wpdb->update(
			$table,
			$data,
			[ 'id' => $id ],
			$format,
			[ '%d' ]
		);

		if ( false === $updated ) {
			return $this->error( __( 'Failed to update redirect.', 'seo-ai' ), 500 );
		}

		$redirect = $this->get_redirect_by_id( $id );

		return $this->success(
			[ 'redirect' => $redirect ],
			__( 'Redirect updated.', 'seo-ai' )
		);
	}

	/**
	 * Delete a redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_redirect( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$existing = $this->get_redirect_by_id( $id );
		if ( ! $existing ) {
			return $this->error( __( 'Redirect not found.', 'seo-ai' ), 404 );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::REDIRECTS_TABLE;

		$deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

		if ( false === $deleted ) {
			return $this->error( __( 'Failed to delete redirect.', 'seo-ai' ), 500 );
		}

		return $this->success( [], __( 'Redirect deleted.', 'seo-ai' ) );
	}

	// =========================================================================
	// 404 Log
	// =========================================================================

	/**
	 * Get paginated 404 log entries.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_404_log( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table    = $wpdb->prefix . self::LOG_TABLE;
		$search   = (string) $request->get_param( 'search' );
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$orderby  = (string) $request->get_param( 'orderby' );
		$order    = (string) $request->get_param( 'order' );

		$where  = [];
		$values = [];

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(url LIKE %s OR referrer LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$allowed_orderby = [ 'id', 'url', 'hits', 'last_hit', 'created_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'last_hit';
		}

		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// Total count.
		$count_query = "SELECT COUNT(*) FROM {$table} {$where_clause}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_query );
		}

		$offset = ( $page - 1 ) * $per_page;

		$query        = "SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values = array_merge( $values, [ $per_page, $offset ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ), ARRAY_A );

		return $this->success( [
			'logs'     => $results ?: [],
			'total'    => $total,
			'pages'    => (int) ceil( $total / max( 1, $per_page ) ),
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	/**
	 * Clear all 404 log entries.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_404_log( WP_REST_Request $request ) {
		global $wpdb;

		$table = $wpdb->prefix . self::LOG_TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		return $this->success( [], __( '404 log cleared.', 'seo-ai' ) );
	}

	// =========================================================================
	// CSV Import
	// =========================================================================

	/**
	 * Import redirects from an uploaded CSV file.
	 *
	 * Expected CSV format (with optional header row):
	 *   source_url, target_url, type
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_redirects( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return $this->error( __( 'No file uploaded. Please upload a CSV file.', 'seo-ai' ) );
		}

		$file = $files['file'];

		// Validate file upload.
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return $this->error( __( 'Invalid file upload.', 'seo-ai' ) );
		}

		// Validate MIME type.
		$allowed_types = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ];
		$finfo         = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type     = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return $this->error( __( 'Invalid file type. Please upload a CSV file.', 'seo-ai' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			return $this->error( __( 'Could not read the uploaded file.', 'seo-ai' ), 500 );
		}

		global $wpdb;
		$table    = $wpdb->prefix . self::REDIRECTS_TABLE;
		$imported = 0;
		$skipped  = 0;
		$errors   = [];
		$row_num  = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;

			// Skip empty rows.
			if ( empty( $row ) || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
				continue;
			}

			// Skip header row (heuristic: first column contains "source").
			if ( 1 === $row_num && isset( $row[0] ) && mb_stripos( (string) $row[0], 'source' ) !== false ) {
				continue;
			}

			$source = isset( $row[0] ) ? sanitize_text_field( trim( (string) $row[0] ) ) : '';
			$target = isset( $row[1] ) ? esc_url_raw( trim( (string) $row[1] ) ) : '';
			$type   = isset( $row[2] ) ? (int) $row[2] : 301;

			if ( '' === $source ) {
				$errors[] = sprintf(
					/* translators: %d: CSV row number */
					__( 'Row %d: Source URL is empty.', 'seo-ai' ),
					$row_num
				);
				continue;
			}

			if ( ! in_array( $type, [ 301, 302, 307, 308, 410 ], true ) ) {
				$type = 301;
			}

			// Skip duplicates.
			$existing = $this->find_redirect_by_source( $source );
			if ( $existing ) {
				$skipped++;
				continue;
			}

			$inserted = $wpdb->insert(
				$table,
				[
					'source_url' => $source,
					'target_url' => $target,
					'type'       => $type,
					'is_regex'   => 0,
					'status'     => 'active',
					'hits'       => 0,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				],
				[ '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' ]
			);

			if ( false !== $inserted ) {
				$imported++;
			} else {
				$errors[] = sprintf(
					/* translators: 1: row number, 2: source URL */
					__( 'Row %1$d: Failed to import "%2$s".', 'seo-ai' ),
					$row_num,
					$source
				);
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return $this->success(
			[
				'imported' => $imported,
				'skipped'  => $skipped,
				'errors'   => $errors,
			],
			sprintf(
				/* translators: 1: imported count, 2: skipped count */
				__( 'Import complete: %1$d imported, %2$d skipped (duplicates).', 'seo-ai' ),
				$imported,
				$skipped
			)
		);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Retrieve a single redirect by its ID.
	 *
	 * @param int $id Redirect row ID.
	 *
	 * @return array|null Row data or null if not found.
	 */
	private function get_redirect_by_id( int $id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . self::REDIRECTS_TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a redirect by its source URL.
	 *
	 * @param string $source_url The source URL to search for.
	 *
	 * @return array|null Row data or null if not found.
	 */
	private function find_redirect_by_source( string $source_url ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . self::REDIRECTS_TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_url = %s LIMIT 1", $source_url ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Validate redirect fields.
	 *
	 * @param string $source_url Source URL.
	 * @param string $target_url Target URL.
	 * @param int    $type       Redirect type (301, 302, etc.).
	 * @param bool   $is_regex   Whether the source is a regex pattern.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function validate_redirect( string $source_url, string $target_url, int $type, bool $is_regex ) {
		if ( '' === trim( $source_url ) ) {
			return $this->error( __( 'Source URL is required.', 'seo-ai' ) );
		}

		// 410 Gone does not require a target URL.
		if ( 410 !== $type && '' === trim( $target_url ) ) {
			return $this->error( __( 'Target URL is required for this redirect type.', 'seo-ai' ) );
		}

		if ( ! in_array( $type, [ 301, 302, 307, 308, 410 ], true ) ) {
			return $this->error( __( 'Invalid redirect type. Allowed: 301, 302, 307, 308, 410.', 'seo-ai' ) );
		}

		// Validate regex patterns.
		if ( $is_regex ) {
			// Suppress errors and test the pattern.
			$test = @preg_match( '#' . $source_url . '#', '' );
			if ( false === $test ) {
				return $this->error( __( 'The source URL contains an invalid regular expression.', 'seo-ai' ) );
			}
		}

		// Prevent redirect loops (simple check).
		if ( 410 !== $type && ! $is_regex && $source_url === $target_url ) {
			return $this->error( __( 'Source and target URLs cannot be identical.', 'seo-ai' ) );
		}

		return true;
	}

	/**
	 * Define query parameter arguments for the redirects collection endpoint.
	 *
	 * @return array
	 */
	private function get_collection_args(): array {
		return [
			'search'   => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'type'     => [
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
			'status'   => [
				'type'              => 'string',
				'default'           => '',
				'enum'              => [ '', 'active', 'inactive' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 25,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
			'orderby'  => [
				'type'              => 'string',
				'default'           => 'id',
				'enum'              => [ 'id', 'source_url', 'target_url', 'type', 'hits', 'status', 'created_at', 'updated_at' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order'    => [
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => [ 'ASC', 'DESC' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Define arguments for creating/updating a redirect.
	 *
	 * @param bool $required Whether source_url and target_url are required.
	 *
	 * @return array
	 */
	private function get_redirect_args( bool $required = true ): array {
		return [
			'source_url' => [
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'target_url' => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			],
			'type'       => [
				'type'              => 'integer',
				'default'           => 301,
				'enum'              => [ 301, 302, 307, 308, 410 ],
				'sanitize_callback' => 'absint',
			],
			'is_regex'   => [
				'type'    => 'boolean',
				'default' => false,
			],
			'status'     => [
				'type'              => 'string',
				'required'          => false,
				'enum'              => [ 'active', 'inactive' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Define query parameter arguments for the 404 log collection endpoint.
	 *
	 * @return array
	 */
	private function get_log_collection_args(): array {
		return [
			'search'   => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 25,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
			'orderby'  => [
				'type'              => 'string',
				'default'           => 'last_hit',
				'enum'              => [ 'id', 'url', 'hits', 'last_hit', 'created_at' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order'    => [
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => [ 'ASC', 'DESC' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}
}
