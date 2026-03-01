/**
 * SEO AI - Metabox Scripts (Post Editor)
 */
(function($) {
    'use strict';

    const seoAi = window.seoAi || {};
    const seoAiPost = window.seoAiPost || {};
    let analysisTimer = null;

    // ========================================================================
    // Toast
    // ========================================================================
    function toast(msg, type) {
        const $t = $('<div class="seo-ai-toast seo-ai-toast-' + (type||'success') + '">' + msg + '</div>');
        $('body').append($t);
        setTimeout(() => $t.css('opacity', 0), 2500);
        setTimeout(() => $t.remove(), 3000);
    }

    // ========================================================================
    // API Helper
    // ========================================================================
    function api(endpoint, method, data) {
        return $.ajax({
            url: seoAi.restUrl + endpoint,
            method: method || 'POST',
            data: data ? JSON.stringify(data) : undefined,
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', seoAi.nonce);
            }
        });
    }

    // ========================================================================
    // Tab Navigation
    // ========================================================================
    $(document).on('click', '.seo-ai-metabox-tab', function(e) {
        e.preventDefault();
        const target = $(this).data('tab');
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        $(this).closest('.seo-ai-metabox').find('.seo-ai-metabox-panel').removeClass('active');
        $('#seo-ai-panel-' + target).addClass('active');
    });

    // ========================================================================
    // Character Counter
    // ========================================================================
    function updateCounter($input, min, max) {
        const len = ($input.val() || '').length;
        const $counter = $input.closest('.seo-ai-field').find('.seo-ai-field-counter');
        $counter.text(len + ' / ' + max);
        $counter.removeClass('warning error');
        if (len > max) $counter.addClass('error');
        else if (len < min) $counter.addClass('warning');
    }

    $(document).on('input', '#seo_ai_title', function() {
        updateCounter($(this), 50, 60);
        updatePreview();
        scheduleAnalysis();
    });

    $(document).on('input', '#seo_ai_description', function() {
        updateCounter($(this), 120, 160);
        updatePreview();
        scheduleAnalysis();
    });

    $(document).on('input', '#seo_ai_focus_keyword', function() {
        scheduleAnalysis();
    });

    // ========================================================================
    // Google Preview
    // ========================================================================
    function updatePreview() {
        const title = $('#seo_ai_title').val() || seoAiPost.postTitle || 'Page Title';
        const desc = $('#seo_ai_description').val() || 'Add a meta description for this page...';
        const url = seoAiPost.postUrl || window.location.href;

        $('.seo-ai-preview-title').text(title);
        $('.seo-ai-preview-url').text(url);
        $('.seo-ai-preview-description').text(desc);
    }

    // ========================================================================
    // Content Analysis
    // ========================================================================
    function scheduleAnalysis() {
        clearTimeout(analysisTimer);
        analysisTimer = setTimeout(runAnalysis, 800);
    }

    function getEditorContent() {
        // Try Gutenberg first
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            const content = wp.data.select('core/editor').getEditedPostContent();
            if (content) return content;
        }
        // Try TinyMCE
        if (window.tinyMCE && tinyMCE.get('content')) {
            return tinyMCE.get('content').getContent();
        }
        // Fallback to textarea
        return $('#content').val() || '';
    }

    function getPostTitle() {
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
        }
        return $('#title').val() || '';
    }

    function runAnalysis() {
        const content = getEditorContent();
        const postTitle = getPostTitle();
        const keyword = $('#seo_ai_focus_keyword').val() || '';
        const title = $('#seo_ai_title').val() || '';
        const description = $('#seo_ai_description').val() || '';

        if (!content && !title) return;

        api('analyze', 'POST', {
            post_id: seoAiPost.postId,
            title: title || postTitle,
            content: content,
            description: description,
            keyword: keyword,
            url: seoAiPost.postUrl
        })
        .done(function(data) {
            if (data.success && data.data) {
                renderScores(data.data);
                renderChecks(data.data);
            }
        });
    }

    function renderScores(data) {
        const seoScore = data.seo?.score ?? 0;
        const readScore = data.readability?.score ?? 0;

        // Update score circles
        updateScoreCircle('#seo-ai-seo-score', seoScore);
        updateScoreCircle('#seo-ai-read-score', readScore);

        // Update hidden inputs for saving
        $('#seo_ai_seo_score_val').val(seoScore);
        $('#seo_ai_readability_score_val').val(readScore);
    }

    function updateScoreCircle(selector, score) {
        const $el = $(selector);
        $el.text(score);
        $el.removeClass('seo-ai-score-good seo-ai-score-warning seo-ai-score-error');
        if (score >= 70) $el.addClass('seo-ai-score-good');
        else if (score >= 40) $el.addClass('seo-ai-score-warning');
        else $el.addClass('seo-ai-score-error');
    }

    function renderChecks(data) {
        // SEO checks
        renderCheckList('#seo-ai-seo-checks', data.seo?.checks || []);
        // Readability checks
        renderCheckList('#seo-ai-read-checks', data.readability?.checks || []);
    }

    function renderCheckList(selector, checks) {
        const $list = $(selector).empty();
        checks.forEach(function(check) {
            const iconContent = check.status === 'good' ? '&#10003;' : check.status === 'warning' ? '!' : '&#10007;';
            const fixBtn = check.status !== 'good'
                ? '<button type="button" class="seo-ai-check-fix" data-check="' + check.id + '">Fix with AI</button>'
                : '';
            $list.append(
                '<li class="seo-ai-check seo-ai-check-' + check.status + '">' +
                '  <span class="seo-ai-check-icon">' + iconContent + '</span>' +
                '  <span class="seo-ai-check-text">' + check.message + '</span>' +
                '  ' + fixBtn +
                '</li>'
            );
        });
    }

    // ========================================================================
    // AI Generation
    // ========================================================================
    $(document).on('click', '#seo-ai-generate-title', function() {
        generateField($(this), 'title');
    });

    $(document).on('click', '#seo-ai-generate-description', function() {
        generateField($(this), 'description');
    });

    $(document).on('click', '#seo-ai-generate-keyword', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="seo-ai-spinner"></span> Generating...');

        api('ai/generate-meta', 'POST', {
            post_id: seoAiPost.postId,
            content: getEditorContent(),
            keyword: '',
            field: 'keyword'
        })
        .done(function(data) {
            if (data.success && data.data?.value) {
                $('#seo_ai_focus_keyword').val(data.data.value);
                toast('Focus keyword generated!');
                scheduleAnalysis();
            }
        })
        .fail(function() { toast('AI generation failed', 'error'); })
        .always(function() { $btn.prop('disabled', false).text('Generate with AI'); });
    });

    function generateField($btn, field) {
        $btn.prop('disabled', true).html('<span class="seo-ai-spinner"></span> Generating...');

        const keyword = $('#seo_ai_focus_keyword').val() || '';
        api('ai/generate-meta', 'POST', {
            post_id: seoAiPost.postId,
            content: getEditorContent(),
            keyword: keyword,
            field: field
        })
        .done(function(data) {
            if (data.success && data.data?.value) {
                const $input = $('#seo_ai_' + field);
                $input.val(data.data.value).trigger('input');
                toast('Generated ' + field + '!');
            }
        })
        .fail(function() { toast('AI generation failed', 'error'); })
        .always(function() { $btn.prop('disabled', false).text('Generate with AI'); });
    }

    // ========================================================================
    // Fix with AI
    // ========================================================================
    $(document).on('click', '.seo-ai-check-fix', function() {
        const $btn = $(this);
        const checkId = $btn.data('check');
        $btn.prop('disabled', true).text('Fixing...');

        api('ai/optimize', 'POST', {
            post_id: seoAiPost.postId,
            content: getEditorContent(),
            keyword: $('#seo_ai_focus_keyword').val() || '',
            failing_checks: [checkId]
        })
        .done(function(data) {
            if (data.success && data.data?.suggestions) {
                data.data.suggestions.forEach(function(s) {
                    if (s.field && s.value) {
                        $('#seo_ai_' + s.field).val(s.value).trigger('input');
                    }
                });
                toast('Applied AI suggestion!');
                scheduleAnalysis();
            }
        })
        .fail(function() { toast('AI fix failed', 'error'); })
        .always(function() { $btn.prop('disabled', false).text('Fix with AI'); });
    });

    // ========================================================================
    // Optimize All with AI
    // ========================================================================
    $(document).on('click', '#seo-ai-optimize-all', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="seo-ai-spinner"></span> Optimizing...');

        api('ai/optimize', 'POST', {
            post_id: seoAiPost.postId,
            content: getEditorContent(),
            keyword: $('#seo_ai_focus_keyword').val() || '',
            failing_checks: []
        })
        .done(function(data) {
            if (data.success && data.data?.suggestions) {
                data.data.suggestions.forEach(function(s) {
                    if (s.field && s.value) {
                        $('#seo_ai_' + s.field).val(s.value).trigger('input');
                    }
                });
                toast('All SEO fields optimized!');
                scheduleAnalysis();
            }
        })
        .fail(function() { toast('AI optimization failed', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('Optimize All with AI'); });
    });

    // ========================================================================
    // Social Preview
    // ========================================================================
    function updateSocialPreview() {
        const ogTitle = $('#seo_ai_og_title').val() || $('#seo_ai_title').val() || seoAiPost.postTitle || '';
        const ogDesc = $('#seo_ai_og_description').val() || $('#seo_ai_description').val() || '';
        const domain = (seoAiPost.postUrl || window.location.href).replace(/^https?:\/\//, '').split('/')[0];

        $('.seo-ai-social-preview-title').text(ogTitle);
        $('.seo-ai-social-preview-desc').text(ogDesc);
        $('.seo-ai-social-preview-domain').text(domain);
    }

    $(document).on('input', '#seo_ai_og_title, #seo_ai_og_description', updateSocialPreview);

    // ========================================================================
    // Initialize
    // ========================================================================
    $(function() {
        updatePreview();
        updateCounter($('#seo_ai_title'), 50, 60);
        updateCounter($('#seo_ai_description'), 120, 160);

        // Initial analysis
        setTimeout(runAnalysis, 1000);

        // Watch for Gutenberg changes
        if (window.wp && wp.data && wp.data.subscribe) {
            let lastContent = '';
            wp.data.subscribe(function() {
                const content = wp.data.select('core/editor')?.getEditedPostContent() || '';
                if (content !== lastContent) {
                    lastContent = content;
                    scheduleAnalysis();
                }
            });
        }
    });

})(jQuery);
