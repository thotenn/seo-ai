<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>Content Analysis Settings</h2>

    <table class="form-table">
        <tr>
            <th>Enable Analysis for Post Types</th>
            <td>
                <?php
                $post_types = get_post_types(['public' => true], 'objects');
                $analysis_types = $settings['analysis_post_types'] ?? ['post', 'page'];
                foreach ($post_types as $pt):
                    if ($pt->name === 'attachment') continue;
                ?>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[analysis_post_types][]"
                           value="<?php echo esc_attr($pt->name); ?>"
                           <?php checked(in_array($pt->name, (array)$analysis_types)); ?> />
                    <?php echo esc_html($pt->labels->name); ?>
                </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_min_content">Minimum Content Length</label></th>
            <td>
                <input type="number" name="seo_ai_settings[min_content_length]" id="seo_ai_min_content"
                       value="<?php echo esc_attr($settings['min_content_length'] ?? 300); ?>"
                       min="0" max="5000" step="50" />
                <span>words</span>
            </td>
        </tr>
        <tr>
            <th><label>Keyword Density Range</label></th>
            <td>
                <input type="number" name="seo_ai_settings[keyword_density_min]"
                       value="<?php echo esc_attr($settings['keyword_density_min'] ?? '1.0'); ?>"
                       min="0" max="5" step="0.1" style="width:80px" />
                <span>% to</span>
                <input type="number" name="seo_ai_settings[keyword_density_max]"
                       value="<?php echo esc_attr($settings['keyword_density_max'] ?? '3.0'); ?>"
                       min="0" max="10" step="0.1" style="width:80px" />
                <span>%</span>
                <p class="description">Recommended: 1% - 3%</p>
            </td>
        </tr>
    </table>
</div>
