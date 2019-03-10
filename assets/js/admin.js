jQuery(document).ready(function () {
    jQuery(".chosen-select").chosen({width: "25em"});
    // Check about page
    if (jQuery('.wp-sms-welcome').length) {
        jQuery('.nav-tab-wrapper a').click(function () {
            var tab_id = jQuery(this).attr('data-tab');

            if (tab_id == 'link') {
                return true;
            }

            jQuery('.nav-tab-wrapper a').removeClass('nav-tab-active');
            jQuery('.tab-content').removeClass('current');

            jQuery("[data-tab=" + tab_id + "]").addClass('nav-tab-active');
            jQuery("[data-content=" + tab_id + "]").addClass('current');

            return false;
        });
    }
});