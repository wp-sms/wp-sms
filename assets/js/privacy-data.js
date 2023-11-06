// Privacy Page Ajax
jQuery(document).ready(function () {
    wpSmsPrivacyPage.init();
});

let wpSmsPrivacyPage = {
    elements: {},
    init: function () {
        this.elements.form = jQuery('.wpsms-privacyPage__Form');

        this.elements.form.find('input[type=submit]').on('click', function (event) {
            event.preventDefault();
            var type = event.target.name;

            var mobileNumber = jQuery(event.target).closest('div').find('input[type="tel"]').val();
            var formData = new FormData();
            formData.append('mobileNumber', mobileNumber);
            formData.append('type', type);

            jQuery.ajax({
                url: wp_sms_privacy_page_ajax_vars.url,
                method: 'POST',
                contentType: false,
                cache: false,
                processData: false,
                data: formData,

                beforeSend: function () {
                    jQuery('.wpsms-privacyPage__Result').slideUp();
                },
                success: function (data, response, xhr) {
                    jQuery('.wpsms-privacyPage__Result').removeClass('error');
                    jQuery('.wpsms-privacyPage__Result').addClass('success');
                    // If the file is generated
                    if (data.data.file_url) {
                        window.open(data.data.file_url, '_blank');
                        jQuery('.wpsms-privacyPage__Result p').html(data.data.message);
                    } else {
                        jQuery('.wpsms-privacyPage__Result p').html(data.data);
                    }
                    jQuery('.wpsms-privacyPage__Result').slideDown();
                },
                error: function (data, response, xhr) {
                    jQuery('.wpsms-privacyPage__Result').removeClass('success');
                    jQuery('.wpsms-privacyPage__Result').addClass('error');
                    jQuery('.wpsms-privacyPage__Result p').html(data.responseJSON.data.message);
                    jQuery('.wpsms-privacyPage__Result').slideDown();
                }
            });
        });
    }
};

