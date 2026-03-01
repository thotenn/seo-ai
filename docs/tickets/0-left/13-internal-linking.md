# 13 — Internal Link Suggestions

**Priority:** HIGH
**Effort:** L (5-10 files, 3-7 days)
**Rank Math module:** `links` (Free basic analysis + Pro suggestions)

## What Rank Math Has

- Link analysis and counting (internal + external)
- Broken link detection
- Link quality assessment
- Internal link suggestions based on content similarity

## What SEO AI Currently Has

- Content analysis counts internal and external links
- Checks pass/fail based on minimum link counts
- No link suggestions
- No broken link detection

## Implementation Plan

### Phase 1 — AI-Powered Link Suggestions
1. When analyzing a post, use AI to identify related posts that should be linked
2. Compare post keywords/topics against other published posts
3. Suggest specific anchor text + target post combinations
4. Display suggestions in metabox "SEO" tab or dedicated section

### Phase 2 — Link Index
1. Build an index of all internal links across the site
2. Store in custom table: source_post_id, target_post_id, anchor_text, link_url
3. Update index on post save
4. Show "Posts linking to this" and "Posts linked from this" in metabox

### Phase 3 — Orphan Content Detection
1. Find posts with zero internal links pointing to them
2. Dashboard widget or report showing orphan posts
3. AI suggestions for which posts should link to orphans

### Phase 4 — Broken Link Detection
1. Periodic scan of all links in content
2. Check HTTP status codes (404, 500, timeout)
3. Report broken links with fix suggestions
4. Cron-based background scanning

## AI Prompt for Link Suggestions

```
Given this post's content and keyword "{keyword}", suggest 3-5 internal links
from these published posts on the same site:
{list of post titles + URLs}

For each suggestion, provide:
- Target post title and URL
- Suggested anchor text
- Where in the content to place the link (quote the surrounding sentence)
```

## Files to Create

- `includes/modules/content-analysis/class-link-suggestions.php` — AI link suggestions
- `includes/modules/content-analysis/class-link-index.php` — Link indexing + orphan detection

## Files to Modify

- `includes/modules/content-analysis/class-analyzer.php` — Add link suggestion check
- `assets/js/metabox.js` — Display link suggestions in UI

## DB Table

```sql
CREATE TABLE {prefix}seo_ai_link_index (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_post_id BIGINT UNSIGNED NOT NULL,
  target_post_id BIGINT UNSIGNED DEFAULT NULL,
  target_url VARCHAR(2048) NOT NULL,
  anchor_text VARCHAR(500) DEFAULT '',
  is_external TINYINT(1) DEFAULT 0,
  status_code SMALLINT DEFAULT NULL,
  last_checked DATETIME DEFAULT NULL,
  INDEX idx_source (source_post_id),
  INDEX idx_target (target_post_id),
  INDEX idx_status (status_code)
);
```

## Notes

- This is a HIGH value feature that leverages SEO AI's unique AI advantage
- No other SEO plugin offers AI-powered internal link suggestions
- The link index can also power "related posts" features
- Broken link detection requires background processing (cron)
- Start with Phase 1 (AI suggestions) — it's the biggest differentiator
