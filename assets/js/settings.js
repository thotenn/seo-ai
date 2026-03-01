/**
 * SEO AI - Settings Page Scripts
 */
(function($) {
    'use strict';

    // ========================================================================
    // Tab Navigation
    // ========================================================================
    $(document).on('click', '.seo-ai-tabs .nav-tab', function(e) {
        e.preventDefault();
        var target = $(this).data('tab');

        // Update tab state.
        $(this).siblings().removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show target panel.
        $('.seo-ai-tab-panel').removeClass('active');
        $('#seo-ai-tab-' + target).addClass('active');

        // Store active tab in URL hash.
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + target);
        }
    });

    // ========================================================================
    // Restore Tab from Hash
    // ========================================================================
    function restoreTab() {
        var hash = window.location.hash.replace('#', '');
        if (hash) {
            var $tab = $('.seo-ai-tabs .nav-tab[data-tab="' + hash + '"]');
            if ($tab.length) {
                $tab.trigger('click');
                return;
            }
        }
        // Activate first tab by default.
        $('.seo-ai-tabs .nav-tab:first').trigger('click');
    }

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
    // Init
    // ========================================================================
    $(function() {
        restoreTab();

        // Set initial state for module cards.
        $('.seo-ai-module-card input[type="checkbox"]').each(function() {
            if ($(this).is(':checked')) {
                $(this).closest('.seo-ai-module-card').addClass('enabled');
            }
        });
    });

})(jQuery);
