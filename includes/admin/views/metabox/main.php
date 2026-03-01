<?php
defined('ABSPATH') || exit;
$post_id = $post->ID;
$prefix = '_seo_ai_';
$meta = function($key, $default = '') use ($post_id, $prefix) {
    $val = get_post_meta($post_id, $prefix . $key, true);
    return $val !== '' ? $val : $default;
};
$robots = json_decode($meta('robots', '{}'), true) ?: [];
$seo_score = (int)$meta('seo_score', 0);
$read_score = (int)$meta('readability_score', 0);
$auto_seo = $meta('auto_seo', 'default');

wp_nonce_field('seo_ai_metabox', 'seo_ai_metabox_nonce');
?>
<div class="seo-ai-metabox">
    <!-- Score Bar -->
    <div class="seo-ai-scores-bar">
        <div class="seo-ai-score-item">
            <span id="seo-ai-seo-score" class="seo-ai-score-circle <?php
                echo $seo_score >= 70 ? 'seo-ai-score-good' : ($seo_score >= 40 ? 'seo-ai-score-warning' : 'seo-ai-score-error');
            ?>"><?php echo $seo_score ?: '—'; ?></span>
            <span class="seo-ai-score-label"><strong>SEO</strong>Score</span>
        </div>
        <div class="seo-ai-score-item">
            <span id="seo-ai-read-score" class="seo-ai-score-circle <?php
                echo $read_score >= 70 ? 'seo-ai-score-good' : ($read_score >= 40 ? 'seo-ai-score-warning' : 'seo-ai-score-error');
            ?>"><?php echo $read_score ?: '—'; ?></span>
            <span class="seo-ai-score-label"><strong>Readability</strong>Score</span>
        </div>
        <div style="flex:1"></div>
        <button type="button" class="seo-ai-ai-btn" id="seo-ai-optimize-all">Optimize All with AI</button>
    </div>

    <!-- Tabs -->
    <div class="seo-ai-metabox-tabs">
        <button type="button" class="seo-ai-metabox-tab active" data-tab="seo">SEO</button>
        <button type="button" class="seo-ai-metabox-tab" data-tab="readability">Readability</button>
        <button type="button" class="seo-ai-metabox-tab" data-tab="social">Social</button>
        <button type="button" class="seo-ai-metabox-tab" data-tab="schema">Schema</button>
        <button type="button" class="seo-ai-metabox-tab" data-tab="advanced">Advanced</button>
    </div>

    <!-- SEO Tab -->
    <div class="seo-ai-metabox-panel active" id="seo-ai-panel-seo">
        <!-- Google Preview -->
        <div class="seo-ai-preview">
            <div class="seo-ai-preview-title"><?php echo esc_html($meta('title') ?: get_the_title($post_id)); ?></div>
            <div class="seo-ai-preview-url"><?php echo esc_html(get_permalink($post_id)); ?></div>
            <div class="seo-ai-preview-description"><?php echo esc_html($meta('description') ?: wp_trim_words(get_the_excerpt($post_id), 25)); ?></div>
        </div>

        <!-- Focus Keyword -->
        <div class="seo-ai-field">
            <label>
                Focus Keyword
                <button type="button" class="seo-ai-check-fix" id="seo-ai-generate-keyword">Generate with AI</button>
            </label>
            <input type="text" id="seo_ai_focus_keyword" name="seo_ai[focus_keyword]"
                   value="<?php echo esc_attr($meta('focus_keyword')); ?>" placeholder="Enter focus keyword..." />
        </div>

        <!-- SEO Title -->
        <div class="seo-ai-field">
            <label>
                SEO Title
                <span class="seo-ai-field-counter"><?php echo strlen($meta('title')); ?> / 60</span>
            </label>
            <div style="display:flex;gap:6px;">
                <input type="text" id="seo_ai_title" name="seo_ai[title]" style="flex:1"
                       value="<?php echo esc_attr($meta('title')); ?>" placeholder="SEO title..." />
                <button type="button" class="seo-ai-check-fix" id="seo-ai-generate-title">Generate with AI</button>
            </div>
        </div>

        <!-- Meta Description -->
        <div class="seo-ai-field">
            <label>
                Meta Description
                <span class="seo-ai-field-counter"><?php echo strlen($meta('description')); ?> / 160</span>
            </label>
            <div style="display:flex;gap:6px;align-items:start;">
                <textarea id="seo_ai_description" name="seo_ai[description]" rows="3" style="flex:1"
                          placeholder="Meta description..."><?php echo esc_textarea($meta('description')); ?></textarea>
                <button type="button" class="seo-ai-check-fix" id="seo-ai-generate-description" style="margin-top:4px">Generate with AI</button>
            </div>
        </div>

        <!-- SEO Checks -->
        <h4>SEO Analysis</h4>
        <ul class="seo-ai-checks" id="seo-ai-seo-checks">
            <li class="seo-ai-check"><span class="seo-ai-check-text" style="color:#999;">Analysis will run when you start writing...</span></li>
        </ul>

        <input type="hidden" id="seo_ai_seo_score_val" name="seo_ai[seo_score]" value="<?php echo esc_attr($seo_score); ?>" />
        <input type="hidden" id="seo_ai_readability_score_val" name="seo_ai[readability_score]" value="<?php echo esc_attr($read_score); ?>" />
    </div>

    <!-- Readability Tab -->
    <div class="seo-ai-metabox-panel" id="seo-ai-panel-readability">
        <h4>Readability Analysis</h4>
        <ul class="seo-ai-checks" id="seo-ai-read-checks">
            <li class="seo-ai-check"><span class="seo-ai-check-text" style="color:#999;">Analysis will run when you start writing...</span></li>
        </ul>
    </div>

    <!-- Social Tab -->
    <div class="seo-ai-metabox-panel" id="seo-ai-panel-social">
        <h4>Facebook / Open Graph</h4>
        <div class="seo-ai-social-preview">
            <div class="seo-ai-social-preview-image">
                <?php
                $og_img_id = $meta('og_image');
                $thumb_id = get_post_thumbnail_id($post_id);
                $img_id = $og_img_id ?: $thumb_id;
                if ($img_id):
                    echo wp_get_attachment_image($img_id, 'large');
                else:
                    echo 'No image set';
                endif;
                ?>
            </div>
            <div class="seo-ai-social-preview-body">
                <div class="seo-ai-social-preview-domain"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></div>
                <div class="seo-ai-social-preview-title"><?php echo esc_html($meta('og_title') ?: $meta('title') ?: get_the_title($post_id)); ?></div>
                <div class="seo-ai-social-preview-desc"><?php echo esc_html($meta('og_description') ?: $meta('description')); ?></div>
            </div>
        </div>

        <div class="seo-ai-field">
            <label>OG Title</label>
            <input type="text" id="seo_ai_og_title" name="seo_ai[og_title]"
                   value="<?php echo esc_attr($meta('og_title')); ?>" placeholder="Leave empty to use SEO title" />
        </div>
        <div class="seo-ai-field">
            <label>OG Description</label>
            <textarea id="seo_ai_og_description" name="seo_ai[og_description]" rows="2"
                      placeholder="Leave empty to use meta description"><?php echo esc_textarea($meta('og_description')); ?></textarea>
        </div>
        <div class="seo-ai-field">
            <label>OG Image ID</label>
            <input type="number" name="seo_ai[og_image]" value="<?php echo esc_attr($meta('og_image')); ?>"
                   placeholder="Attachment ID (leave empty for featured image)" class="small-text" />
        </div>

        <hr />
        <h4>Twitter</h4>
        <div class="seo-ai-field">
            <label>Twitter Title</label>
            <input type="text" name="seo_ai[twitter_title]" value="<?php echo esc_attr($meta('twitter_title')); ?>"
                   placeholder="Leave empty to use OG title" />
        </div>
        <div class="seo-ai-field">
            <label>Twitter Description</label>
            <textarea name="seo_ai[twitter_description]" rows="2"
                      placeholder="Leave empty to use OG description"><?php echo esc_textarea($meta('twitter_description')); ?></textarea>
        </div>
    </div>

    <!-- Schema Tab -->
    <div class="seo-ai-metabox-panel" id="seo-ai-panel-schema">
        <div class="seo-ai-field">
            <label>Schema Type</label>
            <select name="seo_ai[schema_type]">
                <option value="">Auto-detect</option>
                <?php
                $schemas = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'FAQPage', 'HowTo', 'Product', 'Recipe', 'Event', 'JobPosting', 'Person', 'Course'];
                $current_schema = $meta('schema_type');
                foreach ($schemas as $s):
                ?>
                <option value="<?php echo esc_attr($s); ?>" <?php selected($current_schema, $s); ?>><?php echo esc_html($s); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Advanced Tab -->
    <div class="seo-ai-metabox-panel" id="seo-ai-panel-advanced">
        <div class="seo-ai-field">
            <label>Canonical URL</label>
            <input type="url" name="seo_ai[canonical]" value="<?php echo esc_attr($meta('canonical')); ?>"
                   placeholder="Leave empty for default (permalink)" />
        </div>

        <div class="seo-ai-field">
            <label>Robots Directives</label>
            <div class="seo-ai-robots-grid">
                <?php
                $robot_options = ['noindex' => 'No Index', 'nofollow' => 'No Follow', 'noarchive' => 'No Archive', 'nosnippet' => 'No Snippet', 'noimageindex' => 'No Image Index'];
                foreach ($robot_options as $key => $label):
                ?>
                <label>
                    <input type="checkbox" name="seo_ai[robots][<?php echo esc_attr($key); ?>]" value="1"
                           <?php checked(!empty($robots[$key])); ?> />
                    <?php echo esc_html($label); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="seo-ai-field">
            <label>Auto-SEO for this post</label>
            <select name="seo_ai[auto_seo]">
                <option value="default" <?php selected($auto_seo, 'default'); ?>>Use global setting</option>
                <option value="yes" <?php selected($auto_seo, 'yes'); ?>>Enabled</option>
                <option value="no" <?php selected($auto_seo, 'no'); ?>>Disabled</option>
            </select>
        </div>
    </div>
</div>
