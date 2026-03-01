<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>Redirect Settings</h2>
    <table class="form-table">
        <tr>
            <th><label>Auto-Redirect on Slug Change</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[auto_redirect_slug_change]" value="1"
                           <?php checked($settings['auto_redirect_slug_change'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <p class="description">Automatically create a 301 redirect when a post's URL slug is changed.</p>
            </td>
        </tr>
        <tr>
            <th><label>Enable 404 Monitoring</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[redirect_404_monitoring]" value="1"
                           <?php checked($settings['redirect_404_monitoring'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <p class="description">Log 404 (Not Found) errors so you can create redirects for them.</p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_404_limit">404 Log Limit</label></th>
            <td>
                <input type="number" name="seo_ai_settings[redirect_404_log_limit]" id="seo_ai_404_limit"
                       value="<?php echo esc_attr($settings['redirect_404_log_limit'] ?? 1000); ?>"
                       min="100" max="10000" step="100" />
                <p class="description">Maximum number of 404 entries to keep. Oldest entries are deleted when limit is reached.</p>
            </td>
        </tr>
    </table>
</div>
