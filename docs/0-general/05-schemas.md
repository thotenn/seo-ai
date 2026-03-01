# Database Schemas & Data Structures

## Custom Tables

### seo_ai_redirects

```sql
CREATE TABLE {prefix}seo_ai_redirects (
    id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    source_url     VARCHAR(2048) NOT NULL,
    target_url     VARCHAR(2048) NOT NULL DEFAULT '',
    type           SMALLINT(4) NOT NULL DEFAULT 301,
    is_regex       TINYINT(1) NOT NULL DEFAULT 0,
    hits           BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    status         VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY source_url (source_url(191)),
    KEY status (status),
    KEY type (type)
) {charset_collate};
```

### seo_ai_404_log

```sql
CREATE TABLE {prefix}seo_ai_404_log (
    id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    url            VARCHAR(2048) NOT NULL,
    referrer       VARCHAR(2048) DEFAULT '',
    user_agent     VARCHAR(512) DEFAULT '',
    ip_address     VARCHAR(45) DEFAULT '',
    hits           INT(11) UNSIGNED NOT NULL DEFAULT 1,
    last_hit       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY url (url(191)),
    KEY last_hit (last_hit)
) {charset_collate};
```

## WordPress Options

### seo_ai_settings (Main Settings)

```php
[
    // General
    'enabled_modules' => ['content-analysis', 'meta-tags', 'schema', 'sitemap', 'social', 'redirects', 'image-seo', 'breadcrumbs', 'robots-txt'],

    // Title & Meta
    'title_separator'     => '–',
    'default_title'       => '%title% %sep% %sitename%',
    'default_description' => '',
    'homepage_title'      => '%sitename% %sep% %tagline%',
    'homepage_description' => '',

    // Post type defaults (per post type)
    'pt_post_title'       => '%title% %sep% %sitename%',
    'pt_post_description' => '%excerpt%',
    'pt_post_schema'      => 'Article',
    'pt_post_noindex'     => false,
    'pt_page_title'       => '%title% %sep% %sitename%',
    'pt_page_description' => '%excerpt%',
    'pt_page_schema'      => 'WebPage',
    'pt_page_noindex'     => false,

    // Taxonomy defaults
    'tax_category_title'  => '%term_title% %sep% %sitename%',
    'tax_category_noindex'=> false,
    'tax_post_tag_title'  => '%term_title% %sep% %sitename%',
    'tax_post_tag_noindex'=> true,

    // Content Analysis
    'analysis_post_types' => ['post', 'page'],
    'min_content_length'  => 300,
    'keyword_density_min' => 1.0,
    'keyword_density_max' => 3.0,

    // Schema / Knowledge Graph
    'schema_type'           => 'Organization', // or 'Person'
    'org_name'              => '',
    'org_description'       => '',
    'org_logo'              => '',
    'org_url'               => '',
    'org_email'             => '',
    'org_phone'             => '',
    'org_address'           => '',
    'org_founding_date'     => '',
    'org_social_profiles'   => [],

    // Sitemap
    'sitemap_enabled'       => true,
    'sitemap_post_types'    => ['post', 'page'],
    'sitemap_taxonomies'    => ['category'],
    'sitemap_max_entries'   => 1000,
    'sitemap_include_images'=> true,
    'sitemap_ping_engines'  => true,

    // Social
    'og_enabled'            => true,
    'og_default_image'      => '',
    'twitter_card_type'     => 'summary_large_image',
    'twitter_site'          => '',
    'facebook_app_id'       => '',

    // Redirects
    'auto_redirect_slug_change' => true,
    'redirect_404_monitoring'   => true,
    'redirect_404_log_limit'    => 1000,

    // Image SEO
    'image_auto_alt'           => true,
    'image_alt_template'       => '%filename%',
    'image_auto_title'         => false,

    // Breadcrumbs
    'breadcrumb_enabled'       => true,
    'breadcrumb_separator'     => '»',
    'breadcrumb_home_text'     => 'Home',
    'breadcrumb_show_home'     => true,

    // AI / Auto-SEO
    'auto_seo_enabled'         => false,
    'auto_seo_post_types'      => ['post'],
    'auto_seo_fields'          => ['title', 'description', 'keyword', 'schema', 'og'],
    'ai_prompt_title'          => '', // Custom prompt override
    'ai_prompt_description'    => '', // Custom prompt override
    'ai_prompt_optimization'   => '', // Custom prompt override

    // Advanced
    'remove_shortlinks'        => true,
    'remove_rsd_link'          => true,
    'remove_wlw_link'          => true,
    'remove_generator_tag'     => true,
    'add_trailing_slash'       => true,
    'strip_category_base'      => false,
]
```

