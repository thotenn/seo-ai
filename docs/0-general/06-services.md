# Services & Components Reference

## Core Services

### 1. Plugin (Singleton)
**File:** `includes/class-plugin.php`
**Role:** Main entry point, service locator, lifecycle management

- Initializes all modules and services
- Registers activation/deactivation hooks
- Manages WordPress hook registration
- Provides access to all sub-services

### 2. Options Helper
**File:** `includes/helpers/class-options.php`
**Role:** Centralized settings access

- `get(key, default)` - Get a setting value
- `set(key, value)` - Update a setting
- `delete(key)` - Remove a setting
- `get_all()` - Get all settings
- Handles serialization/deserialization
- Caching layer for frequent access

### 3. Post Meta Helper
**File:** `includes/helpers/class-post-meta.php`
**Role:** Post-level SEO data access

- `get(post_id, key, default)` - Get post meta
- `set(post_id, key, value)` - Set post meta
- `get_all(post_id)` - Get all SEO meta for a post
- `delete(post_id, key)` - Delete post meta
- Handles JSON serialization for complex fields

## AI Services

### 4. Provider Manager
**File:** `includes/providers/class-provider-manager.php`
**Role:** AI provider registry and orchestration

- Registers all available providers
- Returns active provider instance
- Handles provider configuration
- Connection testing
- Fallback chain management

### 5. AI Optimizer
**File:** `includes/modules/content-analysis/class-ai-optimizer.php`
**Role:** AI-powered SEO optimization

- `generate_meta_title(content, keyword)` - Generate SEO title
- `generate_meta_description(content, keyword)` - Generate meta description
- `suggest_keywords(content)` - Extract keyword suggestions
- `optimize_content(content, keyword)` - Get optimization tips
- `detect_schema_type(content)` - Detect appropriate schema
- `generate_alt_text(filename, context)` - Generate image alt text
- `bulk_optimize(post_ids, fields)` - Batch optimization

### 6. Auto SEO
**File:** `includes/frontend/class-auto-seo.php`
**Role:** Automatic SEO optimization on post save

- Hooks into `save_post` action
- Checks if auto-SEO is enabled (global + per-post)
- Calls AI Optimizer for each enabled field
- Saves generated meta data
- Skips if data already exists and scores well

## Analysis Services

### 7. Content Analyzer
**File:** `includes/modules/content-analysis/class-analyzer.php`
**Role:** SEO content analysis engine

SEO Checks:
- `check_keyword_in_title()`
- `check_keyword_in_description()`
- `check_keyword_in_url()`
- `check_keyword_in_first_paragraph()`
- `check_keyword_in_headings()`
- `check_keyword_density()`
- `check_title_length()`
- `check_description_length()`
- `check_content_length()`
- `check_internal_links()`
- `check_external_links()`
- `check_image_alt_text()`

### 8. Readability Analyzer
**File:** `includes/modules/content-analysis/class-readability.php`
**Role:** Content readability scoring

Readability Checks:
- `check_flesch_reading_ease()`
- `check_sentence_length()`
- `check_paragraph_length()`
- `check_passive_voice()`
- `check_transition_words()`
- `check_consecutive_sentences()`
- `check_subheading_distribution()`

### 9. Score Calculator
**File:** `includes/modules/content-analysis/class-score.php`
**Role:** Weighted score calculation

- Receives check results with weights
- Calculates weighted average (0-100)
- Determines color: green (>70), orange (40-70), red (<40)
- Caches score in post meta

## Frontend Services

### 10. Head Output
**File:** `includes/frontend/class-head.php`
**Role:** Outputs SEO tags in document <head>

Output order:
1. Meta title (wp_title filter)
2. Meta description
3. Robots meta
4. Canonical URL
5. Open Graph tags
6. Twitter Card tags
7. JSON-LD Schema
8. Misc (next/prev for pagination)

### 11. Schema Manager
**File:** `includes/modules/schema/class-schema-manager.php`
**Role:** JSON-LD structured data generation

- Builds @graph array with all relevant schemas
- Always includes: WebSite, Organization/Person
- Per-post: Article/WebPage/FAQ/HowTo/etc.
- Breadcrumb schema when enabled
- Handles @id references between schemas
- Filterable via `seo_ai/schema/graph` hook

### 12. Sitemap Manager
**File:** `includes/modules/sitemap/class-sitemap-manager.php`
**Role:** XML sitemap generation

