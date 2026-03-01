# 21 — Gutenberg Inline AI Writing Assistant

**Priority:** HIGH
**Effort:** L (5-10 files, 3-7 days)
**Rank Math feature:** Content AI inline commands (Pro/Business)

## What Rank Math Has

Rank Math injects AI-powered writing commands directly into the Gutenberg block editor toolbar. When a user selects text or clicks in a block, they get a dropdown with AI actions:

- **Escribe mas / Write More** — Expand selected text with additional content
- **Mejorar / Improve** — Rewrite selected text to be clearer and better
- **Resumir / Summarize** — Condense selected text into a shorter version
- **Escribir analogia / Write Analogy** — Generate an analogy for the selected content
- **Corregir gramatica / Fix Grammar** — Fix grammar and spelling issues
- **Ejecutar como comando / Execute as Command** — Free-form AI prompt on selected text

This runs inline — the result replaces or supplements the selected text directly in the editor, without leaving the writing flow.

## What SEO AI Currently Has

- AI buttons in the metabox sidebar: "Generate Title", "Generate Description", "Suggest Keyword"
- "Fix with AI" buttons per failing SEO check
- "Optimize All with AI" bulk button
- All AI output goes to metabox fields, NOT into the post content body

**Critical gap:** SEO AI has zero AI integration inside the actual content editor. All AI features operate on metadata (title, description, keyword), not on the post body itself.

## Why This Matters

- Users spend 90% of their time writing content, not filling metabox fields
- Inline AI tools help users write BETTER content (which improves SEO naturally)
- This is the most visible differentiator between "basic SEO plugin" and "AI-powered SEO plugin"
- SEO AI already has 5 AI providers configured — this leverages that investment

## Implementation Plan

### Phase 1 — Block Toolbar AI Menu
1. Register a Gutenberg format type or block toolbar extension
2. Add an "AI" button to the block toolbar (or rich text toolbar)
3. On click, show dropdown with AI actions
4. Selected text is sent to the AI provider with an action-specific prompt
5. AI response replaces/appends to the selected text

### Phase 2 — Core Actions
1. **Write More** — Prompt: "Continue writing from this text, maintaining the same style and tone: {selected_text}"
2. **Improve** — Prompt: "Rewrite this text to be clearer, more engaging, and better written: {selected_text}"
3. **Summarize** — Prompt: "Summarize this text in 1-2 concise sentences: {selected_text}"
4. **Fix Grammar** — Prompt: "Fix all grammar, spelling, and punctuation errors in this text. Return only the corrected text: {selected_text}"
5. **Simplify** — Prompt: "Rewrite this text to be simpler and easier to understand: {selected_text}"

### Phase 3 — SEO-Specific Actions
1. **Add Keywords** — Prompt: "Rewrite this text to naturally include the focus keyword '{keyword}': {selected_text}"
2. **Make Scannable** — Prompt: "Break this text into shorter paragraphs with subheadings for better readability: {selected_text}"
3. **Add Internal Links** — Suggest where to add links (from ticket #13)

### Phase 4 — Slash Command
1. Type `/ai` in a new block to trigger AI prompt input
2. Free-form: "Write an introduction about {topic}"
3. AI generates content directly into the block

## Technical Approach

### Option A: Block Toolbar Button (Recommended)
```javascript
// Register a format type for the rich text toolbar
wp.richText.registerFormatType('seo-ai/inline-ai', {
    title: 'AI Assistant',
    tagName: 'span',
    className: null,
    edit: AIToolbarButton,  // React component with dropdown
});
```

### Option B: SlotFill in Block Toolbar
```javascript
// Use BlockControls slot
const AIBlockControls = () => (
    <BlockControls>
        <ToolbarGroup>
            <ToolbarDropdownMenu icon={aiIcon} label="AI Writing">
                {/* Action items */}
            </ToolbarDropdownMenu>
        </ToolbarGroup>
    </BlockControls>
);
```

### REST Endpoint
```
POST /seo-ai/v1/ai/inline
{
    "action": "improve",        // write_more, improve, summarize, fix_grammar, simplify, custom
    "text": "selected text...",
    "context": "surrounding paragraph text...",
    "keyword": "focus keyword",
    "custom_prompt": ""         // for free-form commands
}
```

## Files to Create

- `assets/js/editor-ai.js` — Gutenberg editor integration (React component)
- `includes/rest/class-inline-ai-controller.php` — REST endpoint for inline AI actions

## Files to Modify

- `includes/admin/class-admin.php` — Enqueue editor-ai.js on block editor
- `includes/modules/content-analysis/class-ai-optimizer.php` — Add inline action methods

## Dependencies

- WordPress 6.4+ (block editor APIs)
- `@wordpress/block-editor`, `@wordpress/rich-text`, `@wordpress/components` (WP bundled)
- No build step needed if using `wp.element.createElement` instead of JSX (matches project conventions)
- OR: Add a minimal build step for this file only (esbuild/wp-scripts)

## UX Considerations

- Show a loading indicator while AI processes (inline spinner or skeleton text)
- Allow undo (Ctrl+Z) to revert AI changes
- Don't auto-replace — show a preview/diff first, let user accept or reject
- Keep the dropdown simple (5-6 items max)
- Consider a floating action button that appears on text selection

## Notes

- This is arguably the HIGHEST impact feature for differentiating SEO AI
- SEO AI's multi-provider advantage means users can choose fast/cheap models for inline tasks
- Rank Math gates this behind their paid Content AI credits system — SEO AI can offer it using the user's own API keys (unlimited, no credit system)
- Consider using lower max_tokens for inline actions (100-300) to keep responses fast
- The Gutenberg integration requires React/JSX — this would be the first React file in the project. Could use `wp.element.createElement` to avoid a build step.
