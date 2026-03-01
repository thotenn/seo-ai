# SEO AI

Comprehensive SEO plugin for WordPress with AI-powered optimization. Supports multiple AI providers including Ollama for local/free usage. Modular architecture with 16 toggleable feature modules.

## Requirements

- WordPress 6.4+
- PHP 8.0+

## Features

### Content Analysis
- Real-time SEO scoring (0-100) with 14 checks: keyword density, placement, distribution, title/description length, content length, internal/external links, image alt attributes
- Readability analysis: Flesch reading ease, sentence/paragraph length, passive voice, transition words, subheading distribution
- Google SERP preview in post editor
- Focus keyword tracking
- Cornerstone content support with stricter analysis thresholds (900-word min, 3 internal links, 2 external links)

### AI-Powered Optimization
- Generate SEO titles, meta descriptions, and focus keywords with AI
- One-click "Optimize All" for complete SEO field generation
- "Fix with AI" buttons on individual failing checks
- Bulk AI optimization across multiple posts
- Custom AI prompt templates (configurable in settings)
- **Gutenberg Inline AI Writing**: sidebar panel with 6 actions (Write More, Improve, Summarize, Fix Grammar, Simplify, Add Keywords) + custom prompts
- **Content AI Research Panel**: AI-generated content briefs with recommended word count, heading count, subtopics, related keywords, search intent, and content angle
- **Search Intent Detection**: AI-powered classification of keywords into informational, transactional, navigational, or commercial intent with intent-specific optimization suggestions
- **Internal Link Suggestions**: AI-powered internal link recommendations with anchor text and reasoning, plus orphan post detection

### AI Providers
| Provider | Type | Default Model |
|----------|------|---------------|
| **OpenAI** | Cloud | gpt-4o-mini |
| **Anthropic (Claude)** | Cloud | claude-sonnet-4-5-20250929 |
| **Google Gemini** | Cloud | gemini-2.0-flash |
| **Ollama** | Local | llama3.2 |
| **OpenRouter** | Cloud | anthropic/claude-sonnet-4-5-20250929 |

All providers are configured through the Settings > Providers tab. Ollama runs locally and requires no API key.

### Meta Tags
- Custom SEO title and meta description per post/page
- Title separator customization
- Default templates with variables: `%title%`, `%sitename%`, `%sep%`, `%page%`, `%excerpt%`, `%date%`
- Per-post robots meta control (noindex, nofollow, noarchive, nosnippet, noimageindex)
- Canonical URL management

### Schema Markup (JSON-LD)
- Automatic structured data generation
- Supported types: Article, BlogPosting, NewsArticle, FAQ, HowTo, Product, WebSite, Organization/Person, BreadcrumbList
- **Recipe schema**: ingredients, instructions (HowToStep), prep/cook/total time, yield, category, cuisine, nutrition, ratings
- **JobPosting schema**: employer, location, remote work, salary range, employment type, qualifications
- **PodcastEpisode schema**: AudioObject, PodcastSeries on singular posts with audio
- Knowledge Graph settings (organization name, logo, social profiles)
- Per-post schema type override

### XML Sitemap
- Auto-generated XML sitemaps
- Configurable post types and taxonomies
- Image sitemap support
- Search engine ping on publish
- Max entries per sitemap control
- **Video Sitemap**: auto-detects YouTube, Vimeo, HTML5 `<video>`, and `[video]` shortcodes; generates `video-sitemap.xml`
- **News Sitemap**: Google News compliant with 48-hour publication window, configurable publication name and post types

### Redirects & 404 Monitor
- Create 301, 302, 307, 410, 451 redirects
- Regex pattern support for redirects
- Hit counter tracking
- Automatic redirect on slug change
- **Redirect scheduling** with `active_from`/`active_until` datetime fields
- **Redirect categories** for organization
- **Query parameter handling** modes (ignore, exact, strip)
- 404 error logging with hit count, referrer, and user agent
- One-click "create redirect" from 404 log entries
- **404 CSV export** with optional date range filter
- Import/export redirects

### Social Media
- **Open Graph**: og:title, og:description, og:image, og:type, og:url
- **Twitter Cards**: summary, summary_large_image card types
- Per-post social title/description/image override
- Social preview in metabox

