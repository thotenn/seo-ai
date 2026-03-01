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

<div class="seo-ai-card">
    <h2><?php esc_html_e( 'Video Sitemap', 'seo-ai' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><label><?php esc_html_e( 'Enable Video Sitemap', 'seo-ai' ); ?></label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[video_sitemap_enabled]" value="1"
                           <?php checked( $settings['video_sitemap_enabled'] ?? false ); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <span class="description"><?php esc_html_e( 'Generate a dedicated video sitemap for embedded YouTube, Vimeo, and HTML5 videos.', 'seo-ai' ); ?></span>
                <?php if ( ! empty( $settings['video_sitemap_enabled'] ) ) : ?>
                <p class="description">
                    <?php esc_html_e( 'Your video sitemap:', 'seo-ai' ); ?>
                    <a href="<?php echo esc_url( home_url( '/video-sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/video-sitemap.xml' ) ); ?></a>
                </p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<div class="seo-ai-card">
    <h2><?php esc_html_e( 'News Sitemap', 'seo-ai' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><label><?php esc_html_e( 'Enable News Sitemap', 'seo-ai' ); ?></label></th>
            <td>
                <label class="seo-ai-toggle">
                    <input type="checkbox" name="seo_ai_settings[news_sitemap_enabled]" value="1"
                           <?php checked( $settings['news_sitemap_enabled'] ?? false ); ?> />
                    <span class="seo-ai-toggle-slider"></span>
                </label>
                <span class="description"><?php esc_html_e( 'Google News compliant sitemap. Only includes articles from the last 48 hours.', 'seo-ai' ); ?></span>
                <?php if ( ! empty( $settings['news_sitemap_enabled'] ) ) : ?>
                <p class="description">
                    <?php esc_html_e( 'Your news sitemap:', 'seo-ai' ); ?>
                    <a href="<?php echo esc_url( home_url( '/news-sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/news-sitemap.xml' ) ); ?></a>
                </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_news_pub_name"><?php esc_html_e( 'Publication Name', 'seo-ai' ); ?></label></th>
            <td>
                <input type="text" name="seo_ai_settings[news_sitemap_publication_name]" id="seo_ai_news_pub_name"
                       value="<?php echo esc_attr( $settings['news_sitemap_publication_name'] ?? get_bloginfo( 'name' ) ); ?>"
                       class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'News Post Types', 'seo-ai' ); ?></th>
            <td>
                <?php
                $news_types = $settings['news_sitemap_post_types'] ?? [ 'post' ];
                $all_public = get_post_types( [ 'public' => true ], 'objects' );
                foreach ( $all_public as $pt ) :
                    if ( 'attachment' === $pt->name ) {
                        continue;
                    }
                    ?>
                    <label style="display:block;margin-bottom:4px;">
                        <input type="checkbox" name="seo_ai_settings[news_sitemap_post_types][]"
                               value="<?php echo esc_attr( $pt->name ); ?>"
                               <?php checked( in_array( $pt->name, (array) $news_types, true ) ); ?> />
                        <?php echo esc_html( $pt->labels->name ); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>
</div>
