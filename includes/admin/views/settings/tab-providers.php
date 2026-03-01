<?php defined('ABSPATH') || exit;
$providers_settings = get_option('seo_ai_providers', []);
$active_provider = $providers_settings['active_provider'] ?? 'ollama';

$providers = [
    'openai' => [
        'name'  => 'OpenAI (GPT)',
        'icon'  => '🤖',
        'desc'  => 'GPT-4o, GPT-4.1 models',
        'needs_key' => true,
        'configured' => !empty($providers_settings['openai']['api_key'] ?? ''),
    ],
    'claude' => [
        'name'  => 'Anthropic (Claude)',
        'icon'  => '🧠',
        'desc'  => 'Claude Sonnet, Haiku, Opus',
        'needs_key' => true,
        'configured' => !empty($providers_settings['claude']['api_key'] ?? ''),
    ],
    'gemini' => [
        'name'  => 'Google (Gemini)',
        'icon'  => '💎',
        'desc'  => 'Gemini Flash, Pro',
        'needs_key' => true,
        'configured' => !empty($providers_settings['gemini']['api_key'] ?? ''),
    ],
    'ollama' => [
        'name'  => 'Ollama (Local)',
        'icon'  => '🦙',
        'desc'  => 'Free, runs locally',
        'needs_key' => false,
        'configured' => true,
    ],
    'openrouter' => [
        'name'  => 'OpenRouter',
        'icon'  => '🔀',
        'desc'  => 'Multi-model gateway',
        'needs_key' => true,
        'configured' => !empty($providers_settings['openrouter']['api_key'] ?? ''),
    ],
];
?>

<div class="seo-ai-card">
    <h2>AI Provider Configuration</h2>
    <p class="description">Select and configure your AI provider. <strong>Ollama</strong> is recommended for free, local usage with no API key required.</p>

    <input type="hidden" name="seo_ai_providers[active_provider]" id="seo_ai_active_provider" value="<?php echo esc_attr($active_provider); ?>" />

    <!-- Provider Selection Cards -->
    <div class="seo-ai-provider-cards">
        <?php foreach ($providers as $id => $p): ?>
        <div class="seo-ai-provider-card <?php echo $active_provider === $id ? 'active' : ''; ?>"
             data-provider="<?php echo esc_attr($id); ?>">
            <div class="seo-ai-provider-icon"><?php echo $p['icon']; ?></div>
            <div class="seo-ai-provider-info">
                <strong><?php echo esc_html($p['name']); ?></strong>
                <span class="seo-ai-provider-desc"><?php echo esc_html($p['desc']); ?></span>
            </div>
            <span class="seo-ai-badge <?php echo $p['configured'] ? 'seo-ai-badge-success' : 'seo-ai-badge-muted'; ?>">
                <?php echo $p['configured'] ? 'Configured' : 'Not Configured'; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- OpenAI Settings -->
<div class="seo-ai-card seo-ai-provider-settings" id="seo-ai-provider-openai" <?php echo $active_provider !== 'openai' ? 'style="display:none"' : ''; ?>>
    <h3>OpenAI Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_openai_key">API Key</label></th>
            <td>
                <input type="password" name="seo_ai_providers[openai][api_key]" id="seo_ai_openai_key"
                       value="<?php echo esc_attr($providers_settings['openai']['api_key'] ?? ''); ?>"
                       class="regular-text" placeholder="sk-..." />
                <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a></p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_openai_url">Base URL</label></th>
            <td>
                <input type="url" name="seo_ai_providers[openai][base_url]" id="seo_ai_openai_url"
                       value="<?php echo esc_attr($providers_settings['openai']['base_url'] ?? 'https://api.openai.com'); ?>"
                       class="regular-text" />
                <button type="button" class="button seo-ai-reset-url" data-target="seo_ai_openai_url" data-default="https://api.openai.com">Reset</button>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_openai_model">Model</label></th>
            <td>
                <select name="seo_ai_providers[openai][model]" id="seo_ai_openai_model">
                    <?php
                    $models = ['gpt-4o-mini' => 'GPT-4o Mini (Fast, cheap)', 'gpt-4o' => 'GPT-4o (Best quality)', 'gpt-4.1' => 'GPT-4.1 (Latest)', 'gpt-4.1-mini' => 'GPT-4.1 Mini (Latest, fast)'];
                    $current = $providers_settings['openai']['model'] ?? 'gpt-4o-mini';
                    foreach ($models as $val => $label):
                    ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_openai_temp">Temperature</label></th>
            <td>
                <input type="range" name="seo_ai_providers[openai][temperature]" id="seo_ai_openai_temp"
                       min="0" max="1" step="0.1" value="<?php echo esc_attr($providers_settings['openai']['temperature'] ?? '0.3'); ?>" />
                <span class="seo-ai-range-value"><?php echo esc_html($providers_settings['openai']['temperature'] ?? '0.3'); ?></span>
                <p class="description">Lower = more deterministic, Higher = more creative. Recommended: 0.3 for SEO.</p>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <button type="button" class="button seo-ai-test-provider" data-provider="openai">Test Connection</button>
                <span class="seo-ai-test-result"></span>
            </td>
        </tr>
    </table>
