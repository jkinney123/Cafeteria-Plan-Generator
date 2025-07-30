
jQuery(document).ready(function ($) {
    // Handle Learn More button clicks
    $('.cpp-learn-more-btn').on('click', function (e) {
        e.preventDefault();

        const button = $(this);
        const targetId = button.data('target');
        const content = $('#' + targetId);
        const icon = button.find('.dashicons');

        if (content.is(':visible')) {
            // Hide content
            content.slideUp(300);
            button.removeClass('expanded');
            button.html('<span class="dashicons dashicons-arrow-right-alt2" style="font-size: 13px; vertical-align: middle;"></span> Learn More: View Legal Clause');
        } else {
            // Show content
            content.slideDown(300);
            button.addClass('expanded');
            button.html('<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 13px; vertical-align: middle;"></span> Hide Legal Clause');
        }
    });

    // Enhanced tooltip handling for touch devices
    $('.cpp-tooltip-trigger').on('touchstart', function (e) {
        e.preventDefault();

        // Hide any existing tooltips
        $('.cpp-tooltip-active').removeClass('cpp-tooltip-active');

        // Show this tooltip
        $(this).addClass('cpp-tooltip-active');

        // Hide tooltip after 4 seconds
        setTimeout(() => {
            $(this).removeClass('cpp-tooltip-active');
        }, 4000);
    });

    // Click outside to hide tooltips on touch devices
    $(document).on('touchstart', function (e) {
        if (!$(e.target).closest('.cpp-tooltip-trigger').length) {
            $('.cpp-tooltip-active').removeClass('cpp-tooltip-active');
        }
    });

    // Improve form validation feedback
    $('form').on('submit', function () {
        const requiredFields = $(this).find('[required]');
        let isValid = true;

        requiredFields.each(function () {
            if (!$(this).val()) {
                $(this).css('border-color', '#d63638');
                isValid = false;
            } else {
                $(this).css('border-color', '#8c8f94');
            }
        });

        if (!isValid) {
            $('html, body').animate({
                scrollTop: $(this).find('[required]').first().offset().top - 100
            }, 500);
        }

        return isValid;
    });
});
