# 11 — Advanced Image SEO

**Priority:** MEDIUM
**Effort:** S (1-2 files, < 1 day)
**Rank Math module:** `image-seo` (Pro)

## What Rank Math Has

### Caption & Description Templates
- Auto-generate image captions from templates (like alt text)
- Auto-generate image descriptions from templates
- Variable support: `%filename%`, `%title%`, `%alt%`, `%site_name%`
- Image block caption support in Gutenberg

### Case Conversion
- Alt text case: title case, sentence case, uppercase, lowercase
- Caption case: same options
- Description case: same options

### Find & Replace in Image Attributes
- Search and replace strings in alt text across all posts
- Search and replace in image titles
- Search and replace in captions
- Multiple replacement rules

### Avatar Alt Text
- Auto-add alt text to Gravatar/avatar images
- Format: "Avatar of {username}"

### Image Variable System
- `%imagealt%` and `%imagetitle%` variables for use in templates
- Filename extraction and formatting

## What SEO AI Currently Has

- Auto alt text from filename (dash/underscore → spaces)
- Customizable alt text template (`%filename%`)
- Auto image title attribute (toggle)
- `the_content` filter integration

## Implementation Plan

1. **Caption Template** — Add `image_caption_template` setting, apply via `the_content` filter
2. **Description Template** — Add `image_description_template` for attachment descriptions
3. **Case Conversion** — Add dropdown per attribute: none/title case/sentence case/uppercase/lowercase
4. **Find & Replace** — Admin tool page: input find/replace pairs, run across all post content (batch operation)
5. **Avatar Alt Text** — Hook into `get_avatar` filter to add alt text

## Files to Modify

- `includes/modules/image-seo/class-image-seo.php` — Add caption, case, avatar logic
- `includes/admin/views/settings/tab-advanced.php` — Add new Image SEO settings

## Notes

- Caption template and case conversion are quick wins (< 1 day)
- Find & Replace is a batch operation — needs confirmation dialog and progress tracking
- Avatar alt text is a tiny but nice accessibility improvement
- These features enhance existing Image SEO module without major architecture changes
