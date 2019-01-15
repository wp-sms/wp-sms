jQuery(document).ready(function () {
    var input = document.querySelector(".wp-sms-input-mobile");
    if(input) {
        window.intlTelInput(input, {
            onlyCountries: wp_sms_intel_tel_input.only_countries,
            preferredCountries: wp_sms_intel_tel_input.preferred_countries,
            autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
            nationalMode: wp_sms_intel_tel_input.national_mode,
            separateDialCode: wp_sms_intel_tel_input.separate_dial,
            utilsScript: wp_sms_intel_tel_input.util_js
        });
    }
});