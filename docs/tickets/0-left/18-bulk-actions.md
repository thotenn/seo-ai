# 18 — Bulk Actions Enhancement

**Priority:** MEDIUM
**Effort:** S (1-2 files, < 1 day)
**Rank Math module:** `bulk-actions` (Pro)

## What Rank Math Has

### Post Bulk Actions
- Bulk set robots (noindex/index/nofollow/follow)
- Bulk remove custom canonicals
- Bulk assign primary taxonomy term
- Bulk set schema type (none/default/specific)
- Bulk determine search intent

### Taxonomy Bulk Actions
- Bulk term metadata updates
- Bulk robots updates for categories/tags

## What SEO AI Currently Has

- Bulk action: "Optimize SEO with AI"
- Bulk action: "Set Noindex"
- Bulk action: "Remove Noindex"

## Implementation Plan

1. **Bulk Set Schema Type** — Add bulk action to assign schema type to selected posts
2. **Bulk Remove Canonical** — Clear custom canonical URLs for selected posts
3. **Bulk Set Nofollow** — Add/remove nofollow for selected posts
4. **Bulk Clear SEO Data** — Reset all SEO meta for selected posts (with confirmation)
5. **Bulk Re-Analyze** — Re-run content analysis on selected posts

## Files to Modify

- `includes/admin/class-bulk-actions.php` — Add new bulk action handlers

## Notes

- Most of these are straightforward additions to the existing bulk actions class
- Each action just needs: register bulk action dropdown option + handle the action on `handle_bulk_actions-{screen}`
- Confirmation dialog recommended for destructive actions (clear SEO data)
- Small effort, moderate user value for managing large sites
