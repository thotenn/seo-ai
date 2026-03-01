# CLAUDE.md - SEO AI Plugin

This file provides context for Claude Code when working on this project.

## Project Overview

SEO AI is a WordPress plugin that provides comprehensive SEO optimization with AI-powered content generation. It supports 5 AI providers (OpenAI, Claude, Gemini, Ollama, OpenRouter) and features a modular architecture with 9 toggleable feature modules.

## Tech Stack

- **Language:** PHP 8.0+ with strict types
- **Framework:** WordPress 6.4+ Plugin API
- **Frontend JS:** Vanilla JavaScript with jQuery (WordPress-bundled)
- **CSS:** Plain CSS (no preprocessor)
- **Database:** WordPress `$wpdb` with custom tables
- **REST API:** WordPress REST API (`seo-ai/v1` namespace)
- **No build step** -- no Node.js, no Webpack, no Composer autoload

## Architecture

### Namespace & Autoloading

All classes use the `SeoAi` namespace. The autoloader in `seo-ai.php` maps namespaces to file paths:

```
SeoAi\Admin\Admin         → includes/admin/class-admin.php
SeoAi\Modules\Schema\Schema_Manager → includes/modules/schema/class-schema-manager.php
```

Convention: `Class_Name` → `class-class-name.php` (WordPress kebab-case file naming).

### Singleton Pattern

`Plugin::instance()` is the main entry point. Access it via the global `seo_ai()` function. Never instantiate Plugin directly.

### Dependency Flow

```
Plugin (singleton)
  ├── Options (settings CRUD)
  ├── Post_Meta (post meta CRUD, prefix: _seo_ai_)
  ├── Capability (permission management)
  ├── Provider_Manager (AI providers registry)
  ├── Module_Manager (feature modules registry)
  ├── Admin (admin UI, only in is_admin())
  └── Frontend (head output, only on frontend)
```

### Module System

Modules are registered in `Module_Manager::build_module_definitions()`. Each module has:
- A unique `id` (e.g., `meta_tags`, `open_graph`)
- One or more implementing classes
- A `default` enabled state
- Classes must implement `register_hooks()` method

Enabled modules are stored in the `enabled_modules` key of `seo_ai_settings`.

### REST API Controllers

All controllers extend `Rest_Controller` (abstract). They receive the Plugin instance in their constructor and register routes in `register_routes()`.

### Admin Views

PHP template files in `includes/admin/views/`. Variables are injected by the Admin class methods before including the view. No template engine.

## Key Files

| File | Purpose |
|------|---------|
| `seo-ai.php` | Bootstrap, constants, autoloader |
| `includes/class-plugin.php` | Main singleton, hooks registration |
| `includes/class-activator.php` | DB tables, default options, capabilities |
| `includes/admin/class-admin.php` | All admin: menus, assets, metabox, widget |
| `includes/modules/class-module-manager.php` | Module registry and lifecycle |
| `includes/providers/class-provider-manager.php` | AI provider registry |
| `includes/frontend/class-frontend.php` | Frontend output orchestrator |
| `uninstall.php` | Full data cleanup on plugin deletion |

## Conventions

### PHP
- Use `declare(strict_types=1)` in module files
- Guard with `defined('ABSPATH') || exit;` at top of every file
- Use WordPress coding standards (tabs for indentation, Yoda conditions)
- Sanitize all input, escape all output
- Use `$wpdb->prepare()` for all SQL queries
- Prefix all options with `seo_ai_`, all post meta with `_seo_ai_`
- Hook callbacks should be public methods, helpers should be private

### JavaScript
- jQuery IIFE pattern: `(function($) { 'use strict'; ... })(jQuery);`
- `seoAi` global for REST URL, nonce, admin settings (localized via `wp_localize_script`)
- `seoAiPost` global for post-specific data in the editor (post ID, title, URL, meta)
- No ES modules, no build step

### CSS
- Class prefix: `seo-ai-` for all custom classes
- Sections separated by comment banners
- No CSS variables (compatibility)
- Colors: primary `#2563eb`, success `#16a34a`, warning `#f59e0b`, error `#dc2626`

### Database
- Two custom tables: `{prefix}seo_ai_redirects`, `{prefix}seo_ai_404_log`
- Created via `dbDelta()` in Activator
- Dropped in `uninstall.php`

## WordPress Options

| Option Key | Content |
|------------|---------|
| `seo_ai_settings` | All plugin settings (modules, templates, analysis config) |
| `seo_ai_providers` | AI provider configs and active provider |
| `seo_ai_version` | Installed plugin version |

