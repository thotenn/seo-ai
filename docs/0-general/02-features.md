# SEO-AI Features Specification

## Feature Matrix (Combined from Yoast Premium + Rank Math Pro + AI Enhancement)

### 1. Content Analysis Engine
**Source:** Both plugins | **AI-Enhanced:** Yes

- **SEO Score (0-100):** Weighted scoring based on multiple factors
- **Readability Score (0-100):** Flesch reading ease, sentence length, paragraph length
- **Focus Keyword Analysis:**
  - Keyword in title, meta description, URL, first paragraph, headings
  - Keyword density (ideal range detection)
  - Multiple focus keywords support (up to 5)
  - Keyword synonyms tracking
- **Content Length Check:** Min recommended words based on post type
- **Heading Structure:** H1-H6 hierarchy validation
- **Internal Links:** Count and suggestions for internal linking
- **External Links:** Count and nofollow recommendations
- **Image Optimization:** Alt text presence, keyword in alt text
- **AI-Powered Suggestions:**
  - Auto-generate missing meta titles/descriptions
  - Keyword placement recommendations
  - Content gap analysis
  - Readability improvement suggestions
  - Schema type recommendations

### 2. Meta Tags Management
**Source:** Both plugins | **AI-Enhanced:** Yes

- **Title Tag:**
  - Custom title per post/page/term
  - Template variables: `%title%`, `%sitename%`, `%sep%`, `%page%`, `%category%`
  - Character counter with preview
  - AI auto-generation option
- **Meta Description:**
  - Custom description per post/page/term
  - Character counter (recommended 120-160)
  - AI auto-generation from content
- **Robots Meta:**
  - index/noindex toggle
  - follow/nofollow toggle
  - Advanced: noarchive, nosnippet, noimageindex, max-snippet, max-image-preview, max-video-preview
- **Canonical URL:**
  - Auto-canonical (self-referencing)
  - Custom canonical override
  - Pagination canonical handling
- **Template System:**
  - Default templates per post type
  - Default templates per taxonomy
  - Archive templates (author, date)
  - Variables for dynamic content

### 3. Schema / Structured Data (JSON-LD)
**Source:** Both plugins | **AI-Enhanced:** Yes

**Supported Schema Types:**
1. Article / NewsArticle / BlogPosting
2. Organization / LocalBusiness
3. WebSite (with SearchAction)
4. BreadcrumbList
5. FAQPage
6. HowTo
7. Product (basic + WooCommerce)
8. Recipe
9. Event
10. JobPosting
11. Person
12. VideoObject
13. ImageObject
14. Review / AggregateRating
15. Course
16. SoftwareApplication

**Schema Features:**
- Auto-detection of appropriate schema type per post
- Custom schema per post via meta box
- Global schema settings (organization info)
- Breadcrumb schema auto-generation
- AI-powered schema suggestion based on content analysis
- Schema validation and preview
- Knowledge Graph data (organization/person)

### 4. XML Sitemap
**Source:** Both plugins

- **Sitemap Index:** Aggregates all sub-sitemaps
- **Post Sitemaps:** Per post type with pagination (1000 per page)
- **Taxonomy Sitemaps:** Per taxonomy
- **Author Sitemap:** Optional
- **Image Sitemap:** Include images in sitemap entries
- **Exclude Posts/Terms:** Per-post noindex respects sitemap
- **Last Modified:** Accurate lastmod dates
- **XSL Stylesheet:** Human-readable sitemap view
- **Ping Search Engines:** Auto-ping on update
- **robots.txt Integration:** Auto-add sitemap URL

### 5. Social Media Integration
**Source:** Both plugins | **AI-Enhanced:** Yes

**Open Graph:**
- og:title, og:description, og:image, og:url, og:type, og:locale
- Custom OG per post/page
- Default OG templates per post type
- OG for archives (author, date, taxonomy)
- AI auto-generation of social descriptions

**Twitter Cards:**
- Summary / Summary with Large Image
- twitter:title, twitter:description, twitter:image
- Twitter site/creator handles
- Custom per post