### Image SEO
- Automatic alt text generation from filename
- Customizable alt text templates
- **Alt text case conversion** (Title Case, Sentence case, lowercase, UPPERCASE)
- **Auto-fill caption and description** on image upload with configurable templates
- Auto image title attributes

### Breadcrumbs
- Shortcode: `[seo_ai_breadcrumb]`
- PHP function: `seo_ai_breadcrumb()`
- BreadcrumbList schema markup included
- Customizable separator and home text

### Robots.txt
- **Custom robots.txt rules** textarea in Advanced settings tab
- Real-time syntax validation (valid directives green, invalid lines red)
- Link to view current robots.txt
- Automatic sitemap directive injection

### Instant Indexing
- **IndexNow API** integration for immediate URL submission on publish
- Auto-generated 32-character hex key
- Optional **Bing URL Submission API** with configurable API key
- Auto-submit toggle in Advanced settings

### Competitor Analysis
- Fetch and parse external competitor URLs to extract SEO metrics (title, meta, headings, word count, links, images)
- Side-by-side comparison of own post vs competitor with AI-generated suggestions
- 24-hour transient caching and 5-minute rate limiting per URL

### WooCommerce SEO (requires WooCommerce)
- Enhanced Product schema with GTIN/ISBN/MPN/brand fields
- ProductGroup schema for variable products with offers per variation
- ItemList schema for shop and category archive pages
- Auto-noindex for hidden products
- Removes WC built-in structured data to prevent duplicates

### Local SEO
- LocalBusiness schema with 19 sub-types (Restaurant, MedicalBusiness, LegalService, etc.)
- Structured PostalAddress, GeoCoordinates, OpeningHoursSpecification per weekday
- Price range, payment accepted, currencies accepted fields

### Analytics & Keyword Tracking
- Daily WP-Cron job captures focus keyword and SEO score for all analyzed posts
- Score trend history per post
- Top posts ranked by score, declining posts detection
- Health summary (total/analyzed/avg score/distribution)
- Configurable data retention (default 90 days)

### Podcast Support
- iTunes-compatible RSS feed at `/feed/podcast/`
- Channel metadata from settings (title, description, image, category, language)
- Episode data from post meta (audio URL, duration, episode number, season)
- PodcastEpisode JSON-LD schema

### Integrations
- **ACF**: Recursive field extraction (WYSIWYG, textarea, text, repeater, flexible content) + image extraction for sitemap
- **Elementor**: `_elementor_data` JSON parsing for content extraction
- **Divi**: Shortcode rendering and text extraction
- **bbPress**: QAPage schema for forum topics with question/answer structure

### Post List Enhancements
- **SEO score column** (sortable)
- **Quick edit SEO fields**: edit SEO title, keyword, schema type, noindex, cornerstone directly from the post list
- **Filters**: filter by SEO Score (Good/Needs Work/Poor/Not Analyzed), Robots status (Index/Noindex), Schema type
- **Bulk actions** (9): Optimize SEO with AI, Set/Remove Noindex, Set/Remove Nofollow, Remove Canonical, Set Schema: Article, Clear SEO Data, Re-analyze SEO
- **CSV Export**: export all SEO metadata for any post type
- **CSV Import**: import SEO metadata from CSV file with auto-column mapping

### Admin Features
- Dashboard with SEO score overview, redirect stats, 404 count, active modules (16 badges)
- Optimization wizard: 4-step modal (Select Posts → Configure → Review → Progress)
- Activity log with level/operation/search filters and pagination
- WP Dashboard widget with quick stats
- Plugin action link to Settings page
- Toast notification system
- 8 settings tabs (General, Content, Providers, Schema, Social, Sitemap, Redirects, Advanced)

## Installation

1. Upload the `seo-ai` folder to `/wp-content/plugins/`
2. Activate through the WordPress Plugins admin page
3. Go to **SEO AI > Settings** to configure your AI provider and preferences

## Module System

16 modules registered in Module Manager. Each can be toggled on/off via Settings > General:

| Module ID | Name | Default |
|-----------|------|---------|
| `meta_tags` | Meta Tags | On |
| `schema` | Schema Markup | On |
| `sitemap` | XML Sitemap | On |
| `redirects` | Redirects & 404 Monitor | On |
| `open_graph` | Open Graph | On |
| `twitter_cards` | Twitter Cards | On |
| `image_seo` | Image SEO | On |
| `breadcrumbs` | Breadcrumbs | Off |
| `robots_txt` | Robots.txt | On |
| `indexing` | Instant Indexing | Off |
| `video_sitemap` | Video Sitemap | Off |
| `news_sitemap` | News Sitemap | Off |
| `woocommerce` | WooCommerce SEO | Off |
| `local_seo` | Local SEO | Off |
| `analytics` | Analytics & Keyword Tracking | Off |
| `podcast` | Podcast | Off |

