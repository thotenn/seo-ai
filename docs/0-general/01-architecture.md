# SEO-AI Plugin Architecture

## Overview

SEO-AI is a comprehensive WordPress SEO plugin that combines the best features from Yoast SEO Premium and Rank Math Pro, with native AI assistance powered by multiple providers including Ollama for local/free usage.

## Plugin Identity

- **Plugin Name:** SEO AI
- **Slug:** `seo-ai`
- **Text Domain:** `seo-ai`
- **Namespace:** `SeoAi`
- **Option Prefix:** `seo_ai_`
- **Post Meta Prefix:** `_seo_ai_`
- **Hook Prefix:** `seo_ai/`
- **REST Namespace:** `seo-ai/v1`
- **Minimum PHP:** 8.0
- **Minimum WP:** 6.4

## Directory Structure

```
seo-ai/
├── seo-ai.php                    # Main plugin file (bootstrap)
├── uninstall.php                 # Cleanup on uninstall
├── composer.json                 # Autoloading & dependencies
├── docs/                         # Documentation
├── assets/
│   ├── css/
│   │   ├── admin.css             # Admin styles
│   │   ├── metabox.css           # Editor metabox styles
│   │   └── settings.css          # Settings page styles
│   ├── js/
│   │   ├── admin.js              # Admin scripts
│   │   ├── metabox.js            # Editor metabox (analysis, AI)
│   │   ├── settings.js           # Settings page (provider config)
│   │   └── gutenberg/            # Block editor integration
│   │       └── sidebar.js        # Gutenberg sidebar panel
│   └── images/                   # Icons, logos
├── includes/
│   ├── class-plugin.php          # Main Plugin singleton
│   ├── class-activator.php       # Activation logic
│   ├── class-deactivator.php     # Deactivation logic
│   ├── class-installer.php       # DB tables, defaults
│   ├── class-autoloader.php      # PSR-4 autoloader (fallback)
│   ├── helpers/
│   │   ├── class-options.php     # Options helper (get/set/delete)
│   │   ├── class-post-meta.php   # Post meta helper
│   │   ├── class-capability.php  # Role & capability manager
│   │   └── class-utils.php       # Generic utilities
│   ├── providers/
│   │   ├── class-provider-interface.php    # AI provider contract
│   │   ├── class-provider-manager.php      # Provider registry & factory
│   │   ├── class-openai-provider.php       # OpenAI (GPT)
│   │   ├── class-claude-provider.php       # Anthropic Claude
│   │   ├── class-gemini-provider.php       # Google Gemini
│   │   ├── class-ollama-provider.php       # Ollama (local)
│   │   └── class-openrouter-provider.php   # OpenRouter (multi-model)
│   ├── modules/
│   │   ├── class-module-interface.php      # Module contract
│   │   ├── class-module-manager.php        # Module registry
│   │   ├── content-analysis/
│   │   │   ├── class-analyzer.php          # SEO content analyzer
│   │   │   ├── class-score.php             # Score calculator
│   │   │   ├── class-readability.php       # Readability checks
│   │   │   ├── class-keyword-analyzer.php  # Keyword density/usage
│   │   │   └── class-ai-optimizer.php      # AI-powered optimization
│   │   ├── meta-tags/
│   │   │   ├── class-meta-tags.php         # Meta tag output
│   │   │   ├── class-title.php             # Title tag management
│   │   │   └── class-description.php       # Meta description
│   │   ├── schema/
│   │   │   ├── class-schema-manager.php    # JSON-LD orchestrator
│   │   │   ├── class-schema-article.php    # Article schema
│   │   │   ├── class-schema-organization.php
│   │   │   ├── class-schema-breadcrumb.php
│   │   │   ├── class-schema-faq.php
│   │   │   ├── class-schema-howto.php
│   │   │   ├── class-schema-product.php
│   │   │   ├── class-schema-local-business.php
│   │   │   └── class-schema-website.php
│   │   ├── sitemap/
│   │   │   ├── class-sitemap-manager.php   # XML sitemap generator
│   │   │   ├── class-sitemap-index.php     # Sitemap index
│   │   │   ├── class-sitemap-posts.php     # Post sitemap
│   │   │   ├── class-sitemap-taxonomies.php
│   │   │   └── class-sitemap-xsl.php       # XSL stylesheet
│   │   ├── social/
│   │   │   ├── class-open-graph.php        # Open Graph tags
│   │   │   └── class-twitter-cards.php     # Twitter Card tags
│   │   ├── redirects/
│   │   │   ├── class-redirect-manager.php  # Redirect CRUD
│   │   │   ├── class-redirect-handler.php  # Frontend redirect execution
│   │   │   ├── class-redirect-table.php    # WP_List_Table
│   │   │   └── class-404-monitor.php       # 404 logging
│   │   ├── image-seo/
│   │   │   └── class-image-seo.php         # Auto alt text, titles
│   │   ├── breadcrumbs/
│   │   │   └── class-breadcrumbs.php       # Breadcrumb trail
│   │   └── robots/
│   │       └── class-robots-txt.php        # robots.txt management
│   ├── admin/
│   │   ├── class-admin.php                 # Admin controller
│   │   ├── class-settings-page.php         # Settings page (tabbed)
│   │   ├── class-metabox.php               # Post editor metabox
│   │   ├── class-dashboard-widget.php      # Dashboard SEO overview
│   │   ├── class-bulk-actions.php          # Bulk SEO actions
│   │   ├── class-columns.php              # Admin list columns
│   │   └── views/
│   │       ├── settings/
│   │       │   ├── main.php               # Settings wrapper
│   │       │   ├── tab-general.php        # General settings
│   │       │   ├── tab-providers.php      # AI provider config
│   │       │   ├── tab-content.php        # Content analysis settings
│   │       │   ├── tab-schema.php         # Schema settings
│   │       │   ├── tab-social.php         # Social media settings
│   │       │   ├── tab-sitemap.php        # Sitemap settings
│   │       │   ├── tab-redirects.php      # Redirects settings
│   │       │   └── tab-advanced.php       # Advanced settings
│   │       ├── metabox/
│   │       │   ├── main.php               # Metabox wrapper
│   │       │   ├── tab-seo.php            # SEO tab
│   │       │   ├── tab-readability.php    # Readability tab
│   │       │   ├── tab-social.php         # Social tab
│   │       │   ├── tab-schema.php         # Schema tab
│   │       │   └── tab-ai.php             # AI suggestions tab
│   │       ├── redirects/
│   │       │   ├── list.php               # Redirect list
│   │       │   └── form.php               # Add/edit redirect
│   │       └── dashboard/
│   │           └── widget.php             # Dashboard widget
│   ├── rest/
│   │   ├── class-rest-controller.php      # Base REST controller
│   │   ├── class-analysis-controller.php  # Content analysis API
│   │   ├── class-ai-controller.php        # AI operations API
│   │   ├── class-settings-controller.php  # Settings API
│   │   ├── class-redirect-controller.php  # Redirects API
│   │   └── class-provider-controller.php  # Provider test API
│   └── frontend/
│       ├── class-frontend.php             # Frontend orchestrator
│       ├── class-head.php                 # <head> tag output
│       └── class-auto-seo.php             # Auto-SEO on publish
└── languages/
    └── seo-ai.pot                         # Translation template
```

