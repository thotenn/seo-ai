<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>Open Graph Settings</h2>
    <table class="form-table">
        <tr>
            <th><label>Enable Open Graph</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[og_enabled]" value="1"
                           <?php checked($settings['og_enabled'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_og_image">Default OG Image</label></th>
            <td>
                <input type="url" name="seo_ai_settings[og_default_image]" id="seo_ai_og_image"
                       value="<?php echo esc_attr($settings['og_default_image'] ?? ''); ?>"
                       class="regular-text" />
                <button type="button" class="button seo-ai-upload-image" data-target="seo_ai_og_image">Upload</button>
                <p class="description">Fallback image when posts don't have a featured image. Recommended: 1200x630px.</p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_fb_app_id">Facebook App ID</label></th>
            <td>
                <input type="text" name="seo_ai_settings[facebook_app_id]" id="seo_ai_fb_app_id"
                       value="<?php echo esc_attr($settings['facebook_app_id'] ?? ''); ?>"
                       class="regular-text" />
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>Twitter Card Settings</h2>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_twitter_card">Card Type</label></th>
            <td>
                <select name="seo_ai_settings[twitter_card_type]" id="seo_ai_twitter_card">
                    <option value="summary_large_image" <?php selected($settings['twitter_card_type'] ?? 'summary_large_image', 'summary_large_image'); ?>>Summary with Large Image</option>
                    <option value="summary" <?php selected($settings['twitter_card_type'] ?? '', 'summary'); ?>>Summary</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_twitter_site">Twitter Site Handle</label></th>
            <td>
                <input type="text" name="seo_ai_settings[twitter_site]" id="seo_ai_twitter_site"
                       value="<?php echo esc_attr($settings['twitter_site'] ?? ''); ?>"
                       class="regular-text" placeholder="@yourhandle" />
            </td>
        </tr>
    </table>
</div>
