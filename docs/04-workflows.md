# SEO-AI Workflows

## 1. Post Creation/Edit Workflow

```
User opens post editor
    ↓
SEO-AI metabox loads in sidebar/below editor
    ↓
User writes content
    ↓
[Real-time] Content Analysis runs:
    → Keyword density check
    → Readability score
    → Heading structure
    → Link analysis
    → Image alt text check
    → Meta tag completeness
    ↓
SEO Score + Readability Score displayed (color-coded)
    ↓
If AI enabled → "Optimize with AI" button available
    ↓
User clicks "Optimize with AI" OR auto-optimize on save
    ↓
AI Provider processes content:
    → Generates/improves meta title
    → Generates/improves meta description
    → Suggests focus keyword (if missing)
    → Recommends schema type
    → Suggests OG tags
    ↓
Suggestions shown in metabox for review
    ↓
User accepts/modifies/rejects each suggestion
    ↓
Post saves → All SEO data stored as post meta
    ↓
Frontend renders: meta tags, schema, OG tags, canonical, etc.
```

## 2. Auto-SEO on Publish Workflow

```
User has "Auto-SEO on Publish" enabled in Settings
    ↓
User publishes/updates a post
    ↓
Hook: save_post (priority 20, after content saved)
    ↓
Auto_SEO class checks:
    → Is auto-SEO enabled globally?
    → Is auto-SEO enabled for this post type?
    → Is auto-SEO enabled for this specific post? (per-post toggle)
    → Is an AI provider configured and working?
    ↓
If all yes → Queue SEO optimization:
    1. Check if meta title exists and scores well
       → If not: Generate with AI
    2. Check if meta description exists and scores well
       → If not: Generate with AI
    3. Check if focus keyword is set
       → If not: Extract from content with AI
    4. Check if schema type is set
       → If not: Detect with AI
    5. Check if OG tags are set
       → If not: Generate from meta title/desc
    6. Check images for alt text
       → If missing: Generate with AI
    ↓
All generated data saved as post meta
    ↓
Admin notice: "SEO optimized automatically. Review changes."
    ↓
User can review/modify in metabox
```

## 3. Bulk AI Optimization Workflow

```
User goes to Posts → All Posts
    ↓
Selects multiple posts via checkboxes
    ↓
Bulk Actions → "Optimize SEO with AI"
    ↓
Confirmation modal:
    "Optimize SEO for X posts?"
    Checkboxes: □ Meta Title □ Meta Description □ Focus Keyword
                □ Schema Type □ OG Tags □ Image Alt Text
    ↓
User clicks "Start Optimization"
    ↓
Background processing via WP Cron / REST API batches:
    → Process 5 posts per batch
    → Update progress bar in real-time (via AJAX polling)
    → For each post:
        1. Load content
        2. Send to AI provider
        3. Parse response
        4. Save meta data
        5. Log result
    ↓
Completion: "X posts optimized. Y warnings. Z errors."
    ↓
Admin can review changes via "View Changes" link
```

## 4. Provider Configuration Workflow

```
User goes to SEO AI → Settings → Providers tab
    ↓
Provider cards displayed:
    [OpenAI] [Claude] [Gemini] [Ollama] [OpenRouter]
    Each shows: Name, Status (Configured/Not Configured)
    ↓
User clicks a provider card (e.g., Ollama)
    ↓
Provider settings panel expands:
    → Base URL: http://localhost:11434 [Reset to Default]
    → Model: [Fetch Models ▼] → dropdown populated from /api/tags
    → Temperature: 0.3 (slider)
    ↓
User clicks "Test Connection"
    ↓
AJAX POST to /seo-ai/v1/provider/test
    → Provider sends test request
    → Returns: ✓ Connected (model: llama3.2) or ✗ Error: message
    ↓
User clicks "Save Settings"
    ↓
AJAX POST to /seo-ai/v1/settings
    → Saves to seo_ai_providers option
    → Toast: "Settings saved successfully!"
```

## 5. Redirect Management Workflow

