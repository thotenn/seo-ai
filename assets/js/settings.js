/**
 * SEO AI - Settings Page Scripts
 */
(function($) {
    'use strict';

    // ========================================================================
    // Module Toggle Cards
    // ========================================================================
    $(document).on('change', '.seo-ai-module-card input[type="checkbox"]', function() {
        var $card = $(this).closest('.seo-ai-module-card');
        if ($(this).is(':checked')) {
            $card.addClass('enabled');
        } else {
            $card.removeClass('enabled');
        }
    });

    // ========================================================================
    // Reset Prompt to Default (empty)
    // ========================================================================
    $(document).on('click', '.seo-ai-reset-prompt', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
    });

    // ========================================================================
    // Ollama: Fetch Models → populate datalist for text input
    // ========================================================================
    $('#seo-ai-fetch-ollama-models').on('click', function() {
        var $btn = $(this);
        var $result = $btn.siblings('.seo-ai-fetch-result');
        var baseUrl = $('#seo_ai_ollama_url').val() || 'http://localhost:11434';

        $btn.prop('disabled', true);
        $result.text('Fetching...').css('color', '');

        $.ajax({
            url: seoAi.restUrl + 'seo-ai/v1/settings/ollama-models',
            method: 'POST',
            headers: { 'X-WP-Nonce': seoAi.nonce },
            data: JSON.stringify({ base_url: baseUrl }),
            contentType: 'application/json',
            success: function(resp) {
                var models = resp.data && resp.data.models ? resp.data.models : [];
                if (models.length) {
                    // Populate datalist for autocomplete
                    var $datalist = $('#seo_ai_ollama_models_list');
                    if (!$datalist.length) {
                        $datalist = $('<datalist id="seo_ai_ollama_models_list">');
                        $('#seo_ai_ollama_model').after($datalist).attr('list', 'seo_ai_ollama_models_list');
                    }
                    $datalist.empty();
                    models.forEach(function(m) { $datalist.append($('<option>').val(m)); });
                    $result.text(models.length + ' models found').css('color', '#16a34a');
                } else {
                    $result.text('No models found').css('color', '#f59e0b');
                }
            },
            error: function() {
                $result.text('Connection failed').css('color', '#dc2626');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // ========================================================================
    // Robots.txt Validation
    // ========================================================================
    var validPrefixes = ['user-agent:', 'disallow:', 'allow:', 'sitemap:', 'host:', 'crawl-delay:', 'clean-param:', '#'];

    function validateRobotsRules() {
        var $textarea = $('#seo_ai_robots_rules');
        var $output = $('#seo-ai-robots-validation');
        if (!$textarea.length) return;

        var text = $textarea.val().trim();
        if (!text) {
            $output.html('');
            return;
        }

        var lines = text.split('\n');
        var errors = [];

        lines.forEach(function(line, i) {
            line = line.trim();
            if (!line) return; // skip empty
            var lower = line.toLowerCase();
            var valid = validPrefixes.some(function(p) { return lower.indexOf(p) === 0; });
            if (!valid) {
                errors.push('Line ' + (i + 1) + ': "' + line.substring(0, 40) + '" — unrecognized directive');
            }
        });

        if (errors.length) {
            $output.html('<span style="color:#dc2626;font-size:12px;">' + errors.join('<br>') + '</span>');
        } else {
            $output.html('<span style="color:#16a34a;font-size:12px;">All directives are valid.</span>');
        }
    }

    $(document).on('input', '#seo_ai_robots_rules', validateRobotsRules);

    // ========================================================================
    // Init
    // ========================================================================
    $(function() {
        // Set initial state for module cards.
        $('.seo-ai-module-card input[type="checkbox"]').each(function() {
            if ($(this).is(':checked')) {
                $(this).closest('.seo-ai-module-card').addClass('enabled');
            }
        });

        // Validate robots rules on page load.
        validateRobotsRules();
    });

})(jQuery);
