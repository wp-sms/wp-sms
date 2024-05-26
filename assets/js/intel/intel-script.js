jQuery(document).ready(function () {

    setTimeout(init, 1500);
});


function init() {
    var inputTells = document.querySelectorAll(".wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone, #billing-phone, #wp-sms-input-mobile, .user-mobile-wrap #mobile");

    if (inputTells && inputTells.length) {
        for (var i = 0; i < inputTells.length; i++) {
            var parentElement = inputTells[i].parentNode;
            if (inputTells[i] && inputTells[i].nodeName == 'INPUT') {
                const body = document.body;
                const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';
                inputTells[i].setAttribute('dir', direction);

                window.intlTelInput(inputTells[i], {
                    separateDialCode: true,
                    allowDropdown: true,
                    strictMode: true,
                    autoPlaceholder: "aggressive",
                    onlyCountries: wp_sms_intel_tel_input.only_countries,
                    countryOrder: wp_sms_intel_tel_input.preferred_countries,
                    //autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
                    nationalMode: false,
                    useFullscreenPopup: false,
                    utilsScript: wp_sms_intel_tel_input.util_js,
                    formatOnDisplay: false,
                    initialCountry: 'us'
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
        const body = document.body;
        const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';
        inputTell.setAttribute('dir', direction)
        window.intlTelInput(inputTell, {
             autoInsertDialCode: true,
            autoPlaceholder: "aggressive",
            allowDropdown: true,
            strictMode: true,
            useFullscreenPopup: false,
            onlyCountries: wp_sms_intel_tel_input.only_countries,
            countryOrder: wp_sms_intel_tel_input.preferred_countries,
            autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
            nationalMode: wp_sms_intel_tel_input.national_mode,
            utilsScript: wp_sms_intel_tel_input.util_js,
            formatOnDisplay: false,
            initialCountry: 'us'
         });
    }

    // Handle the change event for the checkbox
    document.querySelector('#shipping-fields .wc-block-checkout__use-address-for-billing input').addEventListener('change', function () {
        if (document.querySelector('#billing-fields')) {
            setTimeout(checkAndInitializeInputs, 500);
        }
    });
}
