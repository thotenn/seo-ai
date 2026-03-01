# 20 — Search Intent Detection

**Priority:** MEDIUM
**Effort:** M (3-5 files, 1-3 days)
**Rank Math module:** Common feature (Pro)

## What Rank Math Has

- **Auto-Detect Search Intent** — Analyze content and determine intent type
  - Informational (how-to, guide, tutorial, what is)
  - Transactional (buy, purchase, price, deal, discount)
  - Navigational (brand name, login, specific site)
  - Commercial Investigation (best, review, comparison, top)
- **Per-Post Intent Label** — Displayed in post list and metabox
- **Filter by Intent** — Post list filter by detected intent
- **Focus Keyword Limit Increase** — Up to 100 focus keywords (vs 5 in free)

## What SEO AI Currently Has

- Focus keyword analysis (single keyword per post)
- Content analysis checks keyword placement
- No search intent classification
- No multiple focus keywords

## Implementation Plan

### Phase 1 — AI-Powered Intent Detection
1. Add search intent detection to AI Optimizer
2. AI prompt: analyze content + focus keyword → classify intent
3. Store intent as post meta (`_seo_ai_search_intent`)
4. Display intent badge in metabox and post list column

### Phase 2 — Intent-Based Optimization
1. Adjust SEO suggestions based on detected intent
   - Informational → suggest FAQ schema, longer content, comprehensive coverage
   - Transactional → suggest Product schema, CTA presence, pricing info
   - Commercial → suggest comparison structure, pros/cons, review schema
2. Add intent-specific content analysis checks

### Phase 3 — Multiple Focus Keywords
1. Allow up to 5 focus keywords per post
2. Analyze each keyword independently
3. Show per-keyword scores in metabox
4. Primary + secondary keyword concept

## AI Prompt for Intent Detection

```
Analyze the following content and its focus keyword "{keyword}".
Classify the search intent into exactly ONE of these categories:
- informational (user wants to learn something)
- transactional (user wants to buy/do something)
- navigational (user looking for specific page/brand)
- commercial (user comparing options before buying)

Return ONLY the category name, nothing else.
```

## Files to Create

- `includes/modules/content-analysis/class-search-intent.php` — Intent detection + scoring adjustments

## Files to Modify

- `includes/modules/content-analysis/class-ai-optimizer.php` — Add `detect_search_intent()` method
- `includes/admin/class-columns.php` — Intent column in post list
- `assets/js/metabox.js` — Display intent badge

## Post Meta

- `_seo_ai_search_intent` — String: informational/transactional/navigational/commercial

## Notes

- This is a natural fit for SEO AI's multi-provider AI advantage
- Intent detection helps users optimize content for the RIGHT type of search
- Combined with AI suggestions, this becomes a unique differentiator
- Multiple focus keywords (Phase 3) is a commonly requested feature
