<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>General Settings</h2>

    <table class="form-table">
        <tr>
            <th><label for="seo_ai_title_separator">Title Separator</label></th>
            <td>
                <select name="seo_ai_settings[title_separator]" id="seo_ai_title_separator">
                    <?php
                    $separators = ['–' => '–', '|' => '|', '-' => '-', '·' => '·', '»' => '»', '/' => '/'];
                    $current = $settings['title_separator'] ?? '–';
                    foreach ($separators as $val => $label):
                    ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Choose the separator used in page titles.</p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_default_title">Default Title Template</label></th>
            <td>
                <input type="text" name="seo_ai_settings[default_title]" id="seo_ai_default_title"
                       value="<?php echo esc_attr($settings['default_title'] ?? '%title% %sep% %sitename%'); ?>"
                       class="large-text" />
                <p class="description">Variables: <code>%title%</code>, <code>%sitename%</code>, <code>%sep%</code>, <code>%excerpt%</code>, <code>%category%</code>, <code>%date%</code>, <code>%author%</code>, <code>%page%</code>, <code>%tagline%</code></p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_homepage_title">Homepage Title</label></th>
            <td>
                <input type="text" name="seo_ai_settings[homepage_title]" id="seo_ai_homepage_title"
                       value="<?php echo esc_attr($settings['homepage_title'] ?? '%sitename% %sep% %tagline%'); ?>"
                       class="large-text" />
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_homepage_description">Homepage Description</label></th>
            <td>
                <textarea name="seo_ai_settings[homepage_description]" id="seo_ai_homepage_description"
                          rows="3" class="large-text"><?php echo esc_textarea($settings['homepage_description'] ?? ''); ?></textarea>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>AI Auto-SEO</h2>
    <p class="description">When enabled, SEO metadata will be automatically generated using AI when you publish or update content.</p>

    <table class="form-table">
        <tr>
            <th><label for="seo_ai_auto_seo">Enable Auto-SEO</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[auto_seo_enabled]" id="seo_ai_auto_seo"
                           value="1" <?php checked(!empty($settings['auto_seo_enabled'])); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <span class="description">Automatically optimize SEO when publishing/updating posts.</span>
            </td>
        </tr>
        <tr>
            <th>Auto-SEO Post Types</th>
            <td>
                <?php
                $post_types = get_post_types(['public' => true], 'objects');
                $auto_types = $settings['auto_seo_post_types'] ?? ['post'];
                foreach ($post_types as $pt):
                    if ($pt->name === 'attachment') continue;
                ?>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[auto_seo_post_types][]"
                           value="<?php echo esc_attr($pt->name); ?>"
                           <?php checked(in_array($pt->name, (array)$auto_types)); ?> />
                    <?php echo esc_html($pt->labels->name); ?>
                </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th>Auto-SEO Fields</th>
            <td>
                <?php
                $fields_options = [
                    'title'       => 'Meta Title',
                    'description' => 'Meta Description',
                    'keyword'     => 'Focus Keyword',
                    'schema'      => 'Schema Type',
                    'og'          => 'Open Graph Tags',
                ];
                $auto_fields = $settings['auto_seo_fields'] ?? ['title', 'description', 'keyword', 'schema', 'og'];
                foreach ($fields_options as $val => $label):
                ?>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="seo_ai_settings[auto_seo_fields][]"
                           value="<?php echo esc_attr($val); ?>"
                           <?php checked(in_array($val, (array)$auto_fields)); ?> />
                    <?php echo esc_html($label); ?>
                </label>
                <?php endforeach; ?>
                <p class="description">Select which fields should be auto-generated by AI. Only empty/missing fields will be generated.</p>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>Post Type SEO Defaults</h2>
    <?php
    $post_types = get_post_types(['public' => true], 'objects');
    foreach ($post_types as $pt):
        if ($pt->name === 'attachment') continue;
        $key = $pt->name;
    ?>
    <div class="seo-ai-post-type-section">
        <h3><?php echo esc_html($pt->labels->name); ?></h3>
        <table class="form-table">
            <tr>
                <th><label>Title Template</label></th>
                <td>
                    <input type="text" name="seo_ai_settings[pt_<?php echo esc_attr($key); ?>_title]"
                           value="<?php echo esc_attr($settings["pt_{$key}_title"] ?? '%title% %sep% %sitename%'); ?>"
                           class="large-text" />
                </td>
            </tr>
            <tr>
                <th><label>Default Schema</label></th>
                <td>
                    <select name="seo_ai_settings[pt_<?php echo esc_attr($key); ?>_schema]">
                        <?php
                        $schemas = ['Article', 'WebPage', 'BlogPosting', 'NewsArticle', 'Product', 'FAQPage', 'HowTo', 'Recipe', 'Event', 'JobPosting'];
                        $current_schema = $settings["pt_{$key}_schema"] ?? ($key === 'post' ? 'Article' : 'WebPage');
                        foreach ($schemas as $s):
                        ?>
                        <option value="<?php echo esc_attr($s); ?>" <?php selected($current_schema, $s); ?>><?php echo esc_html($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Noindex by default</label></th>
                <td>
                    <label class="seo-ai-toggle">
                        <input type="checkbox" name="seo_ai_settings[pt_<?php echo esc_attr($key); ?>_noindex]"
                               value="1" <?php checked(!empty($settings["pt_{$key}_noindex"])); ?> />
                        <span class="seo-ai-toggle-slider"></span>
                    </label>
                </td>
            </tr>
        </table>
    </div>
    <?php endforeach; ?>
</div>
