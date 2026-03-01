# Changelog

All notable changes to the SEO AI plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
