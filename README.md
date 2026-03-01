# SEO AI

Comprehensive SEO plugin for WordPress with AI-powered optimization. Supports multiple AI providers including Ollama for local/free usage.

## Requirements

- WordPress 6.4+
- PHP 8.0+

## Features

### Content Analysis
- Real-time SEO scoring (0-100) with 14 checks: keyword density, placement, distribution, title/description length, content length, internal/external links, image alt attributes
- Readability analysis: Flesch reading ease, sentence/paragraph length, passive voice, transition words, subheading distribution
- Google SERP preview in post editor
- Focus keyword tracking

### AI-Powered Optimization
- Generate SEO titles, meta descriptions, and focus keywords with AI
- One-click "Optimize All" for complete SEO field generation
- "Fix with AI" buttons on individual failing checks
- Bulk AI optimization across multiple posts
- Custom AI prompt templates (configurable in settings)

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
- Supported types: Article, FAQ, HowTo, Product, WebSite, Organization/Person, BreadcrumbList
- Knowledge Graph settings (organization name, logo, social profiles)
- Per-post schema type override

### XML Sitemap
- Auto-generated XML sitemaps
- Configurable post types and taxonomies
- Image sitemap support
- Search engine ping on publish
- Max entries per sitemap control

### Redirects & 404 Monitor
- Create 301, 302, 307, 410, 451 redirects
- Regex pattern support for redirects
- Hit counter tracking
- Automatic redirect on slug change
- 404 error logging with hit count, referrer, and user agent
- One-click "create redirect" from 404 log entries
- Import/export redirects

### Social Media
- **Open Graph**: og:title, og:description, og:image, og:type, og:url
- **Twitter Cards**: summary, summary_large_image card types
- Per-post social title/description/image override
- Social preview in metabox

### Image SEO
- Automatic alt text generation from filename
- Customizable alt text templates
- Auto image title attributes

### Breadcrumbs
- Shortcode: `[seo_ai_breadcrumb]`
- PHP function: `seo_ai_breadcrumb()`
- BreadcrumbList schema markup included
- Customizable separator and home text

### Robots.txt
- Custom robots.txt content via WordPress filter
- Automatic sitemap directive injection

### Admin Features
- Dashboard with SEO score overview, redirect stats, 404 count, active modules
- WP Dashboard widget with quick stats
- SEO score column in post list tables (sortable)
- Bulk actions: Optimize SEO with AI, Set Noindex, Remove Noindex
- Plugin action link to Settings page

## Installation

1. Upload the `seo-ai` folder to `/wp-content/plugins/`
2. Activate through the WordPress Plugins admin page
3. Go to **SEO AI > Settings** to configure your AI provider and preferences

## File Structure

