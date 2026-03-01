# Changelog

All notable changes to the SEO AI plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.7.0] - 2026-03-01

### Added

- **WooCommerce SEO Integration** (#06): Enhanced Product schema with GTIN/ISBN/MPN/brand fields from custom meta box on product edit screen. Automatic WC product data extraction (price, stock, images, reviews) into schema. ProductGroup schema for variable products with offers per variation. ItemList schema for shop and category archive pages. Auto-noindex for hidden products. Removes WC built-in structured data to prevent duplicates. Module only activates when WooCommerce is present.
- **Analytics & Keyword Tracking** (#01): Custom `seo_ai_keyword_tracking` database table for daily keyword snapshots. Daily WP-Cron job captures focus keyword and SEO score for all analyzed posts. One snapshot per post per day (UNIQUE constraint). Query methods: `get_post_history()` for score trends, `get_top_posts()` ranked by latest score, `get_declining_posts()` comparing recent vs prior averages, `get_health_summary()` with total/analyzed/avg score/score distribution. Configurable data retention (default 90 days). Auto-cleanup via cron.
- **Local SEO** (#05): LocalBusiness schema support with 19 sub-types (Restaurant, MedicalBusiness, LegalService, etc.). Hooks into `seo_ai/schema/organization` filter to upgrade Organization to LocalBusiness variant. Structured PostalAddress, GeoCoordinates, OpeningHoursSpecification per weekday. Price range, payment accepted, currencies accepted fields. All settings-driven via Options helper (`local_business_type`, `local_latitude`, `local_longitude`, `local_hours_{day}_open/close`, etc.).

### Changed

- Module Manager now registers 3 new modules: `woocommerce` (default off), `local_seo` (default off), `analytics` (default off).
- Activator creates `seo_ai_keyword_tracking` table via `dbDelta()`.
- Uninstall drops `seo_ai_keyword_tracking` table and deletes `seo_ai_indexnow_key` option.

## [0.6.0] - 2026-03-01

### Added

- **Advanced Schema Types** (#02): Recipe schema builder with full support for ingredients, instructions (HowToStep), prep/cook/total time, yield, category, cuisine, nutrition, and aggregate ratings. JobPosting schema builder with employer, location, remote work, salary range (MonetaryAmount), employment type, qualifications, and date posted/valid through. Both read structured JSON from `_seo_ai_schema_recipe` and `_seo_ai_schema_job` post meta. Added `BlogPosting` and `NewsArticle` to AI schema type detection.
- **Internal Link Suggestions** (#13): AI-powered internal link recommendations that analyze post content and suggest relevant internal links with anchor text and reasoning. Auto-generates suggestions on metabox save when focus keyword is set. Orphan post detection finds posts with zero inbound internal links via content URL scanning. REST endpoint `POST /ai/link-suggestions` for on-demand suggestions. Stored in `_seo_ai_link_suggestions` post meta.
- **Gutenberg Inline AI Writing** (#21): AI writing assistant sidebar panel in the block editor via `wp.plugins.registerPlugin`. Six predefined actions (Write More, Improve, Summarize, Fix Grammar, Simplify, Add Keywords) that operate on selected text. Custom free-form prompt support. REST endpoint `POST /ai/inline` with action-specific system prompts. Copy-to-clipboard for AI results. No build step required (uses `wp.element.createElement`).
- **Content AI Research Panel** (#22): AI content brief generation from focus keyword. Returns recommended word count, heading count, internal/external link targets, subtopics to cover, related keywords, search intent, difficulty level, and content angle. REST endpoint `POST /ai/content-brief`. Integrated into the Gutenberg sidebar panel with tag-style display for subtopics and related keywords.

### Changed

- Plugin class `on_init()` now registers Link_Suggestions and Schema_Builder instances.
- REST controllers array expanded with `Inline_Ai_Controller`.
- Admin enqueue adds `editor-ai.js` on post editor pages for Gutenberg AI sidebar.
- AI Optimizer `detect_schema_type()` now includes BlogPosting and NewsArticle in allowed types.
- Schema Manager `get_post_schema()` routes Recipe/JobPosting types through Schema_Builder filter.
- Post Meta `JSON_FIELDS` expanded with `schema_recipe`, `schema_job`, `link_suggestions`.
- Post Meta `KNOWN_KEYS` expanded with `schema_recipe`, `schema_job`, `link_suggestions`.

## [0.5.0] - 2026-03-01

### Added

- **Search Intent Detection** (#20): AI-powered classification of focus keywords into informational, transactional, navigational, or commercial intent. Auto-detects on metabox save when focus keyword is set. Returns intent-specific optimization suggestions (5 per intent type). Stores result in `_seo_ai_search_intent` post meta.
- **Instant Indexing** (#14): IndexNow API integration for immediate URL submission to search engines on publish. Auto-generates 32-character hex key stored as `seo_ai_indexnow_key` option. Optional Bing URL Submission API support with configurable API key. Settings in Advanced tab with auto-submit toggle.
- **Advanced Redirects** (#09): Redirect scheduling with `active_from`/`active_until` datetime fields. Redirect categories for organization. Query parameter handling modes (ignore, exact, strip). Handler checks schedule window before executing redirects. Four new DB columns added via `dbDelta()`.
- **Video Sitemap** (#03): Auto-detects YouTube, Vimeo, HTML5 `<video>`, and WordPress `[video]` shortcode embeds in post content. Generates `video-sitemap.xml` with `xmlns:video` namespace. Auto-extracts YouTube thumbnails. Integrates with sitemap index. Toggleable module (default: off).
- **News Sitemap** (#04): Google News compliant sitemap with 48-hour publication window. Configurable publication name and post types. Per-post exclusion via `_seo_ai_news_exclude` meta. Generates `news-sitemap.xml` with `xmlns:news` namespace. Toggleable module (default: off).

### Changed

- Module Manager now registers 3 new modules: `indexing`, `video_sitemap`, `news_sitemap` (all default off).
- Redirects table schema expanded with `category`, `query_handling`, `active_from`, `active_until` columns.
- Redirect Manager `create()` accepts new fields; added `is_scheduled_active()` and `sanitize_datetime()` methods.
- Redirect Handler checks scheduled activation window before executing redirects.
- Post Meta `KNOWN_KEYS` expanded with `search_intent` and `news_exclude`.
- Sitemap settings tab includes Video Sitemap and News Sitemap configuration cards.
- Advanced settings tab includes Instant Indexing configuration card.

## [0.4.0] - 2026-03-01

### Added

- **Quick Edit SEO Fields** (#08): Inline SEO editing in the WordPress quick edit panel — edit SEO title, focus keyword, schema type, noindex, and cornerstone directly from the post list. Hidden data attributes populate fields from stored post meta via JS override of `inlineEditPost.edit`.
- **Post List Filters** (#08): Three dropdown filters above post list tables — filter by SEO Score (Good/Needs Work/Poor/Not Analyzed), Robots status (Index/Noindex), and Schema type. Uses `pre_get_posts` with `meta_query`.
- **CSV Export** (#07): Export all SEO metadata for any post type as CSV from Settings → Advanced. Includes post ID, title, URL, and all SEO fields (title, description, keyword, canonical, robots, schema, OG, Twitter, cornerstone, score).
- **CSV Import** (#07): Import SEO metadata from CSV file. Auto-maps columns by header name. Requires `post_id` column. Reports updated/skipped/errors count. Validates post existence and user capabilities per row.

### Changed

- Plugin class `init_admin()` now registers Post_Filters, Quick_Edit, and Csv_Import_Export instances.

## [0.3.0] - 2026-03-01

### Added

- **Cornerstone Content** (#23): Per-post "This is cornerstone content" checkbox in the Advanced tab. Cornerstone posts get stricter analysis thresholds: 900-word minimum (vs 300), 3 internal links (vs 1), 2 external links (vs 1). Star indicator shown in post list SEO column.
- **404 Monitor CSV Export** (#19): "Export CSV" button on the 404 Log page with optional date range filter (From/To). Exports URL, hits, first hit, last hit, referrer, and user agent.
- **Bulk Actions Enhancement** (#18): 6 new bulk actions in post list — Set Nofollow, Remove Nofollow, Remove Custom Canonical, Set Schema: Article, Clear All SEO Data, Re-analyze SEO. Generalized robots directive handler.
- **Advanced Image SEO** (#11): Alt text case conversion (Title Case, Sentence case, lowercase, UPPERCASE). Auto-fill caption and description on image upload with configurable templates. New settings: Alt Text Case, Auto Caption, Caption Template, Auto Description, Description Template.
- **Robots.txt Editor** (#15): Robots.txt custom rules textarea in Advanced settings tab with real-time syntax validation. Link to view current robots.txt. Valid directives highlighted green, invalid lines flagged in red.

### Changed

- Analyzer `check_content_length()`, `check_internal_links()`, `check_external_links()` now accept `$is_cornerstone` parameter for stricter thresholds.
- Bulk Actions class: `bulk_set_noindex()` replaced with generalized `bulk_set_robots()` supporting any directive.
- Post list SEO column width increased from 60px to 70px to accommodate cornerstone star.
- Image SEO `generate_alt_from_filename()` now respects `image_alt_case` setting instead of always using title case.

## [0.2.0] - 2026-03-01

### Changed

- **Model selection**: Replaced `<select>` dropdowns with `<input type="text">` fields for all 5 AI providers (OpenAI, Claude, Gemini, Ollama, OpenRouter), allowing any model ID to be entered without waiting for plugin updates.
- **Ollama model fetch**: "Fetch Models" button now populates a `<datalist>` for autocomplete on the text input instead of replacing a `<select>`.

### Added

- **Custom prompt per provider**: New textarea field for each provider to add custom instructions prepended to every SEO optimization request, with "Reset to Default" button.
- **Cost per 1M tokens**: New input/output cost fields (USD) per provider for cost tracking, with `$` prefix and `step="0.001"` precision.
- **Backend sanitization**: `custom_prompt` (sanitize_textarea_field), `cost_input` and `cost_output` (float, min 0) added to `sanitize_provider_settings()`.
- **Custom prompt integration**: AI Optimizer `chat()` method now prepends the active provider's custom prompt to all system prompts.
- **CSS**: `.seo-ai-cost-row` and `.seo-ai-cost-input` styles for the cost fields layout.

## [0.1.1] - 2026-03-01

### Fixed

- **Provider selection bug**: All 5 AI providers (`get_setting()`) were reading from `$option['providers'][id]` but the Settings Controller saves at `$option[id]`. This caused `is_configured()` to always return false for cloud providers, making the system fall back to Ollama regardless of user configuration.
- **Provider Manager `test_provider()`**: Fixed same data path mismatch when temporarily injecting override settings for connection testing.

## [0.1.0] - 2026-03-01

### Added

- **Optimization Wizard**: 4-step modal wizard (Select Posts → Configure Fields → Review → Progress) launched from the dashboard hero card
- **Activity Log system**: New `seo_ai_activity_log` database table for tracking all plugin operations
- **Activity Log class** (`includes/class-activity-log.php`): Static methods for logging, querying, and cleaning up entries
- **Queue Controller** (`includes/rest/class-queue-controller.php`): REST endpoints for wizard optimization queue — `GET /queue/posts`, `POST /queue/start`, `POST /queue/process-next`, `POST /queue/cancel`
- **Log Controller** (`includes/rest/class-log-controller.php`): REST endpoints for activity log — `GET /logs`, `DELETE /logs`
- **Dashboard hero card**: Gradient banner with unoptimized post count and "Start Optimization" button
- **Dashboard progress card**: Shows active optimization queue status with resume capability
- **Dashboard recent activity**: Last 10 log entries with level badges, timestamps, and expandable context
- **Activity Log admin page**: Full log viewer under SEO AI → Activity Log with level/operation/search filters, pagination, and "Clear Old Logs" action
- **Dashboard CSS** (`assets/css/dashboard.css`): Styles for hero card, modal, step indicator, post list, progress bar, terminal log, activity list
- **Dashboard JS** (`assets/js/dashboard.js`): Wizard state machine, AJAX polling (500ms), real-time progress log, post search/filter/pagination
- **Auto-SEO logging**: Activity log entries created on auto-SEO success and failure
- **Bulk actions logging**: Activity log entries created when bulk optimization is queued
- **Plugin activation logging**: "Plugin activated" entry written on activation

### Changed

- Dashboard view rewritten with hero card, progress section, and activity log
- Admin class updated to register Activity Log submenu and enqueue dashboard-specific assets
- `seoAi` JS global now includes `postTypeLabels` map

## [0.0.3] - 2026-02-28

### Added

- Playwright E2E test infrastructure with TypeScript
- Auth setup using `storageState` pattern for session reuse
- Admin pages test: all 4 pages load, 8 settings tabs navigate, CSS/JS assets verified, `seoAi` global checked
- Metabox test: rendering, 5-tab switching, metabox assets, `seoAiPost` global, field editing
- Redirects CRUD test: page loads, form fields present, redirect creation verified
- Plugin helpers: `navigateToSeoAiPage()`, `createTestPost()`, asset and global assertion utilities
- `.env.test.example` template for test credentials
- npm scripts: `test`, `test:ui`, `test:headed`, `test:debug`

## [1.0.0] - 2026-02-28

### Added

#### Core
- Plugin bootstrap with PSR-4-style autoloader (`SeoAi` namespace)
- Singleton Plugin class with dependency injection for helpers, providers, and modules
- Activation handler: creates database tables, sets default options, grants capabilities
- Deactivation handler: cleans transients, flushes rewrite rules
- Uninstall handler: removes all plugin data (options, post meta, tables, capabilities, transients)

#### Module System
- Module Manager with enable/disable support and default state per module
- 9 modules: Meta Tags, Schema Markup, XML Sitemap, Redirects & 404 Monitor, Open Graph, Twitter Cards, Image SEO, Breadcrumbs, Robots.txt
- Each module independently toggleable through Settings

#### AI Providers
- Provider interface and manager with hot-swappable provider support
- OpenAI provider (GPT-4o-mini default)
- Anthropic Claude provider (Claude Sonnet 4.5 default)
- Google Gemini provider (Gemini 2.0 Flash default)
- Ollama provider for local AI (Llama 3.2 default, no API key required)
- OpenRouter provider for model aggregation
- Connection testing and model listing per provider
- Configurable base URLs, models, and temperature per provider

#### Content Analysis
- 14-check SEO analysis: keyword density, keyword placement (title, description, URL, intro, subheadings), keyword distribution, title length, description length, content length, internal links, external links, image alt attributes
- Readability analysis: Flesch reading ease, sentence length, paragraph length, passive voice, transition words, consecutive sentences, subheading distribution
- Weighted scoring system (0-100 scale) with good/warning/error thresholds
- Real-time analysis in post editor with debounced updates
- Gutenberg and Classic Editor support

#### AI Optimization
- AI-powered generation of SEO titles, meta descriptions, and focus keywords
- "Fix with AI" per individual failing check
- "Optimize All with AI" one-click full optimization
- Bulk AI optimization across multiple posts via REST API
- Customizable AI prompt templates in Advanced settings

#### Meta Tags
- Custom SEO title and meta description per post/page
- Variable replacement: `%title%`, `%sitename%`, `%sep%`, `%page%`, `%excerpt%`, `%date%`
- Title separator customization
- Per-post canonical URL
- Per-post robots meta directives (noindex, nofollow, noarchive, nosnippet, noimageindex)
- Default templates per post type and taxonomy

#### Schema Markup
- JSON-LD structured data output in `<head>`
- Automatic schema types: Article, FAQ, HowTo, Product
- Site-level: WebSite, Organization/Person, BreadcrumbList
- Knowledge Graph settings: organization name, logo, social profiles
- Per-post schema type override

#### XML Sitemap
- Auto-generated XML sitemaps with configurable post types and taxonomies
- Image sitemap support
- Maximum entries per sitemap configurable
- Search engine ping on publish
- Automatic robots.txt sitemap directive
- 12-hour transient caching

#### Redirects & 404 Monitor
- Create 301, 302, 307, 410, 451 redirects
- Regex pattern support
- Hit counter per redirect
- Automatic redirect creation on slug change
- 404 error logging with deduplication, hit counting, referrer, user agent
- IP anonymization in 404 logs
- One-click redirect creation from 404 log entries
- Paginated 404 log with clear/delete actions
- WP_List_Table display for redirect management
- CSV import/export via REST API

#### Social Media
- Open Graph meta tags (og:title, og:description, og:image, og:type, og:url, og:site_name)
- Twitter Card meta tags (summary, summary_large_image)
- Per-post social title, description, and image override
- Facebook App ID and Twitter site handle configuration
- Social preview in post editor metabox

#### Image SEO
- Automatic alt text generation from filename (dash/underscore to spaces)
- Customizable alt text template
- Auto image title attribute

#### Breadcrumbs
- Shortcode `[seo_ai_breadcrumb]`
- PHP function `seo_ai_breadcrumb()`
- BreadcrumbList JSON-LD schema
- Customizable separator and home text

#### Robots.txt
- Custom robots.txt content via `robots_txt` WordPress filter
- Automatic sitemap URL injection

#### Admin UI
- Top-level "SEO AI" admin menu with dashboard, settings, redirects, 404 log submenus
- Dashboard page: score overview (6 stats), redirect/404/provider cards, active modules, quick actions
- Settings page: 8 tabs (General, Content, Providers, Schema, Social, Sitemap, Redirects, Advanced)
- Post editor metabox: score bar, 5 tabs (SEO, Readability, Social, Schema, Advanced), Google SERP preview, AI generate buttons, character counters
- WP Dashboard widget with average score, posts analyzed, low-score count
- SEO score column in post list tables (sortable)
- Bulk actions: Optimize SEO with AI, Set Noindex, Remove Noindex
- Plugin action link to Settings
- Toast notification system
- Toggle switches for module/feature management
- Provider cards with selection, configuration, and connection testing

#### REST API
- 13 endpoints under `seo-ai/v1` namespace
- Content analysis endpoint (`POST /analyze`)
- AI operations: optimize, generate-meta, generate-schema, bulk-optimize
- Settings: read, update, update providers, reset to defaults
- Redirects: CRUD, import
- 404 log: read, clear
- Provider: test connection, list models
- Permission checks on all endpoints

#### Security
- Nonce verification on all form submissions
- Capability-based access control (3 custom capabilities)
- Input sanitization per field type
- Output escaping throughout views
- Prepared statements for all database queries
- REST API permission callbacks

#### Frontend
- SEO meta tag output at `wp_head` priority 1
- Open Graph tags at priority 5
- Schema JSON-LD output
- HTML comment markers (`<!-- SEO AI v1.0.0 -->`)
- Removal of default WordPress head actions (generator, RSD, WLW, shortlinks)
- Auto-SEO on publish (optional)
- Redirect handler at priority 1

#### Helpers
- Options helper with caching and dirty tracking
- Post Meta helper with `_seo_ai_` prefix and JSON encode/decode
- Capability helper with grant/revoke per role
- Utils helper for text processing

#### Database
- `seo_ai_redirects` table for URL redirect management
- `seo_ai_404_log` table for 404 error tracking
- Tables created with `dbDelta()` for safe schema updates
