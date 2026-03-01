/**
 * SEO AI - Admin Scripts
 */
(function($) {
    'use strict';

    const seoAi = window.seoAi || window.seoAiAdmin || {};

    // ========================================================================
    // Toast Notifications
    // ========================================================================
    function toast(message, type = 'success') {
        const $toast = $('<div class="seo-ai-toast seo-ai-toast-' + type + '">' + message + '</div>');
        $('body').append($toast);
        setTimeout(() => $toast.css('opacity', 0), 2500);
        setTimeout(() => $toast.remove(), 3000);
    }

    // ========================================================================
    // API Helper
    // ========================================================================
    function apiRequest(endpoint, method, data) {
        return $.ajax({
            url: seoAi.restUrl + endpoint,
            method: method || 'GET',
            data: data ? JSON.stringify(data) : undefined,
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', seoAi.nonce);
            }
        });
    }

    // ========================================================================
    // Provider Selection
    // ========================================================================
    $(document).on('click', '.seo-ai-provider-card', function() {
        const provider = $(this).data('provider');
        $('.seo-ai-provider-card').removeClass('active');
        $(this).addClass('active');
        $('#seo_ai_active_provider').val(provider);
        $('.seo-ai-provider-settings').hide();
        $('#seo-ai-provider-' + provider).show();
    });

    // ========================================================================
    // Reset Base URL
    // ========================================================================
    $(document).on('click', '.seo-ai-reset-url', function() {
        const target = $(this).data('target');
        const defaultUrl = $(this).data('default');
        $('#' + target).val(defaultUrl);
    });

    // ========================================================================
    // Temperature Slider
    // ========================================================================
    $(document).on('input', 'input[type="range"]', function() {
        $(this).siblings('.seo-ai-range-value').text($(this).val());
    });

    // ========================================================================
    // Test Provider Connection
    // ========================================================================
    $(document).on('click', '.seo-ai-test-provider', function() {
        const $btn = $(this);
        const provider = $btn.data('provider');
        const $result = $btn.siblings('.seo-ai-test-result');

        $btn.prop('disabled', true).html('<span class="seo-ai-spinner"></span> Testing...');
        $result.html('').hide();

        // Collect current settings for this provider
        const settings = {};
        $btn.closest('.seo-ai-provider-settings').find('input, select, textarea').each(function() {
            const name = $(this).attr('name');
            if (name && name.startsWith('seo_ai_providers[')) {
                const match = name.match(/\[([^\]]+)\]\[([^\]]+)\]/);
                if (match && match[2]) {
                    settings[match[2]] = $(this).val();
                }
            }
        });

        apiRequest('provider/test', 'POST', { provider, settings })
            .done(function(data) {
                if (data.success) {
                    $result.html('<span class="seo-ai-badge seo-ai-badge-success">' + data.message + '</span>').show();
                } else {
                    $result.html('<span class="seo-ai-badge seo-ai-badge-danger">' + (data.message || 'Connection failed') + '</span>').show();
                }
            })
            .fail(function(xhr) {
                const msg = xhr.responseJSON?.message || xhr.statusText || 'Request failed';
                $result.html('<span class="seo-ai-badge seo-ai-badge-danger">' + msg + '</span>').show();
            })
            .always(function() {
                $btn.prop('disabled', false).html('Test Connection');
            });
    });

    // ========================================================================
    // Fetch Ollama Models
    // ========================================================================
    $(document).on('click', '#seo-ai-fetch-ollama-models', function() {
        const $btn = $(this);
        const $result = $btn.siblings('.seo-ai-fetch-result');
        const baseUrl = $('#seo_ai_ollama_url').val();

        $btn.prop('disabled', true).text('Fetching...');
        $result.html('');

        apiRequest('provider/models', 'GET', { provider: 'ollama', base_url: baseUrl })
            .done(function(data) {
                if (data.success && data.data && data.data.models) {
                    const $select = $('#seo_ai_ollama_model');
                    const current = $select.val();
                    $select.empty();
                    $.each(data.data.models, function(id, name) {
                        $select.append($('<option>').val(id).text(name));
                    });
                    if (current && $select.find('option[value="' + current + '"]').length) {
                        $select.val(current);
                    }
                    $result.html('<span class="seo-ai-badge seo-ai-badge-success">Found ' + Object.keys(data.data.models).length + ' models</span>');
                } else {
                    $result.html('<span class="seo-ai-badge seo-ai-badge-danger">No models found</span>');
                }
            })
            .fail(function(xhr) {
                $result.html('<span class="seo-ai-badge seo-ai-badge-danger">Failed to fetch models</span>');
            })
            .always(function() {
                $btn.prop('disabled', false).text('Fetch Models');
            });
    });

    // ========================================================================
    // Save Settings
    // ========================================================================
    $(document).on('click', '#seo-ai-save-settings', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="seo-ai-spinner"></span> Saving...');

        const settings = {};
        const providers = {};

        $('#seo-ai-settings-form').find('input, select, textarea').each(function() {
            const name = $(this).attr('name');
            if (!name) return;

            let value;
            if ($(this).attr('type') === 'checkbox') {
                if (name.endsWith('[]')) {
                    if (!$(this).is(':checked')) return;
                    value = $(this).val();
                } else {
                    value = $(this).is(':checked') ? true : false;
                }
            } else {
                value = $(this).val();
            }

            if (name.startsWith('seo_ai_settings[')) {
                const inner = name.slice('seo_ai_settings['.length);
                const isArray = inner.endsWith('[]');
                const keys = inner.replace(/\[\]$/, '').split(']').filter(Boolean).map(k => k.replace(/^\[/, ''));

                let target = settings;
                for (let i = 0; i < keys.length - 1; i++) {
                    if (!target[keys[i]] || typeof target[keys[i]] !== 'object') {
                        target[keys[i]] = {};
                    }
                    target = target[keys[i]];
                }

                const lastKey = keys[keys.length - 1];
                if (isArray) {
                    if (!Array.isArray(target[lastKey])) target[lastKey] = [];
                    target[lastKey].push(value);
                } else {
                    target[lastKey] = value;
                }
            } else if (name.startsWith('seo_ai_providers[')) {
                const inner = name.slice('seo_ai_providers['.length);
                const keys = inner.split(']').filter(Boolean).map(k => k.replace(/^\[/, ''));

                let target = providers;
                for (let i = 0; i < keys.length - 1; i++) {
                    if (!target[keys[i]] || typeof target[keys[i]] !== 'object') {
                        target[keys[i]] = {};
                    }
                    target = target[keys[i]];
                }
                target[keys[keys.length - 1]] = value;
            }
        });

        // Save both settings and providers
        const promises = [];
        if (Object.keys(settings).length > 0) {
            promises.push(apiRequest('settings', 'POST', { settings }));
        }
        if (Object.keys(providers).length > 0) {
            promises.push(apiRequest('settings/providers', 'POST', { providers }));
        }

        $.when.apply($, promises)
            .done(function() { toast('Settings saved successfully!', 'success'); })
            .fail(function() { toast('Failed to save settings', 'error'); })
            .always(function() { $btn.prop('disabled', false).html('Save Settings'); });
    });

    // ========================================================================
    // Restore Defaults
    // ========================================================================
    $(document).on('click', '#seo-ai-restore-defaults', function() {
        if (!confirm('Are you sure you want to restore settings to defaults?\n\nProvider settings will not be changed.')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="seo-ai-spinner"></span> Restoring...');

        apiRequest('settings/reset', 'POST')
            .done(function() {
                toast('Settings restored to defaults', 'success');
                setTimeout(function() { location.reload(); }, 800);
            })
            .fail(function() { toast('Failed to restore defaults', 'error'); })
            .always(function() { $btn.prop('disabled', false).html('Restore Defaults'); });
    });

    // ========================================================================
    // Image Upload
    // ========================================================================
    $(document).on('click', '.seo-ai-upload-image', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const target = $btn.data('target');

        const frame = wp.media({
            title: 'Select Image',
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $('#' + target).val(attachment.url);
        });

        frame.open();
    });

})(jQuery);