## REST API

Base namespace: `seo-ai/v1`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/analyze` | POST | Run content analysis |
| `/ai/optimize` | POST | AI optimization suggestions |
| `/ai/generate-meta` | POST | Generate title/description/keyword |
| `/ai/generate-schema` | POST | Detect schema type |
| `/ai/bulk-optimize` | POST | Bulk optimize posts |
| `/ai/inline` | POST | Inline AI writing (improve, summarize, etc.) |
| `/ai/content-brief` | POST | Generate AI content brief from keyword |
| `/ai/link-suggestions` | POST | AI internal link suggestions |
| `/competitor/analyze` | POST | Analyze competitor URL for SEO metrics |
| `/competitor/compare` | POST | Compare own post vs competitor |
| `/settings` | GET/POST | Read/update settings |
| `/settings/providers` | POST | Update provider config |
| `/settings/reset` | POST | Restore defaults |
| `/redirects` | GET/POST/PUT/DELETE | Redirect CRUD |
| `/redirects/import` | POST | Import redirects (CSV) |
| `/404-log` | GET/DELETE | Read/clear 404 log |
| `/provider/test` | POST | Test provider connection |
| `/provider/models` | GET | List available models |
| `/queue/posts` | GET | List posts for optimization queue |
| `/queue/start` | POST | Start optimization queue |
| `/queue/process-next` | POST | Process next post in queue |
| `/queue/cancel` | POST | Cancel optimization queue |
| `/logs` | GET/DELETE | Read/clear activity log |

## File Structure

```
seo-ai/
├── seo-ai.php                    # Plugin entry point & autoloader
├── uninstall.php                 # Clean uninstallation handler
├── assets/
│   ├── css/
│   │   ├── admin.css             # Core admin styles
│   │   ├── settings.css          # Settings page styles
│   │   ├── dashboard.css         # Dashboard page styles
│   │   └── metabox.css           # Post editor metabox styles
│   └── js/
│       ├── admin.js              # Admin page interactions
│       ├── settings.js           # Settings tab navigation
│       ├── dashboard.js          # Dashboard wizard & activity log
│       ├── metabox.js            # Metabox analysis & AI generation
│       └── editor-ai.js          # Gutenberg AI sidebar panel
├── includes/
│   ├── class-plugin.php          # Main singleton bootstrap
│   ├── class-activator.php       # Activation (tables, options, caps)
│   ├── class-deactivator.php     # Deactivation cleanup
│   ├── helpers/
│   │   ├── class-options.php     # Settings CRUD with caching
│   │   ├── class-post-meta.php   # Post meta with _seo_ai_ prefix
│   │   ├── class-capability.php  # Custom capabilities
│   │   ├── class-activity-log.php # Audit log functionality
│   │   └── class-utils.php       # Text utilities
│   ├── providers/                # 5 AI providers + manager + interface
│   ├── modules/
│   │   ├── class-module-manager.php    # 16 module definitions
│   │   ├── content-analysis/     # Analyzer, Keyword, Readability, Score, AI Optimizer, Search Intent, Link Suggestions, Competitor Analyzer
│   │   ├── meta-tags/            # Meta tag output
│   │   ├── schema/               # JSON-LD structured data + Schema Builder (Recipe, JobPosting)
│   │   ├── sitemap/              # XML sitemap + Video Sitemap + News Sitemap
│   │   ├── redirects/            # Redirect handler, 404 monitor, redirect table
│   │   ├── social/               # Open Graph, Twitter Cards
│   │   ├── image-seo/            # Image alt text optimization + case conversion
│   │   ├── breadcrumbs/          # Breadcrumb navigation
│   │   ├── robots/               # Robots.txt customization
│   │   ├── indexing/             # IndexNow + Bing Submission API
│   │   ├── woocommerce/          # WooCommerce product schema
│   │   ├── local-seo/            # LocalBusiness schema
│   │   ├── analytics/            # Keyword tracking with daily cron
│   │   └── podcast/              # Podcast RSS feed + schema
│   ├── integrations/
│   │   ├── class-content-extractor.php  # ACF/Elementor/Divi content
│   │   └── class-bbpress.php            # bbPress QAPage schema
│   ├── rest/                     # 10 REST controllers
│   ├── admin/
│   │   ├── class-admin.php       # Menus, assets, metabox, dashboard widget
│   │   ├── class-columns.php     # SEO score column in post list
│   │   ├── class-bulk-actions.php # 9 bulk actions
│   │   ├── class-quick-edit.php  # Quick edit SEO fields
│   │   ├── class-post-filters.php # Score/robots/schema filters
│   │   ├── class-csv-import-export.php # CSV import/export
│   │   └── views/                # PHP templates for admin pages
│   └── frontend/
│       ├── class-frontend.php    # Frontend output orchestrator
│       ├── class-head.php        # Head tag utilities
│       └── class-auto-seo.php    # Auto-optimize on publish
├── tests/                        # Playwright E2E tests (156 tests)
└── docs/                         # Architecture documentation
```

## Hooks

### Actions

| Hook | Description |
|------|-------------|
| `seo_ai/init` | After plugin is fully initialized |
| `seo_ai/modules_loaded` | After all modules registered |
| `seo_ai/metabox_saved` | After metabox data saved |
| `seo_ai/activate` | After plugin activation |
| `seo_ai/deactivate` | After plugin deactivation |
| `seo_ai/auto_seo_completed` | After auto-SEO job completed |
| `seo_ai/redirect/before_execute` | Before executing a redirect |
| `seo_ai_daily_keyword_track` | Daily keyword tracking cron |

### Filters

| Filter | Description |
|--------|-------------|
| `seo_ai/post_types` | Modify supported post types |
| `seo_ai/content_for_analysis` | Filter content for analysis (ACF/Elementor/Divi) |
| `seo_ai/meta_description` | Filter meta description output |
| `seo_ai/robots_directives` | Filter robots meta directives |
| `seo_ai/canonical_url` | Filter canonical URL |
| `seo_ai/generated_image_alt` | Filter auto-generated image alt text |
| `seo_ai/breadcrumb_html` | Filter breadcrumb HTML output |
| `seo_ai/robots_txt_lines` | Filter robots.txt lines |
| `seo_ai/sitemap/index_entries` | Filter sitemap index entries |
| `seo_ai/sitemap/entries` | Filter sitemap entries |
| `seo_ai/schema/graph` | Filter full schema graph |
| `seo_ai/schema/organization` | Filter organization schema (used by Local SEO) |
| `seo_ai/schema/article` | Filter article schema |
| `seo_ai/schema/recipe` | Filter recipe schema |
| `seo_ai/schema/job` | Filter job posting schema |
| `seo_ai/og/data` | Filter Open Graph data |
| `seo_ai/twitter/data` | Filter Twitter Card data |

## Custom Capabilities

| Capability | Description |
|------------|-------------|
| `seo_ai_manage_settings` | Access plugin settings |
| `seo_ai_manage_redirects` | Manage redirects and 404 log |
| `seo_ai_view_reports` | View SEO reports and dashboard |

Granted to the Administrator role on activation.

## Database Tables

- `{prefix}seo_ai_redirects` -- URL redirect rules with hit tracking, scheduling, categories
- `{prefix}seo_ai_404_log` -- 404 error log with deduplication
- `{prefix}seo_ai_activity_log` -- Plugin activity audit log
- `{prefix}seo_ai_keyword_tracking` -- Daily keyword/score snapshots per post

## Testing

156 Playwright E2E tests across 11 spec files. See CLAUDE.md for full test structure and setup instructions.

```bash
npm test              # run all tests
npm run test:ui       # interactive UI mode
npm run test:headed   # run with visible browser
npm run test:debug    # step-by-step debug mode
```

## Uninstallation

When deleted through WordPress admin, the plugin removes:
- All plugin options (`seo_ai_settings`, `seo_ai_providers`, `seo_ai_version`, `seo_ai_indexnow_key`)
- All post meta with `_seo_ai_` prefix
- All 4 custom database tables
- Custom capabilities from all roles
- Plugin transients and cron jobs

## License

GPL-2.0-or-later

## Author

[Thotenn](https://thotenn.com)
