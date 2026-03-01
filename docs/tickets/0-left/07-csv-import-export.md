# 07 — CSV Import/Export for SEO Data

**Priority:** HIGH
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `csv-import-export` (Pro admin feature)

## What Rank Math Has

### CSV Import
- Upload CSV file with SEO metadata
- Column mapping (CSV columns → SEO fields)
- Supported fields: title, description, focus keyword, robots, canonical, OG data, schema type
- Background processing for large imports (100+ posts)
- Progress tracking with AJAX polling
- Error handling and validation
- Duplicate detection

### CSV Export
- Export post SEO data to CSV
- Filter by post type
- Include/exclude specific fields
- Batch export

### Import from Other Plugins
- Import settings from Yoast, AIOSEO, SEOPress, etc.
- Migrate post meta from other SEO plugin formats

## What SEO AI Currently Has

- Redirect CSV import/export (already implemented)
- No SEO metadata import/export for posts

## Implementation Plan

1. **Export** — Add "Export SEO Data" button to Settings → Advanced
   - Select post types to export
   - Select fields (title, description, keyword, robots, canonical, schema type, OG fields)
   - Generate CSV with post ID, post title, permalink + selected SEO fields
   - Stream download

2. **Import** — Add "Import SEO Data" form to Settings → Advanced
   - Upload CSV file
   - Auto-detect columns or manual mapping
   - Preview first 5 rows before import
   - Background processing with progress bar for large files
   - Report: X updated, Y skipped, Z errors

3. **Migration from Other Plugins** (Phase 2)
   - Detect installed SEO plugins (Yoast, Rank Math, AIOSEO)
   - Map their post meta keys to `_seo_ai_*` keys
   - One-click migration with rollback option

## CSV Format

```csv
post_id,post_title,seo_title,seo_description,focus_keyword,canonical,robots_noindex,schema_type
123,"My Post","SEO Title Here","Meta description...","keyword","","0","Article"
456,"Another Post","Another Title","Another desc...","another keyword","https://example.com/canonical","1","WebPage"
```

## Files to Create

- `includes/admin/class-csv-import-export.php` — Import/export handler
- `includes/admin/views/settings/partials/csv-import-export.php` — UI partial

## Files to Modify

- `includes/admin/views/settings/tab-advanced.php` — Add import/export section
- `includes/rest/class-settings-controller.php` — Add import/export REST endpoints (optional)

## Notes

- Redirect CSV import/export already exists — reuse the same patterns
- Background processing is important for sites with 1000+ posts
- Consider using WordPress batch processing (WP_Background_Process) or simple AJAX chunking
- Migration tool is high-value for users switching from Rank Math/Yoast to SEO AI
