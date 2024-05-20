jQuery(document).ready(function () {
    setTimeout(init, 1500);
});

function init() {
    // Initialize input fields with intlTelInput
    function initializeInputs(inputTells) {
        for (var i = 0; i < inputTells.length; i++) {
            if (inputTells[i] && inputTells[i].nodeName === 'INPUT') {
                inputTells[i].setAttribute('dir', 'ltr');
                window.intlTelInput(inputTells[i], {
                    autoInsertDialCode: true,
                    onlyCountries: wp_sms_intel_tel_input.only_countries,
                    preferredCountries: wp_sms_intel_tel_input.preferred_countries,
                    // autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
                    nationalMode: false,
                    utilsScript: wp_sms_intel_tel_input.util_js,
                    formatOnDisplay: false,
                    // initialCountry: 'auto'
                });
            }
        }
    }

    // Check and initialize the main input fields
    function checkAndInitializeInputs() {
        var inputTells = document.querySelectorAll('#billing-wpsms\\/mobile');
        if (!inputTells.length) {
            inputTells = document.querySelectorAll("#billing-phone");
        }

        if (!inputTells.length) {
            inputTells = document.querySelectorAll(".wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone, #billing-phone, #wp-sms-input-mobile, .user-mobile-wrap #mobile");
        }
        initializeInputs(inputTells);
    }

    checkAndInitializeInputs();

    // Additional specific input field initialization
    var inputTell = document.querySelector("#job_mobile, #_job_mobile");
    if (inputTell && !inputTell.getAttribute('placeholder')) {
        inputTell.setAttribute('dir', 'ltr');
        window.intlTelInput(inputTell, {
            autoInsertDialCode: true,
            onlyCountries: wp_sms_intel_tel_input.only_countries,
            preferredCountries: wp_sms_intel_tel_input.preferred_countries,
            autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
            nationalMode: wp_sms_intel_tel_input.national_mode,
            utilsScript: wp_sms_intel_tel_input.util_js,
            formatOnDisplay: false,
            // initialCountry: 'auto'
        });
    }

    // Handle the change event for the checkbox
    document.querySelector('#shipping-fields .wc-block-checkout__use-address-for-billing input').addEventListener('change', function () {
        if (document.querySelector('#billing-fields')) {
            setTimeout(checkAndInitializeInputs, 500);
        }
    });
}
