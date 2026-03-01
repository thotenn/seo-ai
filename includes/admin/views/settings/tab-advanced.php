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
                <p class="description">Variables: <code>%filename%</code>, <code>%title%</code>, <code>%caption%</code>, <code>%sitename%</code></p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_alt_case">Alt Text Case</label></th>
            <td>
                <?php $alt_case = $settings['image_alt_case'] ?? 'title'; ?>
                <select name="seo_ai_settings[image_alt_case]" id="seo_ai_alt_case">
                    <option value="title" <?php selected($alt_case, 'title'); ?>>Title Case</option>
                    <option value="sentence" <?php selected($alt_case, 'sentence'); ?>>Sentence case</option>
                    <option value="lower" <?php selected($alt_case, 'lower'); ?>>lowercase</option>
                    <option value="upper" <?php selected($alt_case, 'upper'); ?>>UPPERCASE</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label>Auto Caption</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[image_auto_caption]" value="1"
                           <?php checked($settings['image_auto_caption'] ?? false); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <span class="description">Auto-fill image caption on upload from filename.</span>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_caption_template">Caption Template</label></th>
            <td>
                <input type="text" name="seo_ai_settings[image_caption_template]" id="seo_ai_caption_template"
                       value="<?php echo esc_attr($settings['image_caption_template'] ?? '%filename%'); ?>"
                       class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label>Auto Description</label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[image_auto_description]" value="1"
                           <?php checked($settings['image_auto_description'] ?? false); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <span class="description">Auto-fill image description on upload from filename.</span>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_desc_template">Description Template</label></th>
            <td>
                <input type="text" name="seo_ai_settings[image_description_template]" id="seo_ai_desc_template"
                       value="<?php echo esc_attr($settings['image_description_template'] ?? '%filename%'); ?>"
                       class="regular-text" />
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
    <h2>Robots.txt</h2>
    <p class="description">
        <?php esc_html_e('Add custom rules to your robots.txt file. Default rules (wp-admin, wp-includes, etc.) are always included.', 'seo-ai'); ?>
        <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank"><?php esc_html_e('View current robots.txt', 'seo-ai'); ?> &rarr;</a>
    </p>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_robots_rules">Custom Rules</label></th>
            <td>
                <textarea name="seo_ai_settings[robots_custom_rules]" id="seo_ai_robots_rules"
                          rows="8" class="large-text" style="font-family:monospace;font-size:13px;"
                          placeholder="User-agent: *&#10;Disallow: /private/&#10;Allow: /public/"
                ><?php echo esc_textarea($settings['robots_custom_rules'] ?? ''); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Valid directives: User-agent, Disallow, Allow, Sitemap, Crawl-delay, Host, # (comments).', 'seo-ai'); ?>
                </p>
                <div id="seo-ai-robots-validation" style="margin-top:8px;"></div>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>CSV Import / Export</h2>
    <p class="description"><?php esc_html_e( 'Export or import SEO metadata for all posts of a given type.', 'seo-ai' ); ?></p>

    <?php
    // Show import result notices.
    if ( isset( $_GET['csv_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $notices = get_transient( 'seo_ai_csv_import_result' );
        if ( is_array( $notices ) ) {
            foreach ( $notices as $notice ) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $notice['type'] ),
                    esc_html( $notice['message'] )
                );
            }
            delete_transient( 'seo_ai_csv_import_result' );
        }
    }
    ?>

    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Export SEO Data', 'seo-ai' ); ?></th>
            <td>
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="page" value="seo-ai-settings" />
                    <input type="hidden" name="seo_ai_csv_export" value="1" />
                    <?php wp_nonce_field( 'seo_ai_csv_export', '_wpnonce', false ); ?>
                    <select name="seo_ai_csv_post_type">
                        <?php
                        $default_pts  = [ 'post', 'page' ];
                        $configured   = $settings['analysis_post_types'] ?? $default_pts;
                        $export_types = (array) apply_filters( 'seo_ai/post_types', $configured );
                        foreach ( $export_types as $pt ) :
                            $obj = get_post_type_object( $pt );
                            ?>
                            <option value="<?php echo esc_attr( $pt ); ?>">
                                <?php echo esc_html( $obj ? $obj->labels->singular_name : $pt ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e( 'Export CSV', 'seo-ai' ); ?></button>
                </form>
                <p class="description"><?php esc_html_e( 'Downloads a CSV with post ID, title, URL, and all SEO fields.', 'seo-ai' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Import SEO Data', 'seo-ai' ); ?></th>
            <td>
                <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="seo_ai_csv_import" value="1" />
                    <?php wp_nonce_field( 'seo_ai_csv_import' ); ?>
                    <input type="file" name="seo_ai_csv_file" accept=".csv" required />
                    <button type="submit" class="button"><?php esc_html_e( 'Import CSV', 'seo-ai' ); ?></button>
                </form>
                <p class="description">
                    <?php esc_html_e( 'CSV must have a "post_id" column. Recognized fields: title, description, focus_keyword, canonical, robots, schema_type, og_title, og_description, og_image, twitter_title, twitter_description, cornerstone.', 'seo-ai' ); ?>
                </p>
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