**Social Previews:**
- Real-time Facebook preview in editor
- Real-time Twitter preview in editor

### 6. Redirect Manager
**Source:** Both plugins

- **Redirect Types:** 301, 302, 307, 410, 451
- **Redirect Formats:** Plain URL + Regex patterns
- **Auto-Redirect:** When post slug changes
- **404 Monitor:** Log 404 errors with URL, referrer, user agent, date
- **Bulk Operations:** Import/export CSV
- **Admin Interface:** WP_List_Table with search, sort, pagination
- **Hit Counter:** Track redirect usage
- **Categories:** Organize redirects (optional)

### 7. Image SEO
**Source:** Rank Math Pro | **AI-Enhanced:** Yes

- **Auto Alt Text:** Generate from image title/filename
- **Auto Title Attribute:** Generate from image meta
- **AI Alt Text:** Use AI to describe images and generate alt text
- **Bulk Update:** Update all images missing alt text
- **Template Variables:** `%filename%`, `%title%`, `%site_name%`

### 8. Breadcrumbs
**Source:** Both plugins

- **Shortcode:** `[seo_ai_breadcrumb]`
- **PHP Function:** `seo_ai_breadcrumb()`
- **Schema Markup:** BreadcrumbList JSON-LD
- **Customizable Separator:** Between breadcrumb items
- **Home Link:** Customizable home text
- **Post Type Archives:** Include in trail
- **Taxonomy in Trail:** Show category/tag in breadcrumb

### 9. Robots.txt Management
**Source:** Rank Math Pro

- **Visual Editor:** Edit robots.txt from admin
- **Default Rules:** Auto-generated sensible defaults
- **Sitemap Reference:** Auto-add sitemap URL
- **Per-Bot Rules:** Custom rules for different crawlers

### 10. AI-Powered Features (UNIQUE TO SEO-AI)

**Auto-SEO on Publish:**
- When user publishes/updates a post, AI automatically:
  - Generates/improves meta title if missing or weak
  - Generates/improves meta description if missing or weak
  - Suggests and adds focus keyword
  - Generates Open Graph tags
  - Recommends and sets schema type
  - Optimizes image alt text
  - Suggests internal links
- User can toggle this globally or per-post
- Uses the configured AI provider

**Bulk AI Optimization:**
- Select multiple posts from admin list
- One-click AI optimization for all selected
- Background processing with progress tracking
- Configurable what to optimize (title, desc, schema, etc.)

**Content AI Assistant:**
- In-editor sidebar panel
- Real-time AI suggestions as you write
- "Fix with AI" buttons on failing SEO checks
- Keyword research suggestions
- Content outline generation
- Related keyword discovery

**AI Provider System:**
- Multiple providers: OpenAI, Claude, Gemini, Ollama, OpenRouter
- Configurable per-provider: API key, model, base URL, temperature
- Connection testing per provider
- Provider fallback chain
- Ollama for free/local usage (no API key required)
- Custom prompt templates for SEO tasks

### 11. Bulk Actions
**Source:** Rank Math Pro

- Change robots directives (noindex/nofollow) in bulk
- Set/remove canonical URLs
- Assign schema type
- Run AI optimization
- Export SEO data to CSV

### 12. Admin Columns
**Source:** Both plugins

- SEO Score column in post list
- Readability Score column
- Focus Keyword column
- Schema Type column
- SEO Title column
- Sortable and filterable

### 13. Dashboard Widget
- Overall SEO health score
- Posts needing attention (low score)
- Recent 404 errors
- AI optimization stats
- Quick links to settings

### 14. WooCommerce Integration (Future)
**Source:** Both plugins

- Product schema markup
- Product sitemap
- Product-specific SEO settings
- GTIN/MPN/Brand fields
- Review schema

### 15. Local SEO (Future)
**Source:** Rank Math Pro

- Local Business schema
- Multiple locations support
- Google Maps integration
- KML file generation
