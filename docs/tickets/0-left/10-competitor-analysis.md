# 10 — Competitor Analysis

**Priority:** LOW
**Effort:** L (5-10 files, 3-7 days)
**Rank Math module:** `seo-analysis` (Pro enhancement)

## What Rank Math Has

- Input competitor URL for analysis
- Fetch and parse competitor page content
- Compare SEO metrics: title length, description length, content length, keyword usage
- Actionable recommendations based on comparison
- Product-specific analysis (WooCommerce/EDD)

## What SEO AI Currently Has

- Content analysis for own posts (14 checks + readability)
- No external URL analysis
- No competitor comparison

## Implementation Plan

### Phase 1 — Single URL Analysis
1. Input field for competitor URL in metabox or dedicated page
2. Fetch URL content (wp_remote_get)
3. Parse: title tag, meta description, H1-H6 structure, content length, keyword density, internal/external links count, image count + alt text coverage
4. Display results alongside own post's analysis

### Phase 2 — Comparison View
1. Side-by-side view: your post vs competitor
2. Highlight where competitor scores better
3. AI-generated suggestions to beat competitor

### Phase 3 — Multi-Competitor
1. Save competitor URLs per post
2. Track competitor changes over time
3. Keyword gap analysis (keywords they target that you don't)

## Files to Create

- `includes/modules/content-analysis/class-competitor-analyzer.php` — Fetch + parse external URL
- `includes/rest/class-competitor-controller.php` — REST endpoint for competitor analysis

## Files to Modify

- `assets/js/metabox.js` — Competitor input field + results display

## Notes

- External URL fetching may be blocked by some hosts (timeout, CORS)
- Rate limit competitor fetches to avoid abuse
- Cache competitor analysis results (transient, 24h)
- This is a "nice to have" — most small site owners don't do competitor analysis
- AI integration could make this uniquely valuable: "AI, how can I beat this competitor?"
