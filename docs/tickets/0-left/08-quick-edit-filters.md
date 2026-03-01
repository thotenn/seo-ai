# 08 — Quick Edit & Post List Filters

**Priority:** HIGH
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `quick-edit` + `post-filters` (Pro)

## What Rank Math Has

### Quick Edit
- Inline editing of SEO fields directly in post list table:
  - SEO title
  - Meta description
  - Focus keyword
  - Robots directives (noindex/nofollow)
  - Canonical URL
  - Primary term selection
  - Schema type
  - Search intent
- Bulk edit for multiple selected posts
- Taxonomy term quick edit (SEO fields for categories/tags)

### Post List Filters
- **Filter by Robots Status** — Show only noindex/index posts
- **Filter by Schema Type** — Show posts with specific schema
- **Filter by Canonical** — Show posts with/without custom canonical
- **Filter by Primary Taxonomy** — Filter by primary category
- **Filter by Search Intent** — Informational/transactional/navigational
- **Filter by SEO Score Range** — Good/fair/poor score ranges

### Media Library Filters
- Filter attachments by SEO status (has alt text / missing alt text)

## What SEO AI Currently Has

- SEO score column in post list table (sortable)
- No quick edit fields
- No post list filters
- No media filters

## Implementation Plan

### Phase 1 — Post List Filters
1. Add dropdown filters to post list:
   - SEO Score: All / Good (70+) / Needs Work (40-69) / Poor (<40) / Not Analyzed
   - Robots: All / Index / Noindex
   - Schema Type: All / Article / WebPage / FAQ / HowTo / Product / None
2. Filter the WP_Query in `pre_get_posts` based on meta values

### Phase 2 — Quick Edit
1. Add SEO fields to WordPress quick edit form:
   - SEO title (text)
   - Focus keyword (text)
   - Robots noindex (checkbox)
   - Schema type (select)
2. Save via AJAX using existing post meta helpers
3. Display current values in hidden columns for JS population

### Phase 3 — Bulk Edit
1. Bulk set robots (noindex/index) for selected posts
2. Bulk set schema type
3. Bulk clear/regenerate SEO fields with AI

### Phase 4 — Media Filters
1. Filter media by alt text status (has/missing)
2. Bulk add alt text to selected images

## Files to Create

- `includes/admin/class-quick-edit.php` — Quick edit fields + save handler
- `includes/admin/class-post-filters.php` — Post list dropdown filters

## Files to Modify

- `includes/admin/class-columns.php` — Add hidden data columns for quick edit
- `includes/admin/class-admin.php` — Register new admin classes
- `assets/js/admin.js` — Quick edit JS for populating fields

## Notes

- Quick edit is one of the most requested features for power users managing many posts
- Filters by SEO score are high-value — let users quickly find posts that need attention
- Use existing WordPress quick edit API (`quick_edit_custom_box` action)
- Media library filters help with Image SEO workflow
