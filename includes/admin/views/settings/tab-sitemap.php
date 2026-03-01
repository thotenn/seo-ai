<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>XML Sitemap Settings</h2>
    <table class="form-table">
        <tr>
            <th><label>Enable XML Sitemap</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[sitemap_enabled]" value="1"
                           <?php checked($settings['sitemap_enabled'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <?php if (!empty($settings['sitemap_enabled'])): ?>
                <p class="description">
                    Your sitemap: <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank"><?php echo esc_html(home_url('/sitemap.xml')); ?></a>
                </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Include Post Types</th>
            <td>
                <?php
                $post_types = get_post_types(['public' => true], 'objects');
                $sitemap_types = $settings['sitemap_post_types'] ?? ['post', 'page'];
                foreach ($post_types as $pt):
                    if ($pt->name === 'attachment') continue;
                ?>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[sitemap_post_types][]"
                           value="<?php echo esc_attr($pt->name); ?>"
                           <?php checked(in_array($pt->name, (array)$sitemap_types)); ?> />
                    <?php echo esc_html($pt->labels->name); ?>
                </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th>Include Taxonomies</th>
            <td>
                <?php
                $taxonomies = get_taxonomies(['public' => true], 'objects');
                $sitemap_taxes = $settings['sitemap_taxonomies'] ?? ['category'];
                foreach ($taxonomies as $tax):
                ?>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[sitemap_taxonomies][]"
                           value="<?php echo esc_attr($tax->name); ?>"
                           <?php checked(in_array($tax->name, (array)$sitemap_taxes)); ?> />
                    <?php echo esc_html($tax->labels->name); ?>
                </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_sitemap_max">Max Entries per Sitemap</label></th>
            <td>
                <input type="number" name="seo_ai_settings[sitemap_max_entries]" id="seo_ai_sitemap_max"
                       value="<?php echo esc_attr($settings['sitemap_max_entries'] ?? 1000); ?>"
                       min="100" max="50000" step="100" />
            </td>
        </tr>
        <tr>
            <th><label>Include Images</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[sitemap_include_images]" value="1"
                           <?php checked($settings['sitemap_include_images'] ?? true); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
            </td>
        </tr>
    </table>
</div>