## Custom Capabilities

| Capability | Granted To |
|------------|------------|
| `seo_ai_manage_settings` | Administrator |
| `seo_ai_manage_redirects` | Administrator |
| `seo_ai_view_reports` | Administrator |

## Post Meta Keys

All prefixed with `_seo_ai_`:

`title`, `description`, `focus_keyword`, `focus_keywords` (JSON), `canonical`, `robots` (JSON), `og_title`, `og_description`, `og_image`, `twitter_title`, `twitter_description`, `schema_type`, `schema_data` (JSON), `seo_score`, `readability_score`, `auto_seo`

## Common Tasks

### Adding a new module

1. Create class(es) in `includes/modules/<module-name>/`
2. Implement `register_hooks()` method
3. Add definition to `Module_Manager::build_module_definitions()`
4. Add UI toggle in `views/settings/tab-general.php` if needed

### Adding a new AI provider

1. Create class in `includes/providers/` implementing `Provider_Interface`
2. Register in `Provider_Manager::register_default_providers()`
3. Add config card in `views/settings/tab-providers.php`

### Adding a new REST endpoint

1. Create or extend a controller in `includes/rest/`
2. Add route in `register_routes()` with permission callback
3. If new controller, add FQCN to `Plugin::$rest_controllers`

### Adding a new settings tab

1. Create `views/settings/tab-<name>.php`
2. Add the tab slug and label to the `$tabs` array in `views/settings/main.php`
3. Tabs are server-side rendered (full page reload per tab via `?tab=` query param) — no client-side JS switching

## Testing

### E2E Tests (Playwright)

The plugin includes Playwright E2E tests that run against a local WordPress instance.

**Setup:**

```bash
cd wp-content/plugins/seo-ai
npm install
npx playwright install chromium
cp .env.test.example .env.test  # then edit credentials
```

**Run tests:**

```bash
npm test              # run all tests
npm run test:ui       # interactive UI mode
npm run test:headed   # run with visible browser
npm run test:debug    # step-by-step debug mode
```

**Test structure:**

| File | Coverage |
|------|----------|
| `tests/auth.setup.ts` | WP login, saves session for other tests |
| `tests/admin/pages.spec.ts` | All 4 admin pages, 8 settings tabs, CSS/JS assets, seoAi global |
| `tests/metabox/editor.spec.ts` | Metabox rendering, 5 tab switching, metabox assets, seoAiPost global, field editing |
| `tests/redirects/crud.spec.ts` | Redirects page, form fields, redirect creation |

**Auth strategy:** Tests use Playwright's `storageState` pattern. `auth.setup.ts` logs in once and saves cookies to `playwright/.auth/user.json`. All other tests reuse the saved session.

**Environment:** Tests target `http://localhost:8080` (Docker local dev). Credentials are in `.env.test` (gitignored).

### Manual Testing

- WordPress admin UI
- REST API calls (use `wp_create_nonce('wp_rest')` for auth)
- Browser DevTools for JS/CSS debugging

## Versioning

**Current version: 0.0.3**

This plugin uses semantic versioning (MAJOR.MINOR.PATCH). Every change MUST include a version bump:

1. Update `Version:` in the plugin header comment in `seo-ai.php`
2. Update the `SEO_AI_VERSION` constant in `seo-ai.php`
3. Update `CHANGELOG.md` with the changes

`SEO_AI_VERSION` is used as the cache-busting parameter for all enqueued CSS/JS assets (`wp_enqueue_style`/`wp_enqueue_script`). If the version is not bumped, browsers will serve stale cached files and changes won't take effect.

Version guidelines:
- **PATCH** (0.0.x): Bug fixes, CSS tweaks, copy changes
- **MINOR** (0.x.0): New features, new modules, new settings tabs, new REST endpoints
- **MAJOR** (x.0.0): Breaking changes, database migrations, architecture changes

## Gotchas

- `Provider_Manager` constructor takes **no arguments** -- it loads settings from `seo_ai_providers` option internally
- Frontend module classes (`Open_Graph`, `Twitter_Cards`, `Schema_Manager`) live in `SeoAi\Modules\*` namespace, NOT `SeoAi\Frontend\*`
- Module IDs use underscores (`meta_tags`, `open_graph`), not hyphens
- The `Options::get()` method returns from the `seo_ai_settings` option, not individual WP options
- `Post_Meta` auto JSON-encodes/decodes `focus_keywords`, `robots`, `schema_data`
- Dashboard widget requires `seo_ai_view_reports` capability
