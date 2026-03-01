# 04 — News Sitemap

**Priority:** MEDIUM
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `news-sitemap` (Pro, requires Sitemap)

## What Rank Math Has

- **Google News Compliant Sitemap** — `news-sitemap.xml` following Google News specs
- **Per-Post Control** — Googlebot-News noindex meta tag per post
- **Publication Metadata** — Publication name, language, publication date
- **Post Age Filtering** — Only include posts from last 48 hours (Google News requirement)
- **Post Type Selection** — Choose which post types appear in news sitemap
- **Auto Schema Override** — News posts automatically get NewsArticle schema
- **REST API** — Endpoints for news sitemap management

## What SEO AI Currently Has

- Standard XML sitemap (post types, taxonomies, images)
- No news-specific sitemap

## Implementation Plan

1. **News Sitemap Generator** — Generate `news-sitemap.xml` per Google News spec
2. **48-Hour Filter** — Only include posts published within last 48 hours
3. **Per-Post Exclusion** — Post meta field to exclude from news sitemap
4. **Settings** — Select post types for news sitemap, publication name
5. **Schema Integration** — Auto-set NewsArticle schema for news posts

## Google News Sitemap Format

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
  <url>
    <loc>https://example.com/post-url</loc>
    <news:news>
      <news:publication>
        <news:name>Site Name</news:name>
        <news:language>en</news:language>
      </news:publication>
      <news:publication_date>2026-03-01</news:publication_date>
      <news:title>Post Title</news:title>
    </news:news>
  </url>
</urlset>
```

## Files to Create

- `includes/modules/sitemap/class-news-sitemap.php` — News sitemap generation

## Files to Modify

- `includes/modules/sitemap/class-sitemap-manager.php` — Add news sitemap to index + rewrite rules
- `includes/admin/views/settings/tab-sitemap.php` — News sitemap settings

## Notes

- News sitemaps are only relevant for news publishers; consider making this a separate toggleable sub-feature
- The 48-hour window is a Google requirement — older articles should NOT appear
- Publication name should default to the site name but be configurable
- This pairs well with the NewsArticle schema type (ticket #02)
