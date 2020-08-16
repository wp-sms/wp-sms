jQuery(document).ready(function ($) {
    // Set Chosen
    $(".chosen-select").chosen({width: "25em"});

    // Auto submit the gateways form, after changing chosen value
    $("#wpsms_settings\\[gateway_name\\]").on('change', function () {
        $( 'input[name="submit"]' ).click();
    });

    // Check about page
    if ($('.wp-sms-welcome').length) {
        $('.nav-tab-wrapper a').click(function () {
            var tab_id = $(this).attr('data-tab');

            if (tab_id == 'link') {
                return true;
            }

            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $('.tab-content').removeClass('current');

            $("[data-tab=" + tab_id + "]").addClass('nav-tab-active');
            $("[data-content=" + tab_id + "]").addClass('current');

            return false;
        });
    }

    if ($('.repeater').length) {
        $('.repeater').repeater({
            initEmpty: false,
            show: function () {
                $(this).slideDown();
            },
            hide: function (deleteElement) {
                if (confirm('Are you sure you want to delete this item?')) {
                    $(this).slideUp(deleteElement);
                }
            },
            isFirstItemUndeletable: true
        });
    }
});