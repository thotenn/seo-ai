# 14 — Instant Indexing API

**Priority:** MEDIUM
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** `instant-indexing` (Free)

## What Rank Math Has

- **Google Indexing API** integration
- **Bing URL Submission API** integration
- Submit URLs for instant crawling when posts are published/updated
- Bulk URL submission
- API key / service account configuration
- Submission history log

## What SEO AI Currently Has

- Sitemap ping on publish (basic ping to search engines)
- No direct Indexing API integration

## Implementation Plan

1. **Google Indexing API Client** — Service account JSON key upload, OAuth2 token management
2. **Bing URL Submission** — API key configuration, submit endpoint
3. **Auto-Submit on Publish** — Hook into `transition_post_status` to submit URL
4. **Manual Submit** — Button in metabox to request indexing for current post
5. **Bulk Submit** — Admin tool to submit multiple URLs at once
6. **Submission Log** — Track submissions with status (success/failure/pending)

## Google Indexing API

```php
// Submit URL for indexing
POST https://indexing.googleapis.com/v3/urlNotifications:publish
{
  "url": "https://example.com/my-post",
  "type": "URL_UPDATED"  // or "URL_DELETED"
}
```

Requires: Google Cloud service account with Indexing API enabled.

## Bing URL Submission API

```php
// Submit URL
POST https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey={key}
{
  "siteUrl": "https://example.com",
  "url": "https://example.com/my-post"
}
```

## Files to Create

- `includes/modules/indexing/class-indexing.php` — Module entry + auto-submit hooks
- `includes/modules/indexing/class-google-indexing.php` — Google API client
- `includes/modules/indexing/class-bing-indexing.php` — Bing API client

## Files to Modify

- `includes/admin/views/settings/tab-advanced.php` — API key settings
- `assets/js/metabox.js` — "Request Indexing" button

## Notes

- Google Indexing API is officially meant for JobPosting and BroadcastEvent, but works for any URL
- Bing URL Submission is simpler (just API key, no OAuth)
- Daily quota: Google = 200 URLs/day, Bing = 10,000 URLs/day
- IndexNow is already in Rank Math Free — it's simpler and should be Phase 1
- IndexNow only requires a key file on the server, no OAuth or API keys
- Google Indexing API (Phase 2) requires service account setup but covers Google directly
- IndexNow endpoint: `https://api.indexnow.org/indexnow?url={url}&key={key}`
