<?php
/**
 * Redirect List Table.
 *
 * Admin list table for displaying, filtering, sorting, and managing
 * URL redirects using the WordPress WP_List_Table infrastructure.
 *
 * @package SeoAi\Modules\Redirects
 * @since   1.0.0
 */

declare(strict_types=1);

namespace SeoAi\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

// Ensure the WP_List_Table class is available.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Redirect_Table
 *
 * Extends WP_List_Table to display redirects in the WordPress admin
 * with pagination, search, sorting, bulk actions, and type filtering.
 *
 * @since 1.0.0
 */
class Redirect_Table extends \WP_List_Table {

	/**
	 * Redirect manager instance.
	 *
	 * @var Redirect_Manager
	 */
	private Redirect_Manager $manager;

	/**
	 * Constructor.
	 *
	 * Sets up the list table with singular/plural labels and screen context.
	 */
	public function __construct() {
		$this->manager = new Redirect_Manager();

		parent::__construct( [
			'singular' => __( 'redirect', 'seo-ai' ),
			'plural'   => __( 'redirects', 'seo-ai' ),
			'ajax'     => false,
		] );
	}

	/**
	 * Define the columns for the list table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of column slugs to labels.
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'source_url' => __( 'Source URL', 'seo-ai' ),
			'target_url' => __( 'Target URL', 'seo-ai' ),
			'type'       => __( 'Type', 'seo-ai' ),
			'hits'       => __( 'Hits', 'seo-ai' ),
			'status'     => __( 'Status', 'seo-ai' ),
			'created_at' => __( 'Created', 'seo-ai' ),
		];
	}

	/**
	 * Define the sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of sortable column slugs to query parameters.
	 */
	public function get_sortable_columns(): array {
		return [
			'source_url' => [ 'source_url', false ],
			'type'       => [ 'type', false ],
			'hits'       => [ 'hits', true ],
			'created_at' => [ 'created_at', true ],
		];
	}

