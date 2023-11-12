// Contact Form 7 Validation
jQuery(document).ready(function () {
    contactFormOtp.init();
});

let contactFormOtp = {
    elements: {}, init: function () {
        this.elements.form = jQuery('.wpcf7-form');
        this.elements.mobile = this.elements.form.find('input[name=wpsms-verify-mobile]')
        this.elements.submit = this.elements.form.find('input[type=submit]')

        this.elements.submit.on('click', function (event) {
            event.preventDefault();
            var type = event.target.name;

            var mobileNumber = jQuery(event.target).closest('div').find('input[type="tel"]').val();
            var formData = new FormData();
            formData.append('mobileNumber', mobileNumber);
            formData.append('type', type);

            jQuery.ajax({
                url: wp_sms_privacy_page_ajax_vars.url, method: 'POST', contentType: false, cache: false, processData: false, data: formData,

                beforeSend: function () {
                    jQuery('.wpsms-privacyPage__Result__Container').hide();
                    jQuery('.wpsms-privacyPage__Result__Container').empty();

                }, success: function (data, response, xhr) {
                    // If the file is generated
                    if (data.data.file_url) {
                        window.open(data.data.file_url);
                        jQuery('.wpsms-privacyPage__Result__Container').html(data.data.message);
                    }
                    jQuery('.wpsms-privacyPage__Result__Container').html(data.data.message);
                    jQuery('.wpsms-privacyPage__Result__Container').show();
                }, error: function (data, response, xhr) {
                    jQuery('.wpsms-privacyPage__Result__Container').html(data.responseJSON.data.message);
                    jQuery('.wpsms-privacyPage__Result__Container').show();
                }
            });
        });
    }
};