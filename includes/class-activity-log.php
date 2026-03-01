<?php
/**
 * Activity Log.
 *
 * Provides static methods for logging plugin operations to the
 * seo_ai_activity_log database table.
 *
 * @package SeoAi
 * @since   0.1.0
 */

declare(strict_types=1);

namespace SeoAi;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activity_Log
 *
 * @since 0.1.0
 */
class Activity_Log {

	/**
	 * Log table name (without prefix).
	 *
	 * @var string
	 */
	private static string $table = 'seo_ai_activity_log';

	/**
	 * Insert a log entry.
	 *
	 * @param string $level     Log level: debug|info|warn|error.
	 * @param string $operation Operation identifier (e.g. auto_seo, bulk_optimize, wizard_optimize).
	 * @param string $message   Human-readable log message.
	 * @param array  $context   Optional JSON-serializable context data.
	 *
	 * @return int|false The inserted row ID, or false on failure.
	 */
	public static function log( string $level, string $operation, string $message, array $context = [] ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . self::$table;

		$data = [
			'level'      => sanitize_text_field( $level ),
			'operation'  => sanitize_text_field( $operation ),
			'message'    => sanitize_text_field( $message ),
			'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
			'user_id'    => get_current_user_id() ?: null,
			'created_at' => current_time( 'mysql', true ),
		];

		$formats = [ '%s', '%s', '%s', '%s', '%d', '%s' ];

		if ( null === $data['user_id'] ) {
			$formats[4] = null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( $table, $data, $formats );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Retrieve log entries with optional filters.
	 *
	 * @param array $filters {
	 *     Optional. Filtering and pagination parameters.
	 *
	 *     @type string $level     Filter by log level.
	 *     @type string $operation Filter by operation.
	 *     @type string $search    Search in message text.
	 *     @type int    $page      Page number (1-indexed).
	 *     @type int    $per_page  Items per page.
	 * }
	 *
	 * @return array{ items: array, total: int, pages: int }
	 */
	public static function get( array $filters = [] ): array {
		global $wpdb;

		$table    = $wpdb->prefix . self::$table;
		$where    = [];
		$values   = [];
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $filters['per_page'] ?? 20 ) ) );

		if ( ! empty( $filters['level'] ) ) {
			$where[]  = 'level = %s';
			$values[] = sanitize_text_field( $filters['level'] );
		}

		if ( ! empty( $filters['operation'] ) ) {
			$where[]  = 'operation = %s';
			$values[] = sanitize_text_field( $filters['operation'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[]  = 'message LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$offset    = ( $page - 1 ) * $per_page;

		// Count total.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// Fetch items.
		$query = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$args  = array_merge( $values, [ $per_page, $offset ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );

		// Decode JSON context.
		foreach ( $items as &$item ) {
			if ( ! empty( $item['context'] ) ) {
				$item['context'] = json_decode( $item['context'], true );
			}
		}

		return [
			'items' => $items ?: [],
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		];
	}

	/**
	 * Delete log entries older than a given number of days.
	 *
	 * @param int $days Number of days. Entries older than this are deleted.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function cleanup( int $days ): int {
		global $wpdb;

		$table = $wpdb->prefix . self::$table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
			)
		);

		return (int) $deleted;
	}
}
