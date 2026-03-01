# 22 — Content AI Research & Writing Panel

**Priority:** MEDIUM
**Effort:** L (5-10 files, 3-7 days)
**Rank Math feature:** Content AI sidebar panel (Free basic + Pro/Business full)

## What Rank Math Has

A dedicated sidebar panel in the editor (separate from the main Rank Math SEO panel) with 4 tabs:

### Tab 1: Research (Investigacion)
- **Keyword Research** — Enter a keyword, get related keywords with:
  - Search volume
  - Competition level
  - CPC data
  - Country selector (80+ countries)
- **Related Questions** — "People also ask" style questions for the keyword
- **Content Metrics Targets** — AI-recommended targets for:
  - Word count (e.g., "Use 1829 words")
  - Heading count (e.g., "Use 9 headings")
  - Link count (e.g., "Use 16-24 links")
  - Media count (e.g., "Use 16-27 media")
  - Keyword density target
- **Competitor Content Metrics** — What top-ranking pages are doing

### Tab 2: Write (Escribir)
- AI writing templates (40+ tools):
  - Blog post intro
  - Blog post outline
  - Blog post conclusion
  - Paragraph writer
  - Product description
  - FAQ generator
  - And many more...
- Each template has input fields and generates content

### Tab 3: Content AI Tools
- SEO Meta generation (title + description)
- FAQ schema generation from content
- Article generation (1-click full article)

### Tab 4: Chat
- Free-form AI chat for content questions
- Context-aware (knows about the current post)

## What SEO AI Currently Has

- Metabox with SEO analysis, AI generate buttons for meta fields
- No research panel
- No keyword research
- No content metrics targets
- No AI writing templates
- No AI chat in editor

## Implementation Plan

### Phase 1 — Content Metrics Panel (MVP)
Add a new tab to the existing SEO AI metabox (or a separate sidebar panel):

1. **AI Content Brief** — When user enters a focus keyword, AI analyzes top competitors and suggests:
   - Recommended word count range
   - Recommended heading count
   - Recommended internal/external link count
   - Key topics to cover
2. Display current post metrics vs recommendations
3. Color-coded indicators (green = met, yellow = close, red = far off)

### Phase 2 — Related Keywords
1. Use AI to suggest related keywords/LSI terms for the focus keyword
2. Display as tags that the user should try to include
3. Check which related keywords already appear in content
4. No external API needed — AI providers can generate these

### Phase 3 — AI Writing Templates
1. Add a "Write with AI" panel or modal
2. Templates: Blog intro, outline, conclusion, FAQ, paragraph
3. Each template: input fields → AI generates → insert into editor
4. Uses existing AI provider infrastructure

### Phase 4 — AI Chat
1. Context-aware chat panel in editor sidebar
2. Knows current post title, content, keyword
3. User can ask questions like "How can I improve this paragraph?"
4. Chat history persists during editing session

## AI Prompts for Content Brief

```
Analyze the focus keyword "{keyword}" and provide content recommendations:
1. Recommended article length (word count range)
2. Recommended number of H2/H3 headings
3. Key subtopics that should be covered
4. Recommended number of internal and external links
5. 5-10 related keywords/LSI terms to include

Format as JSON:
{
  "word_count": { "min": 1500, "max": 2500 },
  "heading_count": { "min": 6, "max": 10 },
  "subtopics": ["topic1", "topic2", ...],
  "link_count": { "internal": 5, "external": 3 },
  "related_keywords": ["kw1", "kw2", ...]
}
```

## Files to Create

- `assets/js/content-panel.js` — Content research panel UI
- `assets/css/content-panel.css` — Panel styling
- `includes/rest/class-content-brief-controller.php` — REST endpoint for content brief generation

## Files to Modify

- `includes/admin/class-admin.php` — Enqueue new assets on editor
- `includes/modules/content-analysis/class-ai-optimizer.php` — Add content brief method

## Notes

- This is where SEO AI's 5-provider advantage really shines — Rank Math charges credits for Content AI; SEO AI uses the user's own API keys
- Phase 1 (Content Brief) is the highest value — it tells users what to aim for
- Related keywords via AI are surprisingly good and don't need external keyword APIs
- The chat feature (Phase 4) can be deferred — it's nice but not essential
- Consider this as a Gutenberg sidebar plugin (PluginSidebar) to keep the metabox clean
