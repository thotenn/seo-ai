# 12 — Podcast Support

**Priority:** LOW
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `podcast` (Pro, requires Schema)

## What Rank Math Has

- **Podcast RSS Feed** — Generate podcast-compatible RSS feed
  - Custom feed slug (`/feed/podcast/`)
  - iTunes-compatible metadata
  - Episode enclosure tags
- **Podcast Episode Schema** — PodcastEpisode JSON-LD
  - Episode name, URL, duration, series
- **Podcast Settings** — Channel image, title, description, feed configuration
- **Variable Replacements** — `%podcast_image%` variable

## What SEO AI Currently Has

Nothing podcast-related.

## Implementation Plan

1. **PodcastEpisode Schema** — Add to schema manager (part of ticket #02)
2. **Podcast RSS Feed** — Register custom feed, output iTunes-compatible XML
3. **Podcast Metabox Fields** — Audio URL, duration, episode number, season
4. **Podcast Settings** — Channel name, image, description, category, language

## Files to Create

- `includes/modules/podcast/class-podcast.php` — Module entry point + RSS feed
- `includes/modules/podcast/class-podcast-schema.php` — PodcastEpisode schema

## Notes

- Niche feature — only relevant for sites that publish podcasts
- Could be implemented as a separate toggleable module
- RSS feed must comply with Apple Podcasts / Spotify specs
- Consider this a low priority unless targeting podcaster audience