	/**
	 * Prepare the items for display.
	 *
	 * Queries redirects from the database with the current pagination,
	 * search, sorting, and filter parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->process_bulk_action();

		$per_page = $this->get_items_per_page( 'seo_ai_redirects_per_page', 20 );
		$page     = $this->get_pagenum();

		$args = [
			'per_page' => $per_page,
			'page'     => $page,
			'orderby'  => $this->get_current_orderby(),
			'order'    => $this->get_current_order(),
		];

		// Search.
		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		// Type filter.
		if ( ! empty( $_REQUEST['redirect_type'] ) ) {
			$args['type'] = (int) $_REQUEST['redirect_type'];
		}

		// Status filter.
		if ( ! empty( $_REQUEST['redirect_status'] ) ) {
			$args['status'] = sanitize_key( $_REQUEST['redirect_status'] );
		}

		$result = $this->manager->get_all( $args );

		$this->items = $result['items'];

		$this->set_pagination_args( [
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => $result['pages'],
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Render the default column output.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item   The current redirect row object.
	 * @param string $column The column slug.
	 * @return string The column content.
	 */
	public function column_default( $item, $column ): string {
		switch ( $column ) {
			case 'target_url':
				$url = esc_html( $item->target_url );

				if ( empty( $url ) ) {
					return '<em>' . esc_html__( '(none)', 'seo-ai' ) . '</em>';
				}

				return sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
					esc_url( $item->target_url ),
					esc_attr( $item->target_url ),
					esc_html( $this->truncate_url( $item->target_url ) )
				);

			case 'hits':
				return '<span class="seo-ai-hits-count">' . number_format_i18n( (int) $item->hits ) . '</span>';

			case 'created_at':
				if ( empty( $item->created_at ) ) {
					return '&mdash;';
				}

				$timestamp  = strtotime( $item->created_at );
				$human_date = human_time_diff( $timestamp, current_time( 'timestamp', true ) );

				return sprintf(
					'<abbr title="%s">%s %s</abbr>',
					esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
					esc_html( $human_date ),
					esc_html__( 'ago', 'seo-ai' )
				);

			default:
				return esc_html( (string) ( $item->$column ?? '' ) );
		}
	}

	/**
	 * Render the checkbox column for bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item The current redirect row object.
	 * @return string The checkbox HTML.
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="redirect_ids[]" value="%d" />',
			(int) $item->id
		);
	}

	/**
	 * Render the source URL column with row actions.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item The current redirect row object.
	 * @return string The source URL column HTML.
	 */
	public function column_source_url( $item ): string {
		$edit_url = wp_nonce_url(
			add_query_arg(
				[
					'page'   => 'seo-ai-redirects',
					'action' => 'edit',
					'id'     => (int) $item->id,
				],
				admin_url( 'admin.php' )
			),
			'seo_ai_redirect_edit_' . $item->id
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				[
					'page'   => 'seo-ai-redirects',
					'action' => 'delete',
					'id'     => (int) $item->id,
				],
				admin_url( 'admin.php' )
			),
			'seo_ai_redirect_delete_' . $item->id
		);

		$source_display = esc_html( $item->source_url );

		// Show regex indicator.
		if ( ! empty( $item->is_regex ) ) {
			$source_display = '<code class="seo-ai-regex">' . $source_display . '</code>'
				. ' <span class="seo-ai-badge seo-ai-badge--regex">'
				. esc_html__( 'regex', 'seo-ai' )
				. '</span>';
		} else {
			$source_display = '<code>' . $source_display . '</code>';
		}

		$actions = [
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'seo-ai' )
			),
			'delete' => sprintf(
				'<a href="%s" class="seo-ai-delete-redirect" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this redirect?', 'seo-ai' ) ),
				esc_html__( 'Delete', 'seo-ai' )
			),
		];

		return $source_display . $this->row_actions( $actions );
	}

	/**
	 * Render the type column with a styled badge.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item The current redirect row object.
	 * @return string The type column HTML.
	 */
	public function column_type( $item ): string {
		$type   = (int) $item->type;
		$labels = $this->get_type_labels();
		$label  = $labels[ $type ] ?? (string) $type;

		$class = match ( $type ) {
			301     => 'seo-ai-badge--301',
			302     => 'seo-ai-badge--302',
			307     => 'seo-ai-badge--307',
			410     => 'seo-ai-badge--410',
			451     => 'seo-ai-badge--451',
			default => 'seo-ai-badge--default',
		};

		return sprintf(
			'<span class="seo-ai-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Render the status column with an active/inactive indicator.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item The current redirect row object.
	 * @return string The status column HTML.
	 */
	public function column_status( $item ): string {
		$is_active = 'active' === $item->status;

		$toggle_url = wp_nonce_url(
			add_query_arg(
				[
					'page'   => 'seo-ai-redirects',
					'action' => $is_active ? 'deactivate' : 'activate',
					'id'     => (int) $item->id,
				],
				admin_url( 'admin.php' )
			),
			'seo_ai_redirect_toggle_' . $item->id
		);

		$status_class = $is_active ? 'seo-ai-status--active' : 'seo-ai-status--inactive';
		$status_label = $is_active
			? __( 'Active', 'seo-ai' )
			: __( 'Inactive', 'seo-ai' );
		$toggle_label = $is_active
			? __( 'Deactivate', 'seo-ai' )
			: __( 'Activate', 'seo-ai' );

		return sprintf(
			'<a href="%s" class="seo-ai-status-toggle %s" title="%s">
				<span class="seo-ai-status-dot"></span>
				<span class="seo-ai-status-label">%s</span>
			</a>',
			esc_url( $toggle_url ),
			esc_attr( $status_class ),
			esc_attr( $toggle_label ),
			esc_html( $status_label )
		);
	}

	/**
	 * Define the available bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of bulk action slugs to labels.
	 */
	public function get_bulk_actions(): array {
		return [
			'bulk_delete'     => __( 'Delete', 'seo-ai' ),
			'bulk_activate'   => __( 'Activate', 'seo-ai' ),
			'bulk_deactivate' => __( 'Deactivate', 'seo-ai' ),
		];
	}

	/**
	 * Process bulk actions.
	 *
	 * Handles bulk delete, activate, and deactivate operations on
	 * selected redirects.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		$action = $this->current_action();

		if ( empty( $action ) ) {
			// Handle single-item actions.
			$this->process_single_actions();
			return;
		}

		// Verify nonce for bulk actions.
		$nonce = $_REQUEST['_wpnonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		$ids = isset( $_REQUEST['redirect_ids'] ) ? array_map( 'intval', (array) $_REQUEST['redirect_ids'] ) : [];

		if ( empty( $ids ) ) {
			return;
		}

		$count = 0;

		switch ( $action ) {
			case 'bulk_delete':
				foreach ( $ids as $id ) {
					if ( $this->manager->delete( $id ) ) {
						$count++;
					}
				}

				if ( $count > 0 ) {
					add_settings_error(
						'seo_ai_redirects',
						'bulk_deleted',
						/* translators: %d: number of deleted redirects */
						sprintf( _n( '%d redirect deleted.', '%d redirects deleted.', $count, 'seo-ai' ), $count ),
						'success'
					);
				}
				break;

			case 'bulk_activate':
				foreach ( $ids as $id ) {
					if ( $this->manager->update( $id, [ 'status' => 'active' ] ) ) {
						$count++;
					}
				}

				if ( $count > 0 ) {
					add_settings_error(
						'seo_ai_redirects',
						'bulk_activated',
						/* translators: %d: number of activated redirects */
						sprintf( _n( '%d redirect activated.', '%d redirects activated.', $count, 'seo-ai' ), $count ),
						'success'
					);
				}
				break;

			case 'bulk_deactivate':
				foreach ( $ids as $id ) {
					if ( $this->manager->update( $id, [ 'status' => 'inactive' ] ) ) {
						$count++;
					}
				}

				if ( $count > 0 ) {
					add_settings_error(
						'seo_ai_redirects',
						'bulk_deactivated',
						/* translators: %d: number of deactivated redirects */
						sprintf( _n( '%d redirect deactivated.', '%d redirects deactivated.', $count, 'seo-ai' ), $count ),
						'success'
					);
				}
				break;
		}
	}

	/**
	 * Render extra table navigation controls.
	 *
	 * Adds a type filter dropdown above and below the list table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Position: 'top' or 'bottom'.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current_type   = isset( $_REQUEST['redirect_type'] ) ? (int) $_REQUEST['redirect_type'] : 0;
		$current_status = isset( $_REQUEST['redirect_status'] ) ? sanitize_key( $_REQUEST['redirect_status'] ) : '';
		$type_labels    = $this->get_type_labels();

		echo '<div class="alignleft actions">';

		// Type filter.
		echo '<select name="redirect_type" id="filter-by-type">';
		echo '<option value="">' . esc_html__( 'All Types', 'seo-ai' ) . '</option>';

		foreach ( $type_labels as $type_value => $type_label ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $type_value,
				selected( $current_type, $type_value, false ),
				esc_html( $type_label )
			);
		}

		echo '</select>';

		// Status filter.
		echo '<select name="redirect_status" id="filter-by-status">';
		echo '<option value="">' . esc_html__( 'All Statuses', 'seo-ai' ) . '</option>';

		$statuses = [
			'active'   => __( 'Active', 'seo-ai' ),
			'inactive' => __( 'Inactive', 'seo-ai' ),
		];

		foreach ( $statuses as $status_value => $status_label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $status_value ),
				selected( $current_status, $status_value, false ),
				esc_html( $status_label )
			);
		}

		echo '</select>';

		submit_button( __( 'Filter', 'seo-ai' ), '', 'filter_action', false );

		echo '</div>';
	}

	/**
	 * Message displayed when there are no redirects.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No redirects found.', 'seo-ai' );
	}

	/**
	 * Process single-item actions (edit, delete, activate, deactivate).
	 *
	 * @return void
	 */
	private function process_single_actions(): void {
		if ( ! isset( $_REQUEST['action'], $_REQUEST['id'] ) ) {
			return;
		}

		$action = sanitize_key( $_REQUEST['action'] );
		$id     = (int) $_REQUEST['id'];

		if ( $id < 1 ) {
			return;
		}

		switch ( $action ) {
			case 'delete':
				if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'seo_ai_redirect_delete_' . $id ) ) {
					return;
				}

				if ( $this->manager->delete( $id ) ) {
					add_settings_error(
						'seo_ai_redirects',
						'deleted',
						__( 'Redirect deleted successfully.', 'seo-ai' ),
						'success'
					);
				}
				break;

			case 'activate':
				if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'seo_ai_redirect_toggle_' . $id ) ) {
					return;
				}

				$this->manager->update( $id, [ 'status' => 'active' ] );
				break;

			case 'deactivate':
				if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'seo_ai_redirect_toggle_' . $id ) ) {
					return;
				}

				$this->manager->update( $id, [ 'status' => 'inactive' ] );
				break;
		}
	}

	/**
	 * Get human-readable labels for redirect types.
	 *
	 * @return array Associative array of HTTP status codes to labels.
	 */
	private function get_type_labels(): array {
		return [
			301 => __( '301 Permanent', 'seo-ai' ),
			302 => __( '302 Found', 'seo-ai' ),
			307 => __( '307 Temporary', 'seo-ai' ),
			410 => __( '410 Gone', 'seo-ai' ),
			451 => __( '451 Unavailable', 'seo-ai' ),
		];
	}

	/**
	 * Get the current orderby parameter.
	 *
	 * @return string The sanitized orderby column name.
	 */
	private function get_current_orderby(): string {
		$allowed = [ 'source_url', 'type', 'hits', 'created_at' ];

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$orderby = sanitize_key( $_REQUEST['orderby'] );
			if ( in_array( $orderby, $allowed, true ) ) {
				return $orderby;
			}
		}

		return 'created_at';
	}

	/**
	 * Get the current sort order parameter.
	 *
	 * @return string 'ASC' or 'DESC'.
	 */
	private function get_current_order(): string {
		if ( ! empty( $_REQUEST['order'] ) ) {
			return 'asc' === strtolower( sanitize_key( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		}

		return 'DESC';
	}

	/**
	 * Truncate a URL for display, keeping it readable.
	 *
	 * @param string $url    The URL to truncate.
	 * @param int    $length Maximum display length.
	 * @return string The truncated URL.
	 */
	private function truncate_url( string $url, int $length = 60 ): string {
		if ( mb_strlen( $url ) <= $length ) {
			return $url;
		}

		return mb_substr( $url, 0, $length - 3 ) . '...';
	}
}
