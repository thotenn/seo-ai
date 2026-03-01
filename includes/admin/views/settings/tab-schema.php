<?php defined('ABSPATH') || exit;
$settings = get_option('seo_ai_settings', []);
?>
<div class="seo-ai-card">
    <h2>Knowledge Graph / Organization</h2>
    <p class="description">This information is used to generate Schema.org structured data for your site.</p>

    <table class="form-table">
        <tr>
            <th><label for="seo_ai_schema_type">Represent this site as</label></th>
            <td>
                <select name="seo_ai_settings[schema_type]" id="seo_ai_schema_type">
                    <option value="Organization" <?php selected($settings['schema_type'] ?? 'Organization', 'Organization'); ?>>Organization</option>
                    <option value="Person" <?php selected($settings['schema_type'] ?? '', 'Person'); ?>>Person</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_org_name">Name</label></th>
            <td>
                <input type="text" name="seo_ai_settings[org_name]" id="seo_ai_org_name"
                       value="<?php echo esc_attr($settings['org_name'] ?? get_bloginfo('name')); ?>"
                       class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_org_description">Description</label></th>
            <td>
                <textarea name="seo_ai_settings[org_description]" id="seo_ai_org_description"
                          rows="3" class="large-text"><?php echo esc_textarea($settings['org_description'] ?? ''); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_org_logo">Logo URL</label></th>
            <td>
                <input type="url" name="seo_ai_settings[org_logo]" id="seo_ai_org_logo"
                       value="<?php echo esc_attr($settings['org_logo'] ?? ''); ?>"
                       class="regular-text" />
                <button type="button" class="button seo-ai-upload-image" data-target="seo_ai_org_logo">Upload</button>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_org_email">Email</label></th>
            <td>
                <input type="email" name="seo_ai_settings[org_email]" id="seo_ai_org_email"
                       value="<?php echo esc_attr($settings['org_email'] ?? ''); ?>"
                       class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_org_phone">Phone</label></th>
            <td>
                <input type="text" name="seo_ai_settings[org_phone]" id="seo_ai_org_phone"
                       value="<?php echo esc_attr($settings['org_phone'] ?? ''); ?>"
                       class="regular-text" placeholder="+1-234-567-890" />
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_org_address">Address</label></th>
            <td>
                <textarea name="seo_ai_settings[org_address]" id="seo_ai_org_address"
                          rows="2" class="large-text"><?php echo esc_textarea($settings['org_address'] ?? ''); ?></textarea>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2>Social Profiles</h2>
    <p class="description">Used in Organization schema sameAs property.</p>
    <table class="form-table">
        <?php
        $social_fields = [
            'facebook'  => ['label' => 'Facebook URL', 'placeholder' => 'https://facebook.com/yourpage'],
            'twitter'   => ['label' => 'Twitter/X URL', 'placeholder' => 'https://x.com/yourhandle'],
            'instagram' => ['label' => 'Instagram URL', 'placeholder' => 'https://instagram.com/yourhandle'],
            'linkedin'  => ['label' => 'LinkedIn URL', 'placeholder' => 'https://linkedin.com/company/yourcompany'],
            'youtube'   => ['label' => 'YouTube URL', 'placeholder' => 'https://youtube.com/@yourchannel'],
        ];
        $social = $settings['org_social_profiles'] ?? [];
        foreach ($social_fields as $key => $field):
        ?>
        <tr>
            <th><label><?php echo esc_html($field['label']); ?></label></th>
            <td>
                <input type="url" name="seo_ai_settings[org_social_profiles][<?php echo esc_attr($key); ?>]"
                       value="<?php echo esc_attr($social[$key] ?? ''); ?>"
                       class="regular-text" placeholder="<?php echo esc_attr($field['placeholder']); ?>" />
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
