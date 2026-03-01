# 17 — bbPress / Forum Integration

**Priority:** LOW
**Effort:** S (1-2 files, < 1 day)
**Rank Math module:** `bbPress` (Pro)

## What Rank Math Has

- **QAPage Schema** — Question/answer structured data for forum topics
  - Question from topic post
  - Accepted answer schema
  - Answer count
  - Publication dates
- **Mark Reply as Solved** — Frontend button for moderators to mark a reply as the accepted answer
- **Reply status tracking** in database

## What SEO AI Currently Has

Nothing bbPress-specific.

## Implementation Plan

1. Detect bbPress active
2. Auto-apply QAPage schema to forum topics
3. Extract question from topic content, answers from replies
4. Add "Mark as Solved" button for moderators (optional)

## Files to Create

- `includes/integrations/class-bbpress.php` — QAPage schema + solved reply support

## Notes

- Very niche — only relevant for sites running bbPress forums
- QAPage schema can generate rich results in Google (star ratings, answer count)
- Low effort to implement if just doing schema output (no UI needed)
- The "Mark as Solved" feature requires a post meta field on replies
