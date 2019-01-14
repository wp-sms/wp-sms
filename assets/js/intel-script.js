jQuery(document).ready(function () {
    jQuery(".wp-sms-intel-mobile").intlTelInput(
        {
            onlyCountries: [wp_sms_intel_tel_input.only_countries],
            preferredCountries: [wp_sms_intel_tel_input.preferred_countries],
            autoHideDialCode: false,
            nationalMode: false,
            separateDialCode: true,
        }
    );
});