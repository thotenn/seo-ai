# 01 — Google Search Console & Analytics Integration

**Priority:** HIGH
**Effort:** XL (10+ files, 2+ weeks)
**Rank Math module:** `analytics` (Pro)

## What Rank Math Has

### Google Search Console Integration
- OAuth2 connection to Google Search Console
- Import search performance data (clicks, impressions, CTR, position)
- Keyword position tracking over time
- URL inspection API (indexing status, crawl stats, mobile usability)
- Country-based analytics filtering
- Custom date range selection

### Google Analytics Integration
- Connect to GA4 via OAuth2
- Traffic by post/page
- Organic vs paid vs referral breakdown
- Pageviews tracking via frontend JS endpoint

### Google AdSense Integration
- Revenue data per page
- Earnings trends

### Keyword Tracking
- Track focus keyword rankings
- Search volume data
- CPC estimates
- Winning/losing content detection (ranking changes)

### Post Performance Analytics
- Per-post traffic metrics
- Click-through rate analysis
- Impressions and ranking trends
- Content performance scoring

### Email Reports
- Automated daily/weekly/monthly digest emails
- Console summary reports
- Customizable recipients

### Analytics Dashboard
- Dedicated dashboard with charts and trends
- Data retention configuration
- REST API endpoints for data access

## What SEO AI Currently Has

Nothing. No external analytics integration.

## Implementation Plan

### Phase 1 — Google Search Console (MVP)
1. **OAuth2 Authentication** — Google API client with token storage
2. **Search Performance API** — Fetch clicks, impressions, CTR, position
3. **Data Storage** — Custom DB table for caching API data
4. **Dashboard Widget** — Basic GSC stats on SEO AI dashboard
5. **Per-Post Metrics** — Show search performance in metabox

### Phase 2 — Keyword Tracking
1. **Focus Keyword Ranking** — Track position for each post's focus keyword
2. **Historical Data** — Store daily/weekly snapshots
3. **Winning/Losing Detection** — Flag posts with significant ranking changes

### Phase 3 — Full Analytics Dashboard
1. **Dedicated Analytics Page** — Admin submenu with charts
2. **Date Range Picker** — Custom period selection
3. **Country Filter** — Filter by country
4. **Email Reports** — Scheduled digest emails

### Phase 4 — Extended Integrations
1. **Google Analytics 4** — Traffic data (optional)
2. **URL Inspection API** — Indexing status checks

## Files to Create

- `includes/modules/analytics/class-analytics.php` — Module entry point
- `includes/modules/analytics/class-google-client.php` — OAuth2 + API client
- `includes/modules/analytics/class-data-store.php` — DB storage/caching
- `includes/modules/analytics/class-keywords.php` — Keyword tracking
- `includes/modules/analytics/class-posts.php` — Post performance
- `includes/modules/analytics/class-email-reports.php` — Digest emails
- `includes/rest/class-analytics-controller.php` — REST endpoints
- `includes/admin/views/analytics/main.php` — Analytics page
- `includes/admin/views/settings/tab-analytics.php` — Settings tab
- `assets/js/analytics.js` — Charts and interactions
- `assets/css/analytics.css` — Styling
- DB migration for analytics tables

## Dependencies

- Google API PHP Client (or custom OAuth2 implementation)
- Chart library (Chart.js or similar, loaded from CDN or bundled)

## Notes

- This is the single largest missing feature. Analytics is what keeps users in Rank Math.
- Consider starting with GSC-only (Phase 1) as an MVP — it provides the most value with least complexity.
- Google Analytics 4 integration adds complexity (different API, different auth scopes).
- Email reports can be deferred to a later phase.
