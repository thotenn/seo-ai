# AI Provider System

## Overview

The AI provider system follows the pattern established by the Flavor Translator plugin. It provides a unified interface for multiple AI providers, with a visual settings panel for configuration, connection testing, and model selection.

## Provider Interface

```php
interface Provider_Interface {
    public function get_id(): string;            // e.g., 'openai'
    public function get_name(): string;           // e.g., 'OpenAI (GPT)'
    public function get_models(): array;          // Available models
    public function get_default_model(): string;  // Default model ID
    public function get_default_base_url(): string;
    public function is_configured(): bool;        // Has required settings
    public function test_connection(): array;      // {success, message}
    public function chat(string $system_prompt, string $user_prompt, array $options = []): string;
}
```

## Supported Providers

### 1. OpenAI (GPT)
- **Settings:** API Key, Base URL, Model, Temperature
- **Models:** gpt-4o-mini, gpt-4o, gpt-4.1, gpt-4.1-mini
- **Default Base URL:** `https://api.openai.com`
- **API Endpoint:** `/v1/chat/completions`
- **Auth:** Bearer token

### 2. Anthropic (Claude)
- **Settings:** API Key, Base URL, Model, Temperature, Max Tokens
- **Models:** claude-sonnet-4-5-20250929, claude-haiku-4-5-20251001, claude-opus-4-6
- **Default Base URL:** `https://api.anthropic.com`
- **API Endpoint:** `/v1/messages`
- **Auth:** x-api-key header + anthropic-version header

### 3. Google Gemini
- **Settings:** API Key, Model, Temperature
- **Models:** gemini-2.0-flash, gemini-2.5-pro
- **Default Base URL:** `https://generativelanguage.googleapis.com`
- **API Endpoint:** `/v1beta/models/{model}:generateContent`
- **Auth:** API key as query parameter

### 4. Ollama (Local)
- **Settings:** Base URL (no API key needed), Model
- **Models:** Auto-detected from Ollama API (`/api/tags`)
- **Default Base URL:** `http://localhost:11434`
- **API Endpoint:** `/api/chat`
- **Auth:** None (local)
- **Special:** Model list fetched dynamically from local Ollama instance

### 5. OpenRouter
- **Settings:** API Key, Model, Temperature
- **Models:** Dynamic list from OpenRouter API
- **Default Base URL:** `https://openrouter.ai`
- **API Endpoint:** `/api/v1/chat/completions`
- **Auth:** Bearer token + HTTP-Referer header

## Provider Manager

```php
class Provider_Manager {
    // Registry
    public function register(Provider_Interface $provider): void;
    public function get_providers(): array;
    public function get_provider(string $id): ?Provider_Interface;

    // Active provider
    public function get_active_provider(): ?Provider_Interface;
    public function set_active_provider(string $id): void;

    // Factory method
    public function create_provider(string $id): Provider_Interface;

    // Test connection
    public function test_provider(string $id, array $settings = []): array;
}
```

## Settings Storage

Provider settings are stored in a single WordPress option `seo_ai_providers`:

```php
[
    'active_provider' => 'ollama',
    'openai' => [
        'api_key'    => 'sk-...',
        'base_url'   => 'https://api.openai.com',
        'model'      => 'gpt-4o-mini',
        'temperature' => 0.3,
    ],
    'claude' => [
        'api_key'    => 'sk-ant-...',
        'base_url'   => 'https://api.anthropic.com',
        'model'      => 'claude-sonnet-4-5-20250929',
        'temperature' => 0.3,
        'max_tokens'  => 4096,
    ],
    'gemini' => [
        'api_key'    => 'AIza...',
        'model'      => 'gemini-2.0-flash',
        'temperature' => 0.3,
    ],
    'ollama' => [
        'base_url'   => 'http://localhost:11434',
        'model'      => 'llama3.2',
        'temperature' => 0.3,
    ],
    'openrouter' => [
        'api_key'    => 'sk-or-...',
        'model'      => 'anthropic/claude-sonnet-4-5-20250929',
        'temperature' => 0.3,
    ],
]
```

## Admin UI Pattern (from Flavor Translator)

### Provider Selection Cards
- Visual cards for each provider (logo, name, status badge)
- Click to select active provider
- Green badge = configured, Red badge = not configured
- Active provider highlighted with border

### Provider Settings Panels
- Only show settings for selected provider
- Fields: API Key (password input), Base URL (with reset button), Model (dropdown), Temperature (range slider)
- "Test Connection" button per provider with result feedback
- Ollama: "Fetch Models" button to load available models

### Save Flow
1. User selects provider card
2. Enters/updates settings
3. Tests connection (optional but recommended)
4. Clicks "Save Settings"
5. AJAX POST to REST API
6. Toast notification on success/failure

## SEO-Specific Prompts

### Prompt Templates

```
Meta Title Generation:
"Generate an SEO-optimized meta title for the following content.
The title should be 50-60 characters, include the focus keyword '{keyword}'
naturally, and be compelling for search results. Return ONLY the title."

Meta Description Generation:
"Generate an SEO-optimized meta description for the following content.
The description should be 120-160 characters, include the focus keyword '{keyword}',
and encourage clicks. Return ONLY the description."

Content Optimization:
"Analyze this content for SEO. The focus keyword is '{keyword}'.
Provide specific, actionable suggestions to improve the SEO score.
Consider: keyword placement, content structure, readability, and internal linking."

Schema Detection:
"Based on this content, determine the most appropriate Schema.org type
(Article, HowTo, FAQPage, Product, Recipe, Event, etc.) and return only
the schema type name."

Alt Text Generation:
"Generate a descriptive, SEO-friendly alt text for an image based on
the filename '{filename}' and the surrounding content context.
The alt text should be 125 characters max and descriptive."
```

## Error Handling

- Network timeouts: 120s default, configurable per provider
- Rate limiting: Exponential backoff on 429 responses
- Error display: Admin notices with clear error messages
- Fallback: If active provider fails, optionally try next configured provider
- Logging: All AI requests/responses logged (debug mode only)