## Design Patterns

### 1. Singleton Plugin Class
The main `Plugin` class follows the singleton pattern with a container-like service locator.

### 2. Module System
Features are organized into independent modules that can be enabled/disabled:
- Each module implements `Module_Interface`
- Modules are registered with `Module_Manager`
- Settings toggle individual modules on/off

### 3. Provider Pattern
AI providers follow a strategy pattern:
- All implement `Provider_Interface`
- `Provider_Manager` acts as factory/registry
- Settings determine active provider
- Each provider handles its own API communication

### 4. Hook-Based Architecture
WordPress hooks are the primary integration mechanism:
- Actions for plugin lifecycle events
- Filters for content modification
- Custom hooks for extensibility (`seo_ai/` prefix)

### 5. REST API Layer
All AJAX operations go through the WP REST API:
- Standardized request/response format
- Proper authentication via nonces
- Capability checks on all endpoints

## Data Flow

```
User Creates/Edits Post
    ↓
Metabox loads → Sends content to Analysis Engine
    ↓
Analysis Engine → Calculates SEO Score + Readability
    ↓
If auto-SEO enabled → AI Provider generates suggestions
    ↓
User reviews/accepts suggestions (or auto-apply)
    ↓
Post saves → Meta tags, Schema, Sitemap updated
    ↓
Frontend → Outputs optimized meta, schema, OG tags
```

## Option Storage

| Option Key | Description |
|---|---|
| `seo_ai_settings` | Main plugin settings (serialized array) |
| `seo_ai_providers` | AI provider configurations |
| `seo_ai_redirects` | Redirect rules |
| `seo_ai_404_log` | 404 error log |
| `seo_ai_version` | Installed version for migrations |
| `seo_ai_modules` | Enabled/disabled modules |

## Post Meta Keys

| Meta Key | Description |
|---|---|
| `_seo_ai_title` | Custom SEO title |
| `_seo_ai_description` | Custom meta description |
| `_seo_ai_focus_keyword` | Primary focus keyword |
| `_seo_ai_focus_keywords` | Additional keywords (JSON) |
| `_seo_ai_canonical` | Canonical URL override |
| `_seo_ai_robots` | Robots directives (JSON) |
| `_seo_ai_og_title` | Open Graph title |
| `_seo_ai_og_description` | Open Graph description |
| `_seo_ai_og_image` | Open Graph image ID |
| `_seo_ai_twitter_title` | Twitter title |
| `_seo_ai_twitter_description` | Twitter description |
| `_seo_ai_schema_type` | Schema type for this post |
| `_seo_ai_schema_data` | Custom schema data (JSON) |
| `_seo_ai_seo_score` | Cached SEO score |
| `_seo_ai_readability_score` | Cached readability score |
| `_seo_ai_auto_seo` | Auto-SEO enabled for this post |

## Custom Database Tables

| Table | Description |
|---|---|
| `{prefix}seo_ai_redirects` | Redirect rules (source, target, type, hits) |
| `{prefix}seo_ai_404_log` | 404 error log (url, referrer, user_agent, date) |

## REST API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | `/seo-ai/v1/analyze` | Analyze content for SEO |
| POST | `/seo-ai/v1/ai/optimize` | Get AI optimization suggestions |
| POST | `/seo-ai/v1/ai/generate-meta` | Generate meta title/description |
| POST | `/seo-ai/v1/ai/generate-schema` | Generate schema markup |
| POST | `/seo-ai/v1/provider/test` | Test AI provider connection |
| GET/POST | `/seo-ai/v1/settings` | Get/update plugin settings |
| GET/POST/DELETE | `/seo-ai/v1/redirects` | CRUD redirects |
| GET | `/seo-ai/v1/404-log` | Get 404 log entries |
