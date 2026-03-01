# SEO AI — Feature Gap Analysis vs Rank Math Pro

**Date:** 2026-03-01
**Rank Math Pro version analyzed:** 3.0.107
**SEO AI version:** 0.2.0

## Summary

This analysis compares Rank Math Pro (free + pro combined, 18+ modules) against SEO AI (9 modules + AI providers). The goal is to identify features Rank Math offers that SEO AI does not yet have, prioritized by value to users.

## Feature Gap Categories

| # | Ticket | Priority | Effort | Description |
|---|--------|----------|--------|-------------|
| 01 | [Google Search Console & Analytics](./01-analytics-search-console.md) | HIGH | XL | GSC integration, keyword tracking, traffic analytics, email reports |
| 02 | [Advanced Schema Types](./02-advanced-schema-types.md) | HIGH | L | Recipe, JobPosting, Movie, Dataset, ClaimReview, QAPage, Podcast Episode, custom schema templates |
| 03 | [Video Sitemap](./03-video-sitemap.md) | MEDIUM | M | Auto-detect videos in content, generate video XML sitemap |
| 04 | [News Sitemap](./04-news-sitemap.md) | MEDIUM | M | Google News compliant sitemap with per-post inclusion control |
| 05 | [Local SEO / Multi-Location](./05-local-seo.md) | MEDIUM | XL | LocalBusiness schema, multi-location CPT, KML export, location shortcode/block |
| 06 | [WooCommerce Integration](./06-woocommerce.md) | MEDIUM | L | Product variants (ProductGroup), GTIN fields, hidden product handling, stock filtering |
| 07 | [CSV Import/Export for SEO Data](./07-csv-import-export.md) | HIGH | M | Bulk import/export of post SEO metadata via CSV |
| 08 | [Quick Edit & Post List Filters](./08-quick-edit-filters.md) | HIGH | M | Inline SEO field editing + filter by robots/schema/canonical in post list |
| 09 | [Advanced Redirects](./09-advanced-redirects.md) | MEDIUM | M | .htaccess sync, redirect categories, scheduled redirects, query parameter matching |
| 10 | [Competitor Analysis](./10-competitor-analysis.md) | LOW | L | Analyze competitor URLs for SEO comparison |
| 11 | [Advanced Image SEO](./11-advanced-image-seo.md) | MEDIUM | S | Caption/description templates, case conversion, find & replace in attributes |
| 12 | [Podcast Support](./12-podcast.md) | LOW | M | Podcast RSS feed generation, PodcastEpisode schema |
| 13 | [Internal Link Suggestions](./13-internal-linking.md) | HIGH | L | AI-powered internal link suggestions based on content analysis |
| 14 | [Instant Indexing API](./14-instant-indexing.md) | MEDIUM | M | Google Indexing API integration for fast crawling |
| 15 | [Advanced Robots.txt Editor](./15-advanced-robots-txt.md) | LOW | S | Visual editor with per-user-agent rules, syntax validation |
| 16 | [ACF / Page Builder Integration](./16-acf-pagebuilder.md) | LOW | M | Extract SEO data from ACF fields, Elementor, Divi |
| 17 | [bbPress / Forum Integration](./17-bbpress.md) | LOW | S | QAPage schema for forum topics, mark reply as solved |
| 18 | [Bulk Actions Enhancement](./18-bulk-actions.md) | MEDIUM | S | Bulk set robots, remove canonicals, assign schema, determine search intent |
| 19 | [404 Monitor Export](./19-404-export.md) | LOW | S | CSV export of 404 logs with date range filtering |
| 20 | [Search Intent Detection](./20-search-intent.md) | MEDIUM | M | Auto-detect search intent (informational, transactional, navigational, commercial) |
| 21 | [Gutenberg Inline AI Writing](./21-gutenberg-ai-writing.md) | HIGH | L | Inline AI writing commands in block editor (Write More, Improve, Summarize, Fix Grammar) |
| 22 | [Content AI Research Panel](./22-content-ai-research-panel.md) | MEDIUM | L | Content brief, related keywords, content metrics targets, AI writing templates |
| 23 | [Cornerstone Content](./23-cornerstone-content.md) | LOW | S | Pillar content checkbox with stricter analysis thresholds |

## Priority Legend

