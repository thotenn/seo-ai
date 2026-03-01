# 23 — Cornerstone / Pillar Content

**Priority:** LOW
**Effort:** S (1-2 files, < 1 day)
**Rank Math feature:** "Esta entrada es un contenido esencial" checkbox

## What Rank Math Has

- **Cornerstone Content Checkbox** — Per-post toggle: "This post is cornerstone content"
- Cornerstone posts get:
  - Stricter SEO analysis thresholds (higher word count, more links required)
  - Priority in internal link suggestions
  - Visual indicator in post list
  - Higher crawl priority (optional)

## What SEO AI Currently Has

- No concept of cornerstone/pillar content
- Same SEO analysis thresholds for all posts

## Implementation Plan

1. **Post Meta Field** — Add `_seo_ai_cornerstone` boolean post meta
2. **Metabox Checkbox** — Add checkbox in SEO tab: "This is cornerstone content"
3. **Adjusted Analysis** — When cornerstone is true, increase thresholds:
   - Minimum word count: 300 → 900
   - Minimum internal links: 1 → 3
   - Minimum external links: 1 → 2
   - Keyword density stricter range
4. **Post List Indicator** — Show a star/pin icon in the SEO score column for cornerstone posts
5. **Internal Link Priority** — When generating link suggestions (ticket #13), prioritize linking TO cornerstone content

## Files to Modify

- `includes/modules/content-analysis/class-analyzer.php` — Adjust thresholds for cornerstone
- `includes/admin/views/metabox/main.php` — Add cornerstone checkbox
- `includes/admin/class-columns.php` — Add cornerstone indicator
- `includes/helpers/class-post-meta.php` — Register new meta key

## Post Meta

- `_seo_ai_cornerstone` — Boolean (0/1)

## Notes

- Very simple feature, can be done in a few hours
- The real value comes when combined with internal link suggestions (ticket #13)
- Cornerstone content is a well-known SEO concept (popularized by Yoast)
- Could also filter cornerstone posts in the dashboard for priority optimization
