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
    // Init
    // ========================================================================
    $(function() {
        // Set initial state for module cards.
        $('.seo-ai-module-card input[type="checkbox"]').each(function() {
            if ($(this).is(':checked')) {
                $(this).closest('.seo-ai-module-card').addClass('enabled');
            }
        });
    });

})(jQuery);
