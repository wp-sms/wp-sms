jQuery(document).ready(function () {
    setTimeout(() => init(), 500);    
});

function init() {
    var inputTells = document.querySelectorAll(".wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone, #billing-phone, #wp-sms-input-mobile, .user-mobile-wrap #mobile");

    for (var i = 0; i < inputTells.length; i++) {
        if (inputTells[i] && inputTells[i].nodeName == 'INPUT') {
            
            inputTells[i].setAttribute('dir', 'ltr')
            window.intlTelInput(inputTells[i], {
                autoInsertDialCode: true,
                onlyCountries: wp_sms_intel_tel_input.only_countries,
                preferredCountries: wp_sms_intel_tel_input.preferred_countries,
                //autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
                nationalMode: false,
                utilsScript: wp_sms_intel_tel_input.util_js,
                formatOnDisplay: false,
                //initialCountry: 'auto'
            });
        }
    }

    var inputTell = document.querySelector("#job_mobile, #_job_mobile");

    if (inputTell && !inputTell.getAttribute('placeholder')) {
        inputTell.setAttribute('dir', 'ltr')

        window.intlTelInput(inputTell, {
            autoInsertDialCode: true,
            onlyCountries: wp_sms_intel_tel_input.only_countries,
            preferredCountries: wp_sms_intel_tel_input.preferred_countries,
            autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
            nationalMode: wp_sms_intel_tel_input.national_mode,
            utilsScript: wp_sms_intel_tel_input.util_js,
            formatOnDisplay: false,
            //initialCountry: 'auto'
        });
    }
}