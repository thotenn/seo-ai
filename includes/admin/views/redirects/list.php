<?php
defined('ABSPATH') || exit;
$table = new SeoAi\Modules\Redirects\Redirect_Table();
$table->prepare_items();
?>
<div class="wrap seo-ai-wrap">
    <h1 class="seo-ai-header">
        <span class="seo-ai-logo">SEO AI</span>
        <span>— Redirects</span>
    </h1>

    <!-- Add Redirect Form -->
    <div class="seo-ai-card">
        <h2>Add Redirect</h2>
        <form method="post" class="seo-ai-redirect-form" id="seo-ai-add-redirect">
            <?php wp_nonce_field('seo_ai_redirect', 'seo_ai_redirect_nonce'); ?>
            <div>
                <label>Source URL</label>
                <input type="text" name="source_url" placeholder="/old-page/" required />
            </div>
            <div>
                <label>Target URL</label>
                <input type="text" name="target_url" placeholder="/new-page/" />
            </div>
            <div>
                <label>Type</label>
                <select name="type">
                    <option value="301">301 Permanent</option>
                    <option value="302">302 Found</option>
                    <option value="307">307 Temporary</option>
                    <option value="410">410 Deleted</option>
                    <option value="451">451 Unavailable</option>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <?php submit_button('Add Redirect', 'primary', 'seo_ai_add_redirect', false); ?>
            </div>
        </form>
    </div>

    <!-- Redirects List -->
    <div class="seo-ai-card">
        <form method="get">
            <input type="hidden" name="page" value="seo-ai-redirects" />
            <?php $table->search_box('Search Redirects', 'seo_ai_redirect_search'); ?>
            <?php $table->display(); ?>
        </form>
    </div>
</div>
