# 15 — Advanced Robots.txt Editor

**Priority:** LOW
**Effort:** S (1-2 files, < 1 day)
**Rank Math module:** `robots-txt` (Pro enhancement)

## What Rank Math Has

- Visual robots.txt editor in admin
- Per-user-agent rule sections
- Syntax validation
- Site URL reference
- Live preview of final robots.txt output

## What SEO AI Currently Has

- Dynamic robots.txt generation via `robots_txt` filter
- Sitemap URL auto-injection
- Default disallow rules for wp-admin
- Public/private site detection
- Settings field for custom rules (plain text)

## Implementation Plan

1. **Structured Editor** — Replace plain text with a structured form:
   - Add user-agent sections (Googlebot, Bingbot, *)
   - Per-section Allow/Disallow rules
   - Add/remove rules with buttons
2. **Preview** — Show rendered robots.txt output below editor
3. **Validation** — Warn about common mistakes (blocking CSS/JS, blocking entire site)
4. **Crawl-delay Support** — Optional crawl-delay directive per user-agent

## Files to Modify

- `includes/modules/robots/class-robots-txt.php` — Parse structured rules into robots.txt output
- `includes/admin/views/settings/tab-advanced.php` — Replace textarea with structured editor
- `assets/js/settings.js` — Add/remove rule rows, live preview

## Notes

- The current plain text field works fine for most users
- Structured editor mainly prevents syntax errors
- Low priority — most sites never need to touch robots.txt beyond defaults
- Consider CodeMirror for syntax highlighting if keeping textarea approach
