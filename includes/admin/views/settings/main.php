<?php defined('ABSPATH') || exit;
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
$tabs = [
    'general'   => __('General', 'seo-ai'),
    'providers' => __('AI Providers', 'seo-ai'),
    'content'   => __('Content Analysis', 'seo-ai'),
    'schema'    => __('Schema', 'seo-ai'),
    'social'    => __('Social Media', 'seo-ai'),
    'sitemap'   => __('Sitemap', 'seo-ai'),
    'redirects' => __('Redirects', 'seo-ai'),
    'advanced'  => __('Advanced', 'seo-ai'),
];
?>
<div class="wrap seo-ai-wrap">
    <h1 class="seo-ai-header">
        <span class="seo-ai-logo">SEO AI</span>
        <span class="seo-ai-version">v<?php echo esc_html(SEO_AI_VERSION); ?></span>
    </h1>

    <nav class="nav-tab-wrapper seo-ai-tabs">
        <?php foreach ($tabs as $slug => $label): ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $slug)); ?>"
               class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="seo-ai-settings-content" id="seo-ai-settings-form">
        <?php
        $view_file = SEO_AI_PATH . 'includes/admin/views/settings/tab-' . $active_tab . '.php';
        if (file_exists($view_file)) {
            include $view_file;
        }
        ?>

        <div class="seo-ai-settings-footer">
            <button type="button" class="button button-primary seo-ai-btn-save" id="seo-ai-save-settings">
                Save Settings
            </button>
            <?php if ($active_tab !== 'providers'): ?>
            <button type="button" class="button seo-ai-btn-reset" id="seo-ai-restore-defaults">
                Restore Defaults
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
