<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>Advanced Settings</h2>
    <table class="form-table">
        <tr>
            <th>Clean Up HTML Head</th>
            <td>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[remove_shortlinks]" value="1"
                           <?php checked($settings['remove_shortlinks'] ?? true); ?> />
                    Remove shortlinks
                </label>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[remove_rsd_link]" value="1"
                           <?php checked($settings['remove_rsd_link'] ?? true); ?> />
                    Remove RSD link
                </label>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[remove_wlw_link]" value="1"
                           <?php checked($settings['remove_wlw_link'] ?? true); ?> />
                    Remove WLW Manifest link
                </label>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[remove_generator_tag]" value="1"
                           <?php checked($settings['remove_generator_tag'] ?? true); ?> />
                    Remove WordPress generator tag
                </label>
            </td>
        </tr>
        <tr>
            <th>URL Settings</th>
            <td>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[add_trailing_slash]" value="1"
                           <?php checked($settings['add_trailing_slash'] ?? true); ?> />
                    Force trailing slash on URLs
                </label>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[strip_category_base]" value="1"
                           <?php checked($settings['strip_category_base'] ?? false); ?> />
                    Strip category base from URLs (e.g., /category/news/ becomes /news/)
                </label>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>Image SEO</h2>
    <table class="form-table">
        <tr>
            <th><label>Auto Alt Text</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[image_auto_alt]" value="1"
                           <?php checked($settings['image_auto_alt'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <span class="description">Automatically add alt text to images that don't have one.</span>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_alt_template">Alt Text Template</label></th>
            <td>
                <input type="text" name="seo_ai_settings[image_alt_template]" id="seo_ai_alt_template"
                       value="<?php echo esc_attr($settings['image_alt_template'] ?? '%filename%'); ?>"
                       class="regular-text" />
                <p class="description">Variables: <code>%filename%</code>, <code>%title%</code>, <code>%site_name%</code></p>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>Breadcrumbs</h2>
    <table class="form-table">
        <tr>
            <th><label>Enable Breadcrumbs</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[breadcrumb_enabled]" value="1"
                           <?php checked($settings['breadcrumb_enabled'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <p class="description">Use shortcode <code>[seo_ai_breadcrumb]</code> or <code>&lt;?php seo_ai_breadcrumb(); ?&gt;</code> in your theme.</p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_breadcrumb_sep">Separator</label></th>
            <td>
                <input type="text" name="seo_ai_settings[breadcrumb_separator]" id="seo_ai_breadcrumb_sep"
                       value="<?php echo esc_attr($settings['breadcrumb_separator'] ?? '»'); ?>"
                       class="small-text" />
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_breadcrumb_home">Home Text</label></th>
            <td>
                <input type="text" name="seo_ai_settings[breadcrumb_home_text]" id="seo_ai_breadcrumb_home"
                       value="<?php echo esc_attr($settings['breadcrumb_home_text'] ?? 'Home'); ?>"
                       class="regular-text" />
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>Custom AI Prompts</h2>
    <p class="description">Override the default AI prompts used for SEO optimization. Leave empty to use defaults.</p>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_prompt_title">Meta Title Prompt</label></th>
            <td>
                <textarea name="seo_ai_settings[ai_prompt_title]" id="seo_ai_prompt_title"
                          rows="3" class="large-text"><?php echo esc_textarea($settings['ai_prompt_title'] ?? ''); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_prompt_desc">Meta Description Prompt</label></th>
            <td>
                <textarea name="seo_ai_settings[ai_prompt_description]" id="seo_ai_prompt_desc"
                          rows="3" class="large-text"><?php echo esc_textarea($settings['ai_prompt_description'] ?? ''); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_prompt_opt">Optimization Prompt</label></th>
            <td>
                <textarea name="seo_ai_settings[ai_prompt_optimization]" id="seo_ai_prompt_opt"
                          rows="3" class="large-text"><?php echo esc_textarea($settings['ai_prompt_optimization'] ?? ''); ?></textarea>
            </td>
        </tr>
    </table>
</div>
