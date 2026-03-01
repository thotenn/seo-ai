# 03 — Video Sitemap

**Priority:** MEDIUM
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `video-sitemap` (Pro, requires Sitemap + Schema)

## What Rank Math Has

- **Video Sitemap Generation** — XML sitemap specifically for video content
- **Auto Video Detection** — Scans post content for embedded videos from:
  - YouTube
  - Vimeo
  - DailyMotion
  - TED
  - VideoPress
  - WordPress native video
- **Video Metadata Extraction** — Duration, thumbnail, description, upload date
- **XSL Stylesheet** — Human-readable display of video sitemap
- **Video Metabox** — Per-post video metadata fields (custom URL, duration)
- **Settings Panel** — Include/exclude post types, video priority
- **Sitemap Index Integration** — Video sitemap listed in main sitemap index

## What SEO AI Currently Has

- XML sitemap with post types, taxonomies, and image support
- No video detection or video sitemap

## Implementation Plan

1. **Video Detector Class** — Parse post content for known video embeds (YouTube, Vimeo oEmbed patterns, iframe src, `[video]` shortcode)
2. **Video Metadata Fetcher** — Use YouTube Data API / Vimeo API (or oEmbed) to get title, description, duration, thumbnail
3. **Video Sitemap Generator** — Generate `video-sitemap.xml` following Google's video sitemap spec
4. **Sitemap Index Update** — Add video sitemap to the main sitemap index
5. **Settings** — Toggle video sitemap on/off, select post types to scan

## Files to Create

- `includes/modules/sitemap/class-video-sitemap.php` — Video sitemap generation
- `includes/modules/sitemap/class-video-detector.php` — Video embed detection + metadata extraction

## Files to Modify

- `includes/modules/sitemap/class-sitemap-manager.php` — Add video sitemap to index
- `includes/admin/views/settings/tab-sitemap.php` — Video sitemap toggle

## Video Detection Patterns

```php
// YouTube
'/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/'

// Vimeo
'/vimeo\.com\/(?:video\/)?(\d+)/'

// WordPress [video] shortcode
'/\[video\s+.*?src=["\']([^"\']+)["\']/'

// HTML5 <video> tag
'/<video\s+.*?src=["\']([^"\']+)["\']/'
```

## Notes

- Video sitemaps help Google discover and index video content faster
- YouTube oEmbed endpoint is free and doesn't require an API key
- Cache video metadata in post meta to avoid repeated API calls
- Consider a cron job to periodically re-scan posts for new videos
