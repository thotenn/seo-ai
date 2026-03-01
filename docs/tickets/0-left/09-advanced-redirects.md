# 09 — Advanced Redirects

**Priority:** MEDIUM
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `redirections` (Free basic + Pro advanced)

## What Rank Math Has (Pro enhancements over free)

### .htaccess Sync
- Export all active redirects to `.htaccess` file
- Apache-level redirects (faster than PHP)
- Automatic block management (add/remove Rank Math section)
- Writable `.htaccess` detection

### Redirect Categories
- Group redirects by custom categories
- Filter redirects by category in admin
- Bulk category operations

### Scheduled Redirects
- Set activation/deactivation dates for redirects
- Cron-based scheduling
- Automatic cleanup of expired redirects

### Query Parameter Matching
- Match redirects including URL query parameters
- Flexible parameter handling (ignore, match, strip)

### Auto-Redirect Cleanup
- Auto-delete redirects when source post/term is permanently deleted
- Mark posts/terms that were redirected
- Clean up orphaned redirects

## What SEO AI Currently Has

- Full redirect CRUD (301, 302, 307, 410, 451)
- Regex pattern support
- Hit counter
- Auto-redirect on slug change
- CSV import/export
- Active/inactive toggle
- 404 monitoring

## Implementation Plan

### Phase 1 — Query Parameter Support
1. Add query parameter matching option per redirect (ignore, exact match, strip)
2. Parse and compare query strings in redirect handler

### Phase 2 — Redirect Categories
1. Add `category` column to redirects table
2. Category filter dropdown in redirect list
3. Bulk assign categories

### Phase 3 — Scheduled Redirects
1. Add `active_from` and `active_until` datetime columns
2. Cron job to activate/deactivate scheduled redirects
3. UI for setting schedule dates

### Phase 4 — .htaccess Sync
1. Generate Apache RewriteRule syntax from active redirects
2. Write to `.htaccess` within marked block
3. Detect file writability
4. Admin UI button with confirmation

## Files to Modify

- `includes/modules/redirects/class-redirect-manager.php` — Add query param + schedule logic
- `includes/modules/redirects/class-redirect-handler.php` — Query param matching in handler
- `includes/admin/views/redirects/list.php` — Category filter, schedule columns
- `includes/rest/class-redirect-controller.php` — New fields in REST

## Files to Create

- `includes/modules/redirects/class-htaccess-sync.php` — .htaccess export

## DB Migration

```sql
ALTER TABLE {prefix}seo_ai_redirects
  ADD COLUMN category VARCHAR(100) DEFAULT '' AFTER status,
  ADD COLUMN active_from DATETIME DEFAULT NULL,
  ADD COLUMN active_until DATETIME DEFAULT NULL,
  ADD COLUMN query_handling ENUM('ignore','exact','strip') DEFAULT 'ignore';
```

## Notes

- .htaccess sync is server-specific (Apache only) — detect server type first
- Scheduled redirects are useful for marketing campaigns with temp URLs
- Query parameter matching catches the edge cases that cause 404s
- Auto-cleanup is already partially handled by slug change detection
