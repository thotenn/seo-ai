<?php
defined('ABSPATH') || exit;

// Calculate stats
$total_posts = wp_count_posts('post');
$published = $total_posts->publish ?? 0;

global $wpdb;
$scored = (int)$wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND meta_value > 0"
);
$avg_score = (int)$wpdb->get_var(
    "SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND meta_value > 0"
);
$low_score = (int)$wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_ai_seo_score' AND CAST(meta_value AS UNSIGNED) < 40 AND meta_value > 0"
);

$redirects_table = $wpdb->prefix . 'seo_ai_redirects';
$redirect_count = 0;
if ($wpdb->get_var("SHOW TABLES LIKE '{$redirects_table}'") === $redirects_table) {
    $redirect_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$redirects_table}");
}

$log_table = $wpdb->prefix . 'seo_ai_404_log';
$recent_404 = 0;
if ($wpdb->get_var("SHOW TABLES LIKE '{$log_table}'") === $log_table) {
    $recent_404 = (int)$wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$log_table} WHERE last_hit > %s", gmdate('Y-m-d H:i:s', strtotime('-7 days')))
    );
}

$providers = get_option('seo_ai_providers', []);
$active_provider = $providers['active_provider'] ?? 'none';
?>

<div class="seo-ai-dashboard-stats">
    <div class="seo-ai-stat">
        <span class="seo-ai-stat-value" style="color:<?php echo $avg_score >= 70 ? '#16a34a' : ($avg_score >= 40 ? '#f59e0b' : '#dc2626'); ?>">
            <?php echo $avg_score ?: '—'; ?>
        </span>
        <span class="seo-ai-stat-label">Avg SEO Score</span>
    </div>
    <div class="seo-ai-stat">
        <span class="seo-ai-stat-value"><?php echo $scored; ?>/<?php echo $published; ?></span>
        <span class="seo-ai-stat-label">Posts Analyzed</span>
    </div>
    <div class="seo-ai-stat">
        <span class="seo-ai-stat-value" style="color:<?php echo $low_score > 0 ? '#dc2626' : '#16a34a'; ?>">
            <?php echo $low_score; ?>
        </span>
        <span class="seo-ai-stat-label">Need Attention</span>
    </div>
</div>

<div style="display:flex;gap:16px;margin-bottom:12px;">
    <div style="flex:1;">
        <strong>Redirects:</strong> <?php echo $redirect_count; ?>
    </div>
    <div style="flex:1;">
        <strong>404s (7d):</strong> <?php echo $recent_404; ?>
    </div>
    <div style="flex:1;">
        <strong>AI Provider:</strong> <?php echo esc_html(ucfirst($active_provider)); ?>
    </div>
</div>

<p>
    <a href="<?php echo esc_url(admin_url('admin.php?page=seo-ai')); ?>" class="button">SEO AI Settings</a>
    <?php if ($low_score > 0): ?>
    <a href="<?php echo esc_url(admin_url('edit.php?orderby=seo_ai_score&order=asc')); ?>" class="button">View Low-Score Posts</a>
    <?php endif; ?>
</p>
