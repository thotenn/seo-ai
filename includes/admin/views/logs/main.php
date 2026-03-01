<?php
/**
 * Activity Log viewer page.
 *
 * @package SeoAi\Admin\Views
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Read filters from GET params.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$filter_level     = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
$filter_operation = isset( $_GET['operation'] ) ? sanitize_text_field( wp_unslash( $_GET['operation'] ) ) : '';
$filter_search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$per_page = 30;

$logs = \SeoAi\Activity_Log::get( [
	'level'     => $filter_level,
	'operation' => $filter_operation,
	'search'    => $filter_search,
	'page'      => $current_page,
	'per_page'  => $per_page,
] );

$items      = $logs['items'];
$total      = $logs['total'];
$total_pages = $logs['pages'];

// Get unique operations for filter dropdown.
$activity_table = $wpdb->prefix . 'seo_ai_activity_log';
$operations     = [];
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $activity_table ) ) === $activity_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$operations = $wpdb->get_col( "SELECT DISTINCT operation FROM {$activity_table} ORDER BY operation ASC" );
}

$base_url = admin_url( 'admin.php?page=seo-ai-logs' );
?>

<div class="wrap seo-ai-wrap">
	<h1 class="seo-ai-header">
		<span class="seo-ai-logo">SEO AI</span>
		<span>&mdash; <?php esc_html_e( 'Activity Log', 'seo-ai' ); ?></span>
	</h1>

	<!-- Filters -->
	<div class="seo-ai-card" style="margin-bottom:16px;">
		<form method="get" action="<?php echo esc_url( $base_url ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="hidden" name="page" value="seo-ai-logs">

			<select name="level">
				<option value=""><?php esc_html_e( 'All Levels', 'seo-ai' ); ?></option>
				<?php foreach ( [ 'debug', 'info', 'warn', 'error' ] as $lv ) : ?>
					<option value="<?php echo esc_attr( $lv ); ?>" <?php selected( $filter_level, $lv ); ?>>
						<?php echo esc_html( ucfirst( $lv ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="operation">
				<option value=""><?php esc_html_e( 'All Operations', 'seo-ai' ); ?></option>
				<?php foreach ( $operations as $op ) : ?>
					<option value="<?php echo esc_attr( $op ); ?>" <?php selected( $filter_operation, $op ); ?>>
						<?php echo esc_html( $op ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="text" name="s" value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Search messages...', 'seo-ai' ); ?>" class="regular-text">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'seo-ai' ); ?></button>

			<?php if ( $filter_level || $filter_operation || $filter_search ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'seo-ai' ); ?></a>
			<?php endif; ?>

			<span style="flex:1;"></span>

			<button type="button" class="button" id="seo-ai-clear-old-logs" style="color:#dc2626;">
				<?php esc_html_e( 'Clear Old Logs', 'seo-ai' ); ?>
			</button>
		</form>
	</div>

	<!-- Log Table -->
	<div class="seo-ai-card">
		<?php if ( empty( $items ) ) : ?>
			<p style="color:#6b7280;text-align:center;padding:24px;">
				<?php esc_html_e( 'No log entries found.', 'seo-ai' ); ?>
			</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="border:none;">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e( 'Time', 'seo-ai' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Level', 'seo-ai' ); ?></th>
						<th style="width:130px;"><?php esc_html_e( 'Operation', 'seo-ai' ); ?></th>
						<th><?php esc_html_e( 'Message', 'seo-ai' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'User', 'seo-ai' ); ?></th>
						<th style="width:50px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $log ) : ?>
						<tr>
							<td>
								<span title="<?php echo esc_attr( $log['created_at'] ); ?>">
									<?php echo esc_html( wp_date( 'M j, H:i:s', strtotime( $log['created_at'] ) ) ); ?>
								</span>
							</td>
							<td>
								<span class="seo-ai-activity-badge seo-ai-activity-badge-<?php echo esc_attr( $log['level'] ); ?>">
									<?php echo esc_html( $log['level'] ); ?>
								</span>
							</td>
							<td>
								<code style="font-size:11px;"><?php echo esc_html( $log['operation'] ); ?></code>
							</td>
							<td><?php echo esc_html( $log['message'] ); ?></td>
							<td>
								<?php
								if ( ! empty( $log['user_id'] ) ) {
									$user = get_userdata( (int) $log['user_id'] );
									echo $user ? esc_html( $user->display_name ) : esc_html( '#' . $log['user_id'] );
								} else {
									echo '<span style="color:#9ca3af;">&mdash;</span>';
								}
								?>
							</td>
							<td>
								<?php if ( ! empty( $log['context'] ) ) : ?>
									<button type="button" class="seo-ai-activity-expand"
										data-context="<?php echo esc_attr( is_string( $log['context'] ) ? $log['context'] : wp_json_encode( $log['context'] ) ); ?>">
										&hellip;
									</button>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( ! empty( $log['context'] ) ) : ?>
							<tr class="seo-ai-log-context-row" style="display:none;">
								<td colspan="6">
									<pre class="seo-ai-activity-detail" style="margin:0;">
									<?php
									$ctx = $log['context'];
									if ( is_string( $ctx ) ) {
										$ctx = json_decode( $ctx, true );
									}
									echo esc_html( wp_json_encode( $ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
									?>
									</pre>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div style="display:flex;justify-content:center;gap:8px;padding:16px 0;">
					<?php if ( $current_page > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>" class="button button-small">&laquo; <?php esc_html_e( 'Previous', 'seo-ai' ); ?></a>
					<?php endif; ?>
					<span style="line-height:28px;font-size:13px;">
						<?php
						printf(
							/* translators: 1: current page, 2: total pages */
							esc_html__( 'Page %1$d of %2$d', 'seo-ai' ),
							$current_page,
							$total_pages
						);
						?>
						(<?php echo esc_html( $total ); ?> <?php esc_html_e( 'entries', 'seo-ai' ); ?>)
					</span>
					<?php if ( $current_page < $total_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Next', 'seo-ai' ); ?> &raquo;</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<script>
(function($) {
	'use strict';

	// Toggle context rows.
	$(document).on('click', '.seo-ai-activity-expand', function() {
		$(this).closest('tr').next('.seo-ai-log-context-row').toggle();
	});

	// Clear old logs.
	$('#seo-ai-clear-old-logs').on('click', function() {
		var days = prompt('Delete log entries older than how many days?', '30');
		if (!days || isNaN(days)) return;

		$.ajax({
			url: seoAi.restUrl + 'logs',
			method: 'DELETE',
			contentType: 'application/json',
			data: JSON.stringify({ days: parseInt(days, 10) }),
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', seoAi.nonce);
			}
		}).done(function(res) {
			var count = (res.data && res.data.deleted) || 0;
			alert('Deleted ' + count + ' log entries.');
			location.reload();
		}).fail(function() {
			alert('Failed to clear logs.');
		});
	});
})(jQuery);
</script>
