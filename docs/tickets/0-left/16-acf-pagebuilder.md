# 16 — ACF / Page Builder Integration

**Priority:** LOW
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `acf` (Pro) + 3rdparty integrations

## What Rank Math Has

### ACF (Advanced Custom Fields)
- Extract images from ACF fields for image sitemap
- Supported field types: WYSIWYG, textarea, flexible content, repeater, group, gallery, single image
- Recursive extraction through nested field structures
- Content parsing from ACF HTML fields for SEO analysis

### Elementor Integration
- Schema block support in Elementor
- Custom field mapping
- Content extraction for SEO analysis

### Divi Integration
- Custom field support
- Content extraction for SEO analysis

## What SEO AI Currently Has

- Content analysis works on standard `post_content`
- No awareness of ACF, Elementor, or Divi content

## Implementation Plan

### Phase 1 — Content Extraction Framework
1. Create a content aggregation system that collects content from multiple sources
2. Hook into `seo_ai/content_for_analysis` filter
3. Standard post_content as base

### Phase 2 — ACF Support
1. Detect ACF plugin active
2. Get all ACF fields for post
3. Extract text content from WYSIWYG, textarea, text fields
4. Extract images from image, gallery fields
5. Append ACF content to analysis content

### Phase 3 — Page Builder Support
1. Detect Elementor/Divi active
2. Parse their rendered output
3. Extract text and images for analysis

## Files to Create

- `includes/helpers/class-content-extractor.php` — Aggregates content from multiple sources
- `includes/integrations/class-acf.php` — ACF field extraction
- `includes/integrations/class-elementor.php` — Elementor content extraction (optional)

## Files to Modify

- `includes/modules/content-analysis/class-analyzer.php` — Use content extractor
- `includes/modules/sitemap/class-sitemap-manager.php` — Include ACF images in sitemap

## Notes

- ACF is one of the most popular WordPress plugins (5M+ installs)
- Without ACF integration, SEO analysis misses significant content on ACF-heavy sites
- Phase 1 (content extraction framework) benefits all future integrations
- Page builder support is complex — rendered output parsing is fragile