### seo_ai_providers (Provider Settings)

```php
[
    'active_provider' => 'ollama',
    'openai'    => ['api_key' => '', 'base_url' => 'https://api.openai.com', 'model' => 'gpt-4o-mini', 'temperature' => 0.3],
    'claude'    => ['api_key' => '', 'base_url' => 'https://api.anthropic.com', 'model' => 'claude-sonnet-4-5-20250929', 'temperature' => 0.3, 'max_tokens' => 4096],
    'gemini'    => ['api_key' => '', 'model' => 'gemini-2.0-flash', 'temperature' => 0.3],
    'ollama'    => ['base_url' => 'http://localhost:11434', 'model' => 'llama3.2', 'temperature' => 0.3],
    'openrouter'=> ['api_key' => '', 'model' => 'anthropic/claude-sonnet-4-5-20250929', 'temperature' => 0.3],
]
```

## Post Meta Schema

| Key | Type | Description |
|---|---|---|
| `_seo_ai_title` | string | Custom SEO title |
| `_seo_ai_description` | string | Custom meta description |
| `_seo_ai_focus_keyword` | string | Primary focus keyword |
| `_seo_ai_focus_keywords` | JSON array | Additional keywords |
| `_seo_ai_canonical` | string | Canonical URL override |
| `_seo_ai_robots` | JSON object | `{noindex, nofollow, noarchive, ...}` |
| `_seo_ai_og_title` | string | OG title override |
| `_seo_ai_og_description` | string | OG description override |
| `_seo_ai_og_image` | int | OG image attachment ID |
| `_seo_ai_twitter_title` | string | Twitter title override |
| `_seo_ai_twitter_description` | string | Twitter description override |
| `_seo_ai_schema_type` | string | Schema type (Article, FAQ, etc.) |
| `_seo_ai_schema_data` | JSON object | Additional schema properties |
| `_seo_ai_seo_score` | int | Cached SEO score (0-100) |
| `_seo_ai_readability_score` | int | Cached readability score (0-100) |
| `_seo_ai_auto_seo` | string | Per-post auto-SEO toggle (yes/no/default) |

## JSON-LD Schema Output Structure

```json
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "WebSite",
            "@id": "https://example.com/#website",
            "url": "https://example.com/",
            "name": "Site Name",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "https://example.com/?s={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        },
        {
            "@type": "Organization",
            "@id": "https://example.com/#organization",
            "name": "Organization Name",
            "url": "https://example.com/",
            "logo": { "@type": "ImageObject", "url": "..." },
            "sameAs": ["https://facebook.com/...", "https://twitter.com/..."]
        },
        {
            "@type": "BreadcrumbList",
            "itemListElement": [
                { "@type": "ListItem", "position": 1, "item": { "@id": "...", "name": "Home" } }
            ]
        },
        {
            "@type": "Article",
            "@id": "https://example.com/post/#article",
            "headline": "Post Title",
            "datePublished": "2024-01-01",
            "dateModified": "2024-01-02",
            "author": { "@type": "Person", "name": "Author" },
            "publisher": { "@id": "https://example.com/#organization" },
            "image": { "@type": "ImageObject", "url": "..." },
            "mainEntityOfPage": { "@id": "https://example.com/post/" }
        }
    ]
}
```

## SEO Analysis Score Structure

```php
[
    'seo' => [
        'score' => 78,  // 0-100
        'checks' => [
            [
                'id'      => 'keyword_in_title',
                'label'   => 'Focus keyword in title',
                'status'  => 'good',     // good, warning, error
                'message' => 'The focus keyword appears in the SEO title.',
                'weight'  => 15,
                'score'   => 15,
            ],
            // ... more checks
        ],
    ],
    'readability' => [
        'score' => 65,
        'checks' => [
            [
                'id'      => 'flesch_reading',
                'label'   => 'Flesch reading ease',
                'status'  => 'warning',
                'message' => 'The text scores 55 on the Flesch reading ease test, which is fairly difficult to read.',
                'weight'  => 20,
                'score'   => 10,
            ],
            // ... more checks
        ],
    ],
]
```