</div>

<!-- Claude Settings -->
<div class="seo-ai-card seo-ai-provider-settings" id="seo-ai-provider-claude" <?php echo $active_provider !== 'claude' ? 'style="display:none"' : ''; ?>>
    <h3>Anthropic (Claude) Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_claude_key">API Key</label></th>
            <td>
                <input type="password" name="seo_ai_providers[claude][api_key]" id="seo_ai_claude_key"
                       value="<?php echo esc_attr($providers_settings['claude']['api_key'] ?? ''); ?>"
                       class="regular-text" placeholder="sk-ant-..." />
                <p class="description">Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a></p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_claude_url">Base URL</label></th>
            <td>
                <input type="url" name="seo_ai_providers[claude][base_url]" id="seo_ai_claude_url"
                       value="<?php echo esc_attr($providers_settings['claude']['base_url'] ?? 'https://api.anthropic.com'); ?>"
                       class="regular-text" />
                <button type="button" class="button seo-ai-reset-url" data-target="seo_ai_claude_url" data-default="https://api.anthropic.com">Reset</button>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_claude_model">Model</label></th>
            <td>
                <select name="seo_ai_providers[claude][model]" id="seo_ai_claude_model">
                    <?php
                    $models = ['claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5 (Balanced)', 'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Fast, cheap)', 'claude-opus-4-6' => 'Claude Opus 4.6 (Most capable)'];
                    $current = $providers_settings['claude']['model'] ?? 'claude-sonnet-4-5-20250929';
                    foreach ($models as $val => $label):
                    ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_claude_temp">Temperature</label></th>
            <td>
                <input type="range" name="seo_ai_providers[claude][temperature]" id="seo_ai_claude_temp"
                       min="0" max="1" step="0.1" value="<?php echo esc_attr($providers_settings['claude']['temperature'] ?? '0.3'); ?>" />
                <span class="seo-ai-range-value"><?php echo esc_html($providers_settings['claude']['temperature'] ?? '0.3'); ?></span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <button type="button" class="button seo-ai-test-provider" data-provider="claude">Test Connection</button>
                <span class="seo-ai-test-result"></span>
            </td>
        </tr>
    </table>
</div>

<!-- Gemini Settings -->
<div class="seo-ai-card seo-ai-provider-settings" id="seo-ai-provider-gemini" <?php echo $active_provider !== 'gemini' ? 'style="display:none"' : ''; ?>>
    <h3>Google (Gemini) Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_gemini_key">API Key</label></th>
            <td>
                <input type="password" name="seo_ai_providers[gemini][api_key]" id="seo_ai_gemini_key"
                       value="<?php echo esc_attr($providers_settings['gemini']['api_key'] ?? ''); ?>"
                       class="regular-text" placeholder="AIza..." />
                <p class="description">Get your API key from <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a></p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_gemini_model">Model</label></th>
            <td>
                <select name="seo_ai_providers[gemini][model]" id="seo_ai_gemini_model">
                    <?php
                    $models = ['gemini-2.0-flash' => 'Gemini 2.0 Flash (Fast)', 'gemini-2.5-pro' => 'Gemini 2.5 Pro (Best)'];
                    $current = $providers_settings['gemini']['model'] ?? 'gemini-2.0-flash';
                    foreach ($models as $val => $label):
                    ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_gemini_temp">Temperature</label></th>
            <td>
                <input type="range" name="seo_ai_providers[gemini][temperature]" id="seo_ai_gemini_temp"
                       min="0" max="1" step="0.1" value="<?php echo esc_attr($providers_settings['gemini']['temperature'] ?? '0.3'); ?>" />
                <span class="seo-ai-range-value"><?php echo esc_html($providers_settings['gemini']['temperature'] ?? '0.3'); ?></span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <button type="button" class="button seo-ai-test-provider" data-provider="gemini">Test Connection</button>
                <span class="seo-ai-test-result"></span>
            </td>
        </tr>
    </table>