- **HIGH** — Core SEO feature that most users expect; competitive disadvantage without it
- **MEDIUM** — Valuable feature for specific use cases; nice to have for general users
- **LOW** — Niche feature; only relevant for specific verticals or advanced users

## Effort Legend

- **S** (Small) — 1-2 files, < 1 day
- **M** (Medium) — 3-5 files, 1-3 days
- **L** (Large) — 5-10 files, 3-7 days
- **XL** (Extra Large) — 10+ files, 1-2 weeks+

## What SEO AI Already Has That Matches Rank Math

These features are already implemented (parity or close):

| Feature | SEO AI | Rank Math |
|---------|--------|-----------|
| Meta tags (title, description, robots, canonical) | Yes | Yes |
| Schema markup (Article, FAQ, HowTo, Product, WebPage) | Yes | Yes |
| XML Sitemap with image support | Yes | Yes |
| Redirects (301/302/307/410/451 + regex) | Yes | Yes |
| 404 Monitor with logging | Yes | Yes |
| Open Graph tags | Yes | Yes |
| Twitter Cards | Yes | Yes |
| Image SEO (auto alt from filename) | Yes | Yes |
| Breadcrumbs (shortcode + schema) | Yes | Yes |
| Robots.txt generation | Yes | Yes |
| Content analysis (14 checks) | Yes | Yes (similar) |
| Readability analysis | Yes | Yes |
| SEO score (0-100) | Yes | Yes |
| Post editor metabox (5 tabs) | Yes | Yes |
| Post list SEO score column | Yes | Yes |
| AI content generation | Yes (5 providers) | Yes (Content AI, limited) |
| Bulk optimization wizard | Yes | Yes (different approach) |
| Activity logging | Yes | No (basic) |
| Custom AI prompts per provider | Yes | No |
| Cost tracking per provider | Yes | No |
| Multi-AI-provider support (5) | Yes | No (1 built-in) |

## Additional Gaps Found (Minor / Niche)

These were identified from Rank Math Free (v1.0.264.1) and are not significant enough for individual tickets but worth noting:

| Feature | Rank Math | SEO AI | Notes |
|---------|-----------|--------|-------|
| **Hreflang / Multilingual** | Sitemap hreflang support | None | Relevant for multilingual sites (WPML/Polylang). Could be added to sitemap module. |
| **Site-Wide SEO Audit** | 28+ tests (tagline, permalinks, GSC, sitemap, focus keywords) | None | Different from per-post analysis; audits the whole site config. |
| **IndexNow Protocol** | Built-in (Bing, Yandex) | None | Simpler than Google Indexing API. See ticket #14. |
| **llms.txt** | Custom llms.txt file for AI crawlers | None | Emerging standard. Very small feature to add. |
| **Role Manager** | Granular per-role capability control | 3 fixed capabilities | SEO AI grants 3 caps to admin only. |
| **Link Counter** | Track to/from internal links per post | Count only (in analysis) | SEO AI counts links but doesn't track to/from relationships. See ticket #13. |
| **Import from Other SEO Plugins** | Yoast, AIOSEO, SEOPress migration | None | One-click migration tool. See ticket #07. |
| **BuddyPress Integration** | Forum SEO optimization | None | Very niche. |
| **Google Web Stories** | Auto SEO for Web Stories | None | Very niche. |
| **Related Keywords/Questions** | Content AI suggests related keywords + questions (80+ countries) | Focus keyword only | Could enhance AI optimizer to suggest related terms. |

## SEO AI Advantages Over Rank Math

Features SEO AI has that Rank Math does NOT:

1. **5 AI Providers** — OpenAI, Claude, Gemini, Ollama (free/local), OpenRouter. Rank Math only has its own Content AI (cloud-only, plan-limited).
2. **Custom AI Prompts** — Per-provider custom prompt configuration. Rank Math has no equivalent.
3. **Cost Tracking** — Per-provider input/output cost per 1M tokens. Rank Math has no cost visibility.
4. **Local AI (Ollama)** — Run AI completely offline/private. Rank Math requires cloud.
5. **Optimization Wizard** — 4-step modal wizard with real-time progress. More user-friendly than Rank Math's bulk approach.
6. **Activity Log** — Full operation logging with filters, search, pagination. Rank Math has no comparable feature.
