# 02 — Advanced Schema Types & Custom Templates

**Priority:** HIGH
**Effort:** L (5-10 files, 3-7 days)
**Rank Math module:** `schema` (Free basic + Pro advanced)

## What Rank Math Has (that SEO AI doesn't)

### Additional Schema Types
SEO AI currently supports: Article, FAQPage, HowTo, Product, WebPage, Event, JobPosting, Recipe (detection only, no full builder).

Rank Math adds:
- **Recipe** — Full builder with ingredients, instructions, nutrition, cook/prep time, yield, cuisine, category
- **JobPosting** — Salary, employer, location, remote, dates, qualifications
- **Movie** — Director, actors, genre, rating, duration, production company
- **Dataset** — Name, description, distribution, license, creator
- **ClaimReview** — Fact-check schema for claims with ratings
- **QAPage** — Question/answer page schema (different from FAQPage)
- **PodcastEpisode** — Episode name, URL, duration, series
- **NewsArticle** — Distinct from Article, for news-specific markup
- **BlogPosting** — Already partially supported, but no dedicated builder

### Custom Schema Templates
- Create reusable schema templates
- Drag-and-drop schema builder UI
- Apply templates to multiple posts
- Template management interface (CRUD)

### Schema Display Conditions
- Conditional schema display based on:
  - Post type
  - Taxonomy/category
  - User role
  - Device type (mobile/desktop)

### Video Schema Auto-Detection
- Auto-detect YouTube, Vimeo, DailyMotion, TED, VideoPress, native WP video
- Extract metadata (duration, thumbnail, description)
- Generate VideoObject schema automatically

### HowTo Block Enhancements
- Estimated cost field
- Supplies list
- Tools list
- Materials list

### Schema Shortcodes
- `[rank-math-faqpage]`, `[rank-math-howto]`, `[rank-math-recipe]`, etc.
- Embeddable schema blocks

## What SEO AI Currently Has

- Schema Manager outputs JSON-LD in @graph format
- 8 schema types: WebSite, Organization/Person, BreadcrumbList, Article/BlogPosting/NewsArticle, FAQPage, HowTo, Product, WebPage
- Per-post schema type override
- AI-powered schema type detection
- Basic HowTo with steps/images/duration
- Product with offers/pricing/availability/ratings

## Implementation Plan

### Phase 1 — Schema Type Builders
1. **Recipe Builder** — Full metabox fields for recipe schema
2. **JobPosting Builder** — Salary, employer, location fields
3. **VideoObject** — Auto-detection from post content for YouTube/Vimeo

### Phase 2 — Advanced Types
1. **Movie** schema builder
2. **Dataset** schema builder
3. **ClaimReview** schema builder
4. **PodcastEpisode** schema builder

### Phase 3 — Templates & Conditions
1. **Custom Schema Templates** — Create, save, apply reusable templates
2. **Display Conditions** — Conditional schema per post type/taxonomy

## Files to Modify/Create

- `includes/modules/schema/class-schema-manager.php` — Add new type handlers
- `includes/admin/views/metabox/schema/` — New metabox panels per type
- `includes/modules/schema/class-video-detector.php` — Video auto-detection
- `includes/modules/schema/class-schema-templates.php` — Template system
- `assets/js/metabox.js` — Schema builder UI interactions

## Notes

- Recipe and JobPosting are the highest-value additions (most searched schema types)
- Video schema auto-detection provides "magic" value with no user effort
- Custom templates can be deferred — most users won't need them
- Consider AI-assisted schema field population (e.g., AI extracts recipe ingredients from content)
