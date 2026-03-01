<?php
/**
 * Redirect Manager.
 *
 * Provides CRUD operations for redirects stored in the
 * {prefix}seo_ai_redirects database table, including import/export
 * functionality and hit tracking.
 *
 * @package SeoAi\Modules\Redirects
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Class Redirect_Manager
 *
 * Manages the seo_ai_redirects custom table for creating, reading,
 * updating, and deleting URL redirects.
 *
 * @since 1.0.0
 */
final class Redirect_Manager {

	/**
	 * WordPress database abstraction object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Full table name including prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Allowed redirect types.
	 *
	 * @var int[]
	 */
	private const ALLOWED_TYPES = [ 301, 302, 307, 410, 451 ];

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'seo_ai_redirects';
	}

	/**
	 * Create a new redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Redirect data.
	 *
	 *     @type string $source_url The source URL path to redirect from.
	 *     @type string $target_url The target URL to redirect to.
	 *     @type int    $type       HTTP status code (301, 302, 307, 410, 451).
	 *     @type int    $is_regex   Whether the source is a regex pattern (0 or 1).
	 *     @type string $status     Redirect status ('active' or 'inactive').
	 * }
	 * @return int|false The inserted redirect ID on success, false on failure.
	 */
	public function create( array $data ): int|false {
		$validated = $this->validate( $data );

		if ( is_wp_error( $validated ) ) {
			return false;
		}

		$source_url = $this->normalize_source( $validated['source_url'] );
		$target_url = $validated['target_url'];
		$type       = $validated['type'];
		$is_regex   = $validated['is_regex'];
		$status     = $validated['status'];

		// Check for duplicate source URL.
		if ( $this->source_exists( $source_url ) ) {
			return false;
		}

		$now    = current_time( 'mysql', true );
		$result = $this->db->insert(
			$this->table,
			[
				'source_url' => $source_url,
				'target_url' => $target_url,
				'type'       => $type,
				'is_regex'   => $is_regex,
				'status'     => $status,
				'hits'       => 0,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $this->db->insert_id;
	}

	/**
	 * Update an existing redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   The redirect ID.
	 * @param array $data The fields to update (same keys as create()).
	 * @return bool True on success, false on failure.
	 */
	public function update( int $id, array $data ): bool {
		$existing = $this->get( $id );

		if ( null === $existing ) {
			return false;
		}

		$update = [];
		$format = [];

		if ( isset( $data['source_url'] ) ) {
			$source = $this->normalize_source( sanitize_text_field( $data['source_url'] ) );

			if ( empty( $source ) ) {
				return false;
			}

			// Check for duplicate source (excluding current record).
			if ( $source !== $existing->source_url && $this->source_exists( $source, $id ) ) {
				return false;
			}

			$update['source_url'] = $source;
			$format[]             = '%s';
		}

		if ( isset( $data['target_url'] ) ) {
			$update['target_url'] = esc_url_raw( $data['target_url'] );
			$format[]             = '%s';
		}

		if ( isset( $data['type'] ) ) {
			$type = (int) $data['type'];
			if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
				return false;
			}
			$update['type'] = $type;
			$format[]       = '%d';
		}

		if ( isset( $data['is_regex'] ) ) {
			$update['is_regex'] = (int) (bool) $data['is_regex'];
			$format[]           = '%d';
		}

		if ( isset( $data['status'] ) ) {
			$status = sanitize_key( $data['status'] );
			if ( ! in_array( $status, [ 'active', 'inactive' ], true ) ) {
				return false;
			}
			$update['status'] = $status;
			$format[]         = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		$update['updated_at'] = current_time( 'mysql', true );
		$format[]             = '%s';

		// Self-redirect check.
		$final_source = $update['source_url'] ?? $existing->source_url;
		$final_target = $update['target_url'] ?? $existing->target_url;

		if ( $this->is_self_redirect( $final_source, $final_target ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->update(
			$this->table,
			$update,
			[ 'id' => $id ],
			$format,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a redirect by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The redirect ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Get a single redirect by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The redirect ID.
	 * @return object|null The redirect row object, or null if not found.
	 */
	public function get( int $id ): ?object {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Get all redirects with pagination, filtering, and sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $search   Search term for source_url or target_url.
	 *     @type int    $type     Filter by redirect type (e.g. 301).
	 *     @type string $status   Filter by status ('active' or 'inactive').
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    Sort direction. 'ASC' or 'DESC'. Default 'DESC'.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Current page number. Default 1.
	 * }
	 * @return array {
	 *     @type array $items Array of redirect row objects.
	 *     @type int   $total Total number of matching redirects.
	 *     @type int   $pages Total number of pages.
	 * }
	 */
	public function get_all( array $args = [] ): array {
		$defaults = [
			'search'   => '',
			'type'     => 0,
			'status'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		];

		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$search_like = '%' . $this->db->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]     = '(source_url LIKE %s OR target_url LIKE %s)';
			$values[]    = $search_like;
			$values[]    = $search_like;
		}

		// Type filter.
		if ( ! empty( $args['type'] ) ) {
			$type = (int) $args['type'];
			if ( in_array( $type, self::ALLOWED_TYPES, true ) ) {
				$where[]  = 'type = %d';
				$values[] = $type;
			}
		}

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			$status = sanitize_key( $args['status'] );
			if ( in_array( $status, [ 'active', 'inactive' ], true ) ) {
				$where[]  = 'status = %s';
				$values[] = $status;
			}
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		// Sorting.
		$allowed_orderby = [ 'source_url', 'target_url', 'type', 'hits', 'status', 'created_at', 'updated_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $this->db->prepare( $count_sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $this->db->get_var( $count_sql );

		// Pagination.
		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$pages    = (int) ceil( $total / $per_page );

		// Query items.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$query_values   = array_merge( $values, [ $per_page, $offset ] );
		$prepared_query = $this->db->prepare( $query, ...$query_values );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $this->db->get_results( $prepared_query );

		return [
			'items' => $items ?: [],
			'total' => $total,
			'pages' => $pages,
		];
	}

	/**
	 * Find a redirect matching a given source URL.
	 *
	 * Checks exact matches first, then regex patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The request URL to match against.
	 * @return object|null The matching redirect row, or null if not found.
	 */
	public function find_by_source( string $url ): ?object {
		$url = $this->normalize_source( $url );

		// Try exact match first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exact = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE source_url = %s AND is_regex = 0 AND status = 'active' LIMIT 1",
				$url
			)
		);

		if ( $exact ) {
			return $exact;
		}

		// Try regex matches.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$regex_rows = $this->db->get_results(
			"SELECT * FROM {$this->table} WHERE is_regex = 1 AND status = 'active' ORDER BY id ASC"
		);

		if ( empty( $regex_rows ) ) {
			return null;
		}

		foreach ( $regex_rows as $row ) {
			$pattern = '@' . str_replace( '@', '\\@', $row->source_url ) . '@i';

			// Suppress errors from invalid patterns.
			if ( @preg_match( $pattern, $url ) === 1 ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Increment the hit counter for a redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The redirect ID.
	 * @return void
	 */
	public function increment_hits( int $id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET hits = hits + 1, updated_at = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			)
		);
	}

	/**
	 * Import redirects from a CSV file.
	 *
	 * Expected CSV columns: source_url, target_url, type, is_regex.
	 * The first row is treated as a header and skipped.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array {
	 *     @type int   $imported Number of successfully imported redirects.
	 *     @type array $errors   Array of error message strings.
	 * }
	 */
	public function import_csv( string $file_path ): array {
		$result = [
			'imported' => 0,
			'errors'   => [],
		];

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			$result['errors'][] = __( 'CSV file not found or not readable.', 'seo-ai' );
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			$result['errors'][] = __( 'Failed to open CSV file.', 'seo-ai' );
			return $result;
		}

		$line   = 0;
		$header = null;

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$line++;

			// Skip header row.
			if ( 1 === $line ) {
				$header = $row;
				continue;
			}

			// Require at least source and target columns.
			if ( count( $row ) < 2 ) {
				/* translators: %d: line number */
				$result['errors'][] = sprintf( __( 'Line %d: insufficient columns.', 'seo-ai' ), $line );
				continue;
			}

			$data = [
				'source_url' => trim( $row[0] ),
				'target_url' => trim( $row[1] ),
				'type'       => isset( $row[2] ) ? (int) trim( $row[2] ) : 301,
				'is_regex'   => isset( $row[3] ) ? (int) trim( $row[3] ) : 0,
			];

			$id = $this->create( $data );

			if ( false === $id ) {
				/* translators: %1$d: line number, %2$s: source URL */
				$result['errors'][] = sprintf(
					__( 'Line %1$d: failed to import "%2$s".', 'seo-ai' ),
					$line,
					esc_html( $data['source_url'] )
				);
			} else {
				$result['imported']++;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return $result;
	}

	/**
	 * Export all redirects as CSV content.
	 *
	 * @since 1.0.0
	 *
	 * @return string CSV-formatted string of all redirects.
	 */
	public function export_csv(): string {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			"SELECT source_url, target_url, type, is_regex, hits, status, created_at FROM {$this->table} ORDER BY id ASC",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return "source_url,target_url,type,is_regex,hits,status,created_at\n";
		}

		$output = fopen( 'php://temp', 'r+' );

		// Header row.
		fputcsv( $output, [ 'source_url', 'target_url', 'type', 'is_regex', 'hits', 'status', 'created_at' ] );

		foreach ( $rows as $row ) {
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Count the total number of redirects.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total redirect count.
	 */
	public function count(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Delete all redirects from the table.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of deleted rows.
	 */
	public function delete_all(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->query( "DELETE FROM {$this->table}" );

		return false !== $result ? $result : 0;
	}

	/**
	 * Validate redirect data before insert.
	 *
	 * @param array $data Raw redirect data.
	 * @return array|\WP_Error Validated and sanitized data, or WP_Error on failure.
	 */
	private function validate( array $data ): array|\WP_Error {
		$source_url = isset( $data['source_url'] ) ? sanitize_text_field( $data['source_url'] ) : '';

		if ( empty( $source_url ) ) {
			return new \WP_Error( 'empty_source', __( 'Source URL cannot be empty.', 'seo-ai' ) );
		}

		$target_url = isset( $data['target_url'] ) ? esc_url_raw( $data['target_url'] ) : '';

		$type = isset( $data['type'] ) ? (int) $data['type'] : 301;
		if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
			$type = 301;
		}

		// 410 (Gone) and 451 (Unavailable for Legal Reasons) do not require a target URL.
		if ( ! in_array( $type, [ 410, 451 ], true ) && empty( $target_url ) ) {
			return new \WP_Error( 'empty_target', __( 'Target URL is required for this redirect type.', 'seo-ai' ) );
		}

		$is_regex = isset( $data['is_regex'] ) ? (int) (bool) $data['is_regex'] : 0;
		$status   = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active';

		if ( ! in_array( $status, [ 'active', 'inactive' ], true ) ) {
			$status = 'active';
		}

		// Self-redirect check.
		if ( $this->is_self_redirect( $source_url, $target_url ) ) {
			return new \WP_Error( 'self_redirect', __( 'Source and target URLs cannot be the same.', 'seo-ai' ) );
		}

		// Validate regex pattern if applicable.
		if ( 1 === $is_regex ) {
			$test_pattern = '@' . str_replace( '@', '\\@', $source_url ) . '@i';
			if ( @preg_match( $test_pattern, '' ) === false ) {
				return new \WP_Error( 'invalid_regex', __( 'Invalid regular expression pattern.', 'seo-ai' ) );
			}
		}

		return [
			'source_url' => $source_url,
			'target_url' => $target_url,
			'type'       => $type,
			'is_regex'   => $is_regex,
			'status'     => $status,
		];
	}

	/**
	 * Normalize a source URL for consistent matching.
	 *
	 * Strips the home URL prefix to store a relative path, and ensures
	 * it starts with a forward slash.
	 *
	 * @param string $url The source URL to normalize.
	 * @return string Normalized URL path.
	 */
	private function normalize_source( string $url ): string {
		$url = trim( $url );

		// Remove the home URL prefix if present.
		$home = untrailingslashit( home_url() );
		if ( str_starts_with( $url, $home ) ) {
			$url = substr( $url, strlen( $home ) );
		}

		// Ensure leading slash.
		if ( '' !== $url && ! str_starts_with( $url, '/' ) ) {
			$url = '/' . $url;
		}

		return $url;
	}

	/**
	 * Check if a source URL already exists in the database.
	 *
	 * @param string $source_url The source URL to check.
	 * @param int    $exclude_id Optional ID to exclude from the check (for updates).
	 * @return bool True if the source URL already exists.
	 */
	private function source_exists( string $source_url, int $exclude_id = 0 ): bool {
		if ( $exclude_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE source_url = %s AND id != %d",
					$source_url,
					$exclude_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE source_url = %s",
					$source_url
				)
			);
		}

		return (int) $exists > 0;
	}

	/**
	 * Check if the source and target would create a self-redirect.
	 *
	 * @param string $source The source URL/path.
	 * @param string $target The target URL.
	 * @return bool True if source and target are effectively the same.
	 */
	private function is_self_redirect( string $source, string $target ): bool {
		if ( empty( $target ) ) {
			return false;
		}

		$normalized_source = $this->normalize_source( $source );
		$normalized_target = $this->normalize_source( $target );

		return $normalized_source === $normalized_target;
	}
}