```
A. Manual Redirect Creation:
    User goes to SEO AI → Redirects → Add New
    → Source URL: /old-page
    → Target URL: /new-page
    → Type: 301 (Permanent)
    → Save
    → Redirect appears in list table

B. Auto-Redirect on Slug Change:
    User changes post slug from /old-slug to /new-slug
    → Hook: post_updated
    → Compare old/new slugs
    → Auto-create 301 redirect from /old-slug to /new-slug
    → Admin notice: "Redirect created automatically"

C. 404 to Redirect:
    Visitor hits a 404 page
    → URL logged in 404 monitor table
    → Admin sees 404 log in SEO AI → Redirects → 404 Log
    → Click "Create Redirect" next to a 404 entry
    → Pre-filled form with source = 404 URL
    → Enter target URL and save

D. Bulk Import:
    SEO AI → Redirects → Import/Export
    → Upload CSV file (source_url, target_url, type)
    → Preview import
    → Confirm
    → Redirects created in batch
```

## 6. Sitemap Generation Workflow

```
Plugin activated
    ↓
Sitemap rules registered on init
    ↓
Rewrite rules added: /sitemap.xml, /sitemap-posts.xml, etc.
    ↓
Search engine/visitor requests /sitemap.xml
    ↓
Sitemap Index generated:
    → Lists all sub-sitemaps with lastmod dates
    → /sitemap-post-1.xml
    → /sitemap-page-1.xml
    → /sitemap-category-1.xml
    → etc.
    ↓
Sub-sitemap requested (e.g., /sitemap-post-1.xml)
    ↓
Query posts (1000 per page):
    → Exclude noindex posts
    → Exclude redirected URLs
    → Include: loc, lastmod, changefreq, priority
    → Include images if enabled
    ↓
XML output with XSL stylesheet reference
    ↓
Caching: Sitemaps cached in transients
    → Invalidated on post save/delete
    → Invalidated on settings change
```

## 7. Content Analysis Workflow (Real-time)

```
User types in editor
    ↓
Debounced (500ms) content analysis triggered
    ↓
Analysis Engine receives:
    → Post title
    → Post content (HTML stripped)
    → Focus keyword(s)
    → URL slug
    → Featured image
    ↓
Analysis checks run:

SEO Checks (scored):
    1. Keyword in title                    (weight: 15)
    2. Keyword in meta description         (weight: 10)
    3. Keyword in URL                      (weight: 10)
    4. Keyword in first paragraph          (weight: 10)
    5. Keyword in headings                 (weight: 8)
    6. Keyword density (1-3%)              (weight: 10)
    7. Meta title length (50-60 chars)     (weight: 8)
    8. Meta description length (120-160)   (weight: 8)
    9. Content length (min 300 words)      (weight: 7)
    10. Internal links present             (weight: 7)
    11. External links present             (weight: 4)
    12. Image with alt text                (weight: 5)

Readability Checks (scored):
    1. Flesch reading ease                 (weight: 20)
    2. Sentence length (avg < 20 words)    (weight: 15)
    3. Paragraph length (avg < 150 words)  (weight: 15)
    4. Passive voice (< 10%)              (weight: 15)
    5. Transition words (> 30%)           (weight: 10)
    6. Consecutive sentences              (weight: 10)
    7. Subheading distribution            (weight: 15)

    ↓
Results displayed in metabox:
    → Overall SEO Score: 0-100 (green/orange/red)
    → Overall Readability: 0-100 (green/orange/red)
    → Individual checks with icons (✓ green, △ orange, ✗ red)
    → Each check has "Fix with AI" button
    ↓
Score saved as post meta on post save
```

## 8. Schema Auto-Detection Workflow

```
Post saved or schema tab opened
    ↓
Schema Manager analyzes:
    → Post type (post, page, product, etc.)
    → Content keywords
    → Content structure (Q&A → FAQ, Steps → HowTo)
    → Categories/tags
    ↓
Default schema determined:
    → Blog posts → Article / BlogPosting
    → Pages → WebPage
    → Products → Product
    → Content with FAQ pattern → FAQPage
    → Content with steps → HowTo
    → Content with recipe pattern → Recipe
    ↓
If AI enabled → More accurate detection:
    → Send content snippet to AI
    → AI returns recommended schema type
    → Shown as suggestion in Schema tab
    ↓
Schema JSON-LD generated and output in <head>
    ↓
User can override in metabox Schema tab:
    → Select schema type from dropdown
    → Fill in additional fields (varies by type)
    → Custom JSON-LD editor (advanced)
```
