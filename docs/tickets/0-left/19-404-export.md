# 19 — 404 Monitor Export

**Priority:** LOW
**Effort:** S (1-2 files, < 1 day)
**Rank Math module:** `404-monitor` (Pro enhancement)

## What Rank Math Has

- CSV export of 404 log entries
- Date range filtering for export (from/to dates)
- Export panel UI in 404 monitor page
- Total hits column in 404 list table

## What SEO AI Currently Has

- 404 monitoring with logging (request URI, referrer, user agent, hit count, timestamps)
- 404 log admin page with list table
- REST API: GET /logs (read), DELETE /logs (clear)
- No export functionality

## Implementation Plan

1. **Export Button** — Add "Export CSV" button to 404 log admin page
2. **Date Range Filter** — Optional from/to date inputs
3. **CSV Generation** — Query 404 log table, stream CSV download
4. **Columns** — URL, hit count, referrer, first hit, last hit

## Files to Modify

- `includes/admin/views/redirects/404-log.php` — Add export button + date filter form
- `includes/modules/redirects/class-404-monitor.php` — Add export handler method

## CSV Format

```csv
url,hit_count,referrer,user_agent,first_hit,last_hit
/old-page,42,https://google.com,"Mozilla/5.0...",2026-01-15 10:30:00,2026-03-01 14:22:00
/missing-image.jpg,12,https://example.com/post,"Mozilla/5.0...",2026-02-20 08:15:00,2026-03-01 09:45:00
```

## Notes

- Very simple feature, can be done in under 2 hours
- Follows same pattern as redirect CSV export (already implemented)
- Date range filter is a nice touch but not required for MVP
- Could also add "Export & Create Redirects" bulk action (export 404s and pre-fill redirect import)