- Registers rewrite rules for sitemap URLs
- Generates sitemap index
- Generates per-type sub-sitemaps
- Handles pagination (1000 entries per page)
- Caches with transients
- XSL stylesheet for readable display
- Respects noindex settings

### 13. Open Graph
**File:** `includes/modules/social/class-open-graph.php`
**Role:** Open Graph meta tag output

- Generates og: tags based on post data
- Falls back to global defaults
- Handles images (featured image, default image)
- Per-post overrides via meta

### 14. Twitter Cards
**File:** `includes/modules/social/class-twitter-cards.php`
**Role:** Twitter Card meta tag output

- Summary or Summary with Large Image
- Falls back to OG data if no Twitter-specific data

## Admin Services

### 15. Settings Page
**File:** `includes/admin/class-settings-page.php`
**Role:** Plugin settings admin page

- Tabbed interface (General, Providers, Content, Schema, Social, Sitemap, Redirects, Advanced)
- AJAX save via REST API
- Provider configuration panel (Flavor Translator pattern)
- Connection testing UI

### 16. Metabox
**File:** `includes/admin/class-metabox.php`
**Role:** Post editor SEO metabox

- Adds metabox to configured post types
- Tabbed interface (SEO, Readability, Social, Schema, AI)
- Real-time analysis via JavaScript
- AI suggestion buttons
- Score display with color coding

### 17. Redirect Manager (Admin)
**File:** `includes/modules/redirects/class-redirect-manager.php`
**Role:** Redirect CRUD operations

- `create(source, target, type, is_regex)` - Create redirect
- `update(id, data)` - Update redirect
- `delete(id)` - Delete redirect
- `get(id)` - Get single redirect
- `get_all(args)` - List with pagination/search
- `import_csv(file)` - Bulk import
- `export_csv()` - Bulk export

### 18. Redirect Handler (Frontend)
**File:** `includes/modules/redirects/class-redirect-handler.php`
**Role:** Execute redirects on frontend

- Hooks into `template_redirect`
- Matches current URL against redirect rules
- Supports plain URL and regex matching
- Executes redirect with proper HTTP status
- Increments hit counter

### 19. 404 Monitor
**File:** `includes/modules/redirects/class-404-monitor.php`
**Role:** Log 404 errors

- Hooks into `template_redirect` when is_404()
- Logs URL, referrer, user agent, IP
- Groups by URL (increment hits)
- Admin list table for viewing
- Auto-cleanup old entries (configurable limit)

## REST API Controllers

### 20. Analysis Controller
**Endpoint:** `/seo-ai/v1/analyze`
- POST: Analyze content and return scores

### 21. AI Controller
**Endpoint:** `/seo-ai/v1/ai/*`
- POST `/optimize`: Get AI optimization suggestions
- POST `/generate-meta`: Generate meta title/description
- POST `/generate-schema`: Generate schema data
- POST `/generate-alt`: Generate image alt text

### 22. Settings Controller
**Endpoint:** `/seo-ai/v1/settings`
- GET: Retrieve all settings
- POST: Update settings

### 23. Provider Controller
**Endpoint:** `/seo-ai/v1/provider/*`
- POST `/test`: Test provider connection
- GET `/models`: Get available models (for Ollama)

### 24. Redirect Controller
**Endpoint:** `/seo-ai/v1/redirects`
- GET: List redirects
- POST: Create redirect
- PUT: Update redirect
- DELETE: Delete redirect
- GET `/404-log`: Get 404 log entries

## WordPress Hooks (Custom)

### Actions
- `seo_ai/init` - Plugin fully initialized
- `seo_ai/activate` - Plugin activated
- `seo_ai/deactivate` - Plugin deactivated
- `seo_ai/post_analyzed` - After content analysis
- `seo_ai/auto_seo_applied` - After auto-SEO runs
- `seo_ai/redirect_created` - After redirect created
- `seo_ai/redirect_executed` - After redirect executes

### Filters
- `seo_ai/meta_title` - Filter meta title output
- `seo_ai/meta_description` - Filter meta description output
- `seo_ai/schema/graph` - Filter schema graph array
- `seo_ai/sitemap/entries` - Filter sitemap entries
- `seo_ai/og/tags` - Filter Open Graph tags
- `seo_ai/analysis/checks` - Filter analysis checks
- `seo_ai/analysis/score` - Filter final score
- `seo_ai/ai/prompt` - Filter AI prompts before sending
- `seo_ai/auto_seo/enabled` - Filter auto-SEO availability
- `seo_ai/post_types` - Filter supported post types
- `seo_ai/redirect/match` - Filter redirect matching