```
seo-ai/
├── seo-ai.php                    # Plugin entry point & autoloader
├── uninstall.php                 # Clean uninstallation handler
├── assets/
│   ├── css/
│   │   ├── admin.css             # Core admin styles
│   │   ├── settings.css          # Settings page styles
│   │   └── metabox.css           # Post editor metabox styles
│   └── js/
│       ├── admin.js              # Admin page interactions
│       ├── settings.js           # Settings tab navigation
│       └── metabox.js            # Metabox analysis & AI generation
├── includes/
│   ├── class-plugin.php          # Main singleton bootstrap
│   ├── class-activator.php       # Activation (tables, options, caps)
│   ├── class-deactivator.php     # Deactivation cleanup
│   ├── helpers/
│   │   ├── class-options.php     # Settings CRUD with caching
│   │   ├── class-post-meta.php   # Post meta with _seo_ai_ prefix
│   │   ├── class-capability.php  # Custom capabilities
│   │   └── class-utils.php       # Text utilities
│   ├── providers/
│   │   ├── class-provider-interface.php
│   │   ├── class-provider-manager.php
│   │   ├── class-openai-provider.php
│   │   ├── class-claude-provider.php
│   │   ├── class-gemini-provider.php
│   │   ├── class-ollama-provider.php
│   │   └── class-openrouter-provider.php
│   ├── modules/
│   │   ├── class-module-manager.php
│   │   ├── content-analysis/     # Analyzer, Keyword, Readability, Score, AI Optimizer
│   │   ├── meta-tags/            # Meta tag output
│   │   ├── schema/               # JSON-LD structured data
│   │   ├── sitemap/              # XML sitemap generation
│   │   ├── redirects/            # Redirect handler, 404 monitor, table
│   │   ├── social/               # Open Graph, Twitter Cards
│   │   ├── image-seo/            # Image alt text optimization
│   │   ├── breadcrumbs/          # Breadcrumb navigation
│   │   └── robots/               # Robots.txt customization
│   ├── rest/
│   │   ├── class-rest-controller.php       # Abstract base
│   │   ├── class-analysis-controller.php   # POST /analyze
│   │   ├── class-ai-controller.php         # POST /ai/*
│   │   ├── class-settings-controller.php   # GET|POST /settings
│   │   ├── class-redirect-controller.php   # CRUD /redirects, /404-log
│   │   └── class-provider-controller.php   # POST /provider/test
│   ├── admin/
│   │   ├── class-admin.php       # Menus, assets, metabox, dashboard widget
│   │   ├── class-columns.php     # SEO score column in post list
│   │   ├── class-bulk-actions.php
│   │   └── views/                # PHP templates for admin pages
│   └── frontend/
│       ├── class-frontend.php    # Frontend output orchestrator
│       ├── class-head.php        # Head tag utilities
│       └── class-auto-seo.php    # Auto-optimize on publish
└── docs/                         # Architecture documentation
```

## REST API

Base namespace: `seo-ai/v1`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/analyze` | POST | Run content analysis |
| `/ai/optimize` | POST | AI optimization suggestions |
| `/ai/generate-meta` | POST | Generate title/description/keyword |
| `/ai/generate-schema` | POST | Detect schema type |
| `/ai/bulk-optimize` | POST | Bulk optimize posts |
| `/settings` | GET/POST | Read/update settings |
| `/settings/providers` | POST | Update provider config |
| `/settings/reset` | POST | Restore defaults |
| `/redirects` | GET/POST/PUT/DELETE | Redirect CRUD |
| `/redirects/import` | POST | Import redirects (CSV) |
| `/404-log` | GET/DELETE | Read/clear 404 log |
| `/provider/test` | POST | Test provider connection |
| `/provider/models` | GET | List available models |

## Hooks

### Actions

| Hook | Description |
|------|-------------|
| `seo_ai/init` | After plugin is fully initialized |
| `seo_ai/modules_loaded` | After all modules registered |
| `seo_ai/metabox_saved` | After metabox data saved |
| `seo_ai/activate` | After plugin activation |
| `seo_ai/deactivate` | After plugin deactivation |

### Filters

| Filter | Description |
|--------|-------------|
| `seo_ai/post_types` | Modify supported post types |

## Custom Capabilities

| Capability | Description |
|------------|-------------|
| `seo_ai_manage_settings` | Access plugin settings |
| `seo_ai_manage_redirects` | Manage redirects and 404 log |
| `seo_ai_view_reports` | View SEO reports and dashboard |

Granted to the Administrator role on activation.

## Database Tables

- `{prefix}seo_ai_redirects` -- URL redirect rules with hit tracking
- `{prefix}seo_ai_404_log` -- 404 error log with deduplication

## Uninstallation

When deleted through WordPress admin, the plugin removes:
- All plugin options (`seo_ai_settings`, `seo_ai_providers`, `seo_ai_version`)
- All post meta with `_seo_ai_` prefix
- Custom database tables
- Custom capabilities from all roles
- Plugin transients

## License

GPL-2.0-or-later

## Author

[Thotenn](https://thotenn.com)