</div>

<!-- Ollama Settings -->
<div class="seo-ai-card seo-ai-provider-settings" id="seo-ai-provider-ollama" <?php echo $active_provider !== 'ollama' ? 'style="display:none"' : ''; ?>>
    <h3>Ollama (Local) Settings</h3>
    <div class="seo-ai-notice seo-ai-notice-info">
        <strong>Free & Private!</strong> Ollama runs AI models locally on your machine. No API key needed, no data leaves your server.
        <a href="https://ollama.ai" target="_blank">Install Ollama</a>
    </div>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_ollama_url">Ollama URL</label></th>
            <td>
                <input type="url" name="seo_ai_providers[ollama][base_url]" id="seo_ai_ollama_url"
                       value="<?php echo esc_attr($providers_settings['ollama']['base_url'] ?? 'http://localhost:11434'); ?>"
                       class="regular-text" />
                <button type="button" class="button seo-ai-reset-url" data-target="seo_ai_ollama_url" data-default="http://localhost:11434">Reset</button>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_ollama_model">Model</label></th>
            <td>
                <select name="seo_ai_providers[ollama][model]" id="seo_ai_ollama_model">
                    <option value="<?php echo esc_attr($providers_settings['ollama']['model'] ?? 'llama3.2'); ?>">
                        <?php echo esc_html($providers_settings['ollama']['model'] ?? 'llama3.2'); ?>
                    </option>
                </select>
                <button type="button" class="button" id="seo-ai-fetch-ollama-models">Fetch Models</button>
                <span class="seo-ai-fetch-result"></span>
                <p class="description">Click "Fetch Models" to load available models from your Ollama instance.</p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_ollama_temp">Temperature</label></th>
            <td>
                <input type="range" name="seo_ai_providers[ollama][temperature]" id="seo_ai_ollama_temp"
                       min="0" max="1" step="0.1" value="<?php echo esc_attr($providers_settings['ollama']['temperature'] ?? '0.3'); ?>" />
                <span class="seo-ai-range-value"><?php echo esc_html($providers_settings['ollama']['temperature'] ?? '0.3'); ?></span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <button type="button" class="button seo-ai-test-provider" data-provider="ollama">Test Connection</button>
                <span class="seo-ai-test-result"></span>
            </td>
        </tr>
    </table>
</div>

<!-- OpenRouter Settings -->
<div class="seo-ai-card seo-ai-provider-settings" id="seo-ai-provider-openrouter" <?php echo $active_provider !== 'openrouter' ? 'style="display:none"' : ''; ?>>
    <h3>OpenRouter Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="seo_ai_openrouter_key">API Key</label></th>
            <td>
                <input type="password" name="seo_ai_providers[openrouter][api_key]" id="seo_ai_openrouter_key"
                       value="<?php echo esc_attr($providers_settings['openrouter']['api_key'] ?? ''); ?>"
                       class="regular-text" placeholder="sk-or-..." />
                <p class="description">Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a></p>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_openrouter_model">Model</label></th>
            <td>
                <select name="seo_ai_providers[openrouter][model]" id="seo_ai_openrouter_model">
                    <?php
                    $models = [
                        'anthropic/claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
                        'openai/gpt-4o' => 'GPT-4o',
                        'google/gemini-2.0-flash' => 'Gemini 2.0 Flash',
                        'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B',
                    ];
                    $current = $providers_settings['openrouter']['model'] ?? 'anthropic/claude-sonnet-4-5-20250929';
                    foreach ($models as $val => $label):
                    ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="seo_ai_openrouter_temp">Temperature</label></th>
            <td>
                <input type="range" name="seo_ai_providers[openrouter][temperature]" id="seo_ai_openrouter_temp"
                       min="0" max="1" step="0.1" value="<?php echo esc_attr($providers_settings['openrouter']['temperature'] ?? '0.3'); ?>" />
                <span class="seo-ai-range-value"><?php echo esc_html($providers_settings['openrouter']['temperature'] ?? '0.3'); ?></span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <button type="button" class="button seo-ai-test-provider" data-provider="openrouter">Test Connection</button>
                <span class="seo-ai-test-result"></span>
            </td>
        </tr>
    </table>
</div>
