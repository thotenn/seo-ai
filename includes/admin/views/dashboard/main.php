<?php
/**
 * Dashboard main view.
 *
 * @package SeoAi\Admin\Views
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Gather statistics.
global $wpdb;

$total_posts     = wp_count_posts( 'post' );
$published       = $total_posts->publish ?? 0;
$total_pages     = wp_count_posts( 'page' );
$published_pages = $total_pages->publish ?? 0;

// phpcs:disable WordPress.DB.DirectDatabaseQuery
$scored = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND meta_value > 0"
);
$avg_score = (int) $wpdb->get_var(
	"SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND meta_value > 0"
);
$good_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND CAST(meta_value AS UNSIGNED) >= 70"
);
$warning_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND CAST(meta_value AS UNSIGNED) >= 40 AND CAST(meta_value AS UNSIGNED) < 70"
);
$low_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND CAST(meta_value AS UNSIGNED) < 40 AND meta_value > 0"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery

$total_content = $published + $published_pages;
$unoptimized   = max( 0, $total_content - $scored );

// Redirects stats.
$redirects_table = $wpdb->prefix . 'seo_ai_redirects';
$redirect_count  = 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $redirects_table ) ) === $redirects_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$redirect_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$redirects_table}" );
}

// 404 stats.
$log_table  = $wpdb->prefix . 'seo_ai_404_log';
$recent_404 = 0;
$total_404  = 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) === $log_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$total_404 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$recent_404 = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$log_table} WHERE last_hit > %s", gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) )
	);
}

// Provider info.
$providers       = get_option( 'seo_ai_providers', [] );
$active_provider = $providers['active_provider'] ?? 'none';

// Module info.
$plugin         = SeoAi\Plugin::instance();
$module_manager = $plugin->module_manager();
$modules        = $module_manager->get_registered_modules();

// Queue state (for progress card).
$queue = get_transient( 'seo_ai_optimize_queue' );

// Recent activity logs.
$activity_table = $wpdb->prefix . 'seo_ai_activity_log';
$recent_logs    = [];
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $activity_table ) ) === $activity_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$recent_logs = $wpdb->get_results(
		"SELECT * FROM {$activity_table} ORDER BY created_at DESC LIMIT 10",
		ARRAY_A
	) ?: [];
}
?>

<div class="wrap seo-ai-wrap">
	<h1 class="seo-ai-header">
		<span class="seo-ai-logo">SEO AI</span>
		<span>&mdash; Dashboard</span>
		<span class="seo-ai-version">v<?php echo esc_html( SEO_AI_VERSION ); ?></span>
	</h1>

	<!-- Hero Card -->
	<div class="seo-ai-hero-card">
		<div class="seo-ai-hero-content">
			<h2><?php esc_html_e( 'AI-Powered SEO Optimization', 'seo-ai' ); ?></h2>
			<p>
				<?php
				if ( $unoptimized > 0 ) {
					printf(
						/* translators: %d: number of unoptimized posts */
						esc_html( _n(
							'You have %d post that needs optimization.',
							'You have %d posts that need optimization.',
							$unoptimized,
							'seo-ai'
						) ),
						$unoptimized
					);
				} else {
					esc_html_e( 'All your content has been optimized!', 'seo-ai' );
				}
				?>
			</p>
			<button type="button" class="button seo-ai-hero-btn" id="seo-ai-start-optimize">
				<?php esc_html_e( 'Start Optimization', 'seo-ai' ); ?>
			</button>
		</div>
		<div class="seo-ai-hero-stats">
			<div class="seo-ai-hero-stat">
				<span class="seo-ai-hero-stat-value"><?php echo esc_html( $total_content ); ?></span>
				<span class="seo-ai-hero-stat-label"><?php esc_html_e( 'Total', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-hero-stat">
				<span class="seo-ai-hero-stat-value"><?php echo esc_html( $scored ); ?></span>
				<span class="seo-ai-hero-stat-label"><?php esc_html_e( 'Optimized', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-hero-stat">
				<span class="seo-ai-hero-stat-value"><?php echo esc_html( $unoptimized ); ?></span>
				<span class="seo-ai-hero-stat-label"><?php esc_html_e( 'Pending', 'seo-ai' ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( is_array( $queue ) && ! empty( $queue['post_ids'] ) ) : ?>
	<!-- Optimization Progress Card (when queue is running) -->
	<div class="seo-ai-card" id="seo-ai-active-queue-card">
		<h2><?php esc_html_e( 'Optimization In Progress', 'seo-ai' ); ?></h2>
		<div class="seo-ai-progress-header">
			<span>
				<?php
				printf(
					/* translators: 1: current index, 2: total */
					esc_html__( '%1$d of %2$d posts', 'seo-ai' ),
					(int) ( $queue['current_index'] ?? 0 ),
					(int) ( $queue['total'] ?? 0 )
				);
				?>
			</span>
			<span>
				<?php
				$pct = 0;
				if ( ! empty( $queue['total'] ) ) {
					$pct = (int) round( ( (int) ( $queue['current_index'] ?? 0 ) / (int) $queue['total'] ) * 100 );
				}
				echo esc_html( $pct . '%' );
				?>
			</span>
		</div>
		<div class="seo-ai-progress-bar">
			<div class="seo-ai-progress-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
		</div>
		<p style="margin-top:12px;">
			<button type="button" class="button" id="seo-ai-resume-queue">
				<?php esc_html_e( 'Resume', 'seo-ai' ); ?>
			</button>
		</p>
	</div>
	<?php endif; ?>

	<!-- SEO Score Overview -->
	<div class="seo-ai-card">
		<h2><?php esc_html_e( 'SEO Score Overview', 'seo-ai' ); ?></h2>
		<div class="seo-ai-dashboard-stats">
			<div class="seo-ai-stat">
				<span class="seo-ai-stat-value" style="color:<?php echo $avg_score >= 70 ? '#16a34a' : ( $avg_score >= 40 ? '#f59e0b' : '#dc2626' ); ?>">
					<?php echo $avg_score ? esc_html( $avg_score ) : '&mdash;'; ?>
				</span>
				<span class="seo-ai-stat-label"><?php esc_html_e( 'Average Score', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-stat">
				<span class="seo-ai-stat-value"><?php echo esc_html( $scored ); ?></span>
				<span class="seo-ai-stat-label"><?php esc_html_e( 'Posts Analyzed', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-stat">
				<span class="seo-ai-stat-value" style="color:#16a34a"><?php echo esc_html( $good_count ); ?></span>
				<span class="seo-ai-stat-label"><?php esc_html_e( 'Good (70+)', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-stat">
				<span class="seo-ai-stat-value" style="color:#f59e0b"><?php echo esc_html( $warning_count ); ?></span>
				<span class="seo-ai-stat-label"><?php esc_html_e( 'Needs Work (40-69)', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-stat">
				<span class="seo-ai-stat-value" style="color:#dc2626"><?php echo esc_html( $low_count ); ?></span>
				<span class="seo-ai-stat-label"><?php esc_html_e( 'Poor (<40)', 'seo-ai' ); ?></span>
			</div>
			<div class="seo-ai-stat">
				<span class="seo-ai-stat-value"><?php echo esc_html( $total_content ); ?></span>
				<span class="seo-ai-stat-label"><?php esc_html_e( 'Total Content', 'seo-ai' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Quick Stats Row -->
	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:16px;">
		<!-- Redirects -->
		<div class="seo-ai-card">
			<h2><?php esc_html_e( 'Redirects', 'seo-ai' ); ?></h2>
			<p style="font-size:28px;font-weight:700;margin:8px 0;"><?php echo esc_html( $redirect_count ); ?></p>
			<p style="color:#6b7280;font-size:13px;margin:0;">
				<?php esc_html_e( 'Active redirects configured', 'seo-ai' ); ?>
			</p>
			<p style="margin-top:12px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-ai-redirects' ) ); ?>" class="button">
					<?php esc_html_e( 'Manage Redirects', 'seo-ai' ); ?>
				</a>
			</p>
		</div>

		<!-- 404 Errors -->
		<div class="seo-ai-card">
			<h2><?php esc_html_e( '404 Errors', 'seo-ai' ); ?></h2>
			<p style="font-size:28px;font-weight:700;margin:8px 0;color:<?php echo $recent_404 > 0 ? '#dc2626' : '#16a34a'; ?>">
				<?php echo esc_html( $recent_404 ); ?>
			</p>
			<p style="color:#6b7280;font-size:13px;margin:0;">
				<?php
				printf(
					/* translators: %d: total 404 count */
					esc_html__( 'In the last 7 days (%d total)', 'seo-ai' ),
					$total_404
				);
				?>
			</p>
			<p style="margin-top:12px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-ai-404-log' ) ); ?>" class="button">
					<?php esc_html_e( 'View 404 Log', 'seo-ai' ); ?>
				</a>
			</p>
		</div>

		<!-- AI Provider -->
		<div class="seo-ai-card">
			<h2><?php esc_html_e( 'AI Provider', 'seo-ai' ); ?></h2>
			<p style="font-size:28px;font-weight:700;margin:8px 0;">
				<?php echo esc_html( ucfirst( $active_provider ) ); ?>
			</p>
			<p style="color:#6b7280;font-size:13px;margin:0;">
				<?php
				if ( 'none' === $active_provider ) {
					esc_html_e( 'No provider configured', 'seo-ai' );
				} else {
					esc_html_e( 'Currently active provider', 'seo-ai' );
				}
				?>
			</p>
			<p style="margin-top:12px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-ai-settings#providers' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'seo-ai' ); ?>
				</a>
			</p>
		</div>
	</div>

	<!-- Active Modules -->
	<div class="seo-ai-card">
		<h2><?php esc_html_e( 'Active Modules', 'seo-ai' ); ?></h2>
		<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
			<?php foreach ( $modules as $module ) : ?>
				<?php
				$is_active   = $module_manager->is_module_enabled( $module['id'] );
				$badge_class = $is_active ? 'seo-ai-badge-success' : 'seo-ai-badge-muted';
				?>
				<span class="seo-ai-badge <?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( $module['name'] ); ?>
				</span>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:12px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-ai-settings#general' ) ); ?>" class="button">
				<?php esc_html_e( 'Manage Modules', 'seo-ai' ); ?>
			</a>
		</p>
	</div>

	<!-- Recent Activity -->
	<div class="seo-ai-card">
		<div style="display:flex;justify-content:space-between;align-items:center;">
			<h2 style="margin:0;"><?php esc_html_e( 'Recent Activity', 'seo-ai' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-ai-logs' ) ); ?>" class="button button-small">
				<?php esc_html_e( 'View All', 'seo-ai' ); ?>
			</a>
		</div>
		<div id="seo-ai-activity-list" class="seo-ai-activity-list" style="margin-top:12px;">
			<?php if ( empty( $recent_logs ) ) : ?>
				<p style="color:#6b7280;"><?php esc_html_e( 'No activity recorded yet.', 'seo-ai' ); ?></p>
			<?php else : ?>
				<?php foreach ( $recent_logs as $log ) : ?>
					<div class="seo-ai-activity-item">
						<span class="seo-ai-activity-badge seo-ai-activity-badge-<?php echo esc_attr( $log['level'] ?? 'info' ); ?>">
							<?php echo esc_html( $log['level'] ?? 'info' ); ?>
						</span>
						<span class="seo-ai-activity-message"><?php echo esc_html( $log['message'] ); ?></span>
						<span class="seo-ai-activity-time" title="<?php echo esc_attr( $log['created_at'] ); ?>">
							<?php echo esc_html( human_time_diff( strtotime( $log['created_at'] ), current_time( 'timestamp', true ) ) ); ?>
							<?php esc_html_e( 'ago', 'seo-ai' ); ?>
						</span>
						<?php if ( ! empty( $log['context'] ) ) : ?>
							<button type="button" class="seo-ai-activity-expand" data-context="<?php echo esc_attr( is_string( $log['context'] ) ? $log['context'] : wp_json_encode( $log['context'] ) ); ?>">
								&hellip;
							</button>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Quick Actions -->
	<?php if ( $low_count > 0 ) : ?>
	<div class="seo-ai-card">
		<h2><?php esc_html_e( 'Quick Actions', 'seo-ai' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?orderby=seo_ai_score&order=asc' ) ); ?>" class="button button-primary">
				<?php
				printf(
					/* translators: %d: number of low-score posts */
					esc_html__( 'Review %d Low-Score Posts', 'seo-ai' ),
					$low_count
				);
				?>
			</a>
		</p>
	</div>
	<?php endif; ?>
</div>

<?php
// Include the optimization wizard modal.
$modal_path = SEO_AI_PATH . 'includes/admin/views/dashboard/partials/optimize-modal.php';
if ( file_exists( $modal_path ) ) {
	include $modal_path;
}
?>
