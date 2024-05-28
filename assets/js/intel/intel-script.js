jQuery(document).ready(function () {
    setTimeout(init, 1500);
});

const body = document.body;
const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';

function init() {
    function initializeInput(input) {
        if (input && input.nodeName === 'INPUT') {
             input.setAttribute('dir', direction);

            let iti = window.intlTelInput(input, {
                separateDialCode: false,
                allowDropdown: true,
                strictMode: true,
                onlyCountries: wp_sms_intel_tel_input.only_countries,
                countryOrder: wp_sms_intel_tel_input.preferred_countries,
                nationalMode: true,
                useFullscreenPopup: false,
                utilsScript: wp_sms_intel_tel_input.util_js,
                formatOnDisplay: false,
                initialCountry: 'us'
            });

            let currentDialCode = iti.getSelectedCountryData().dialCode;

            function updateInputWithCountryCode(input, newDialCode) {
                let value = input.value;

                // Remove all non-digit characters for comparison
                let valueDigitsOnly = value.replace(/\D/g, '');

                // Check if the input already contains the current country dial code
                if (!valueDigitsOnly.startsWith(newDialCode)) {
                    // Create regex pattern to match old dial code at the beginning
                    let oldDialCodePattern = new RegExp(`^(\\+?${currentDialCode})`);
                    let valueWithoutOldCode = value.replace(oldDialCodePattern, '').trim();

                    // Set the new value with the new dial code
                    input.value = (`+${newDialCode} ${valueWithoutOldCode}`).replace(/[-\s]/g, '');

                    // Update current dial code
                    currentDialCode = newDialCode;

                    // Simulate the change event
                    simulateChangeEvent(input);
                }
            }

            function simulateChangeEvent(input) {
                const event = new Event('change', {
                    bubbles: true,
                    cancelable: true
                });
                input.dispatchEvent(event);
            }

            input.addEventListener('blur', function () {
                input.value=(input.value).replace(/[-\s]/g, '')
                updateInputWithCountryCode(this, iti.getSelectedCountryData().dialCode);
            });

            input.addEventListener('countrychange', function () {
                let newDialCode = iti.getSelectedCountryData().dialCode;
                input.value = `+${newDialCode}`; // Set the input value to the new country code
                updateInputWithCountryCode(this, newDialCode);
            });
        }
    }

    function checkAndInitializeInputs() {
        const primaryInput = document.querySelector('#billing-wpsms\\/mobile');
        if (primaryInput) {
            initializeInput(primaryInput);
        } else {
            const inputTells = document.querySelectorAll('.wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone, #billing-phone, #wp-sms-input-mobile, .user-mobile-wrap #mobile');
            if (inputTells.length) {
                inputTells.forEach(input => {
                    initializeInput(input);
                });
            }
        }
    }

    checkAndInitializeInputs();

    const shippingCheckbox = document.querySelector('#shipping-fields .wc-block-checkout__use-address-for-billing input');
    if (shippingCheckbox) {
        shippingCheckbox.addEventListener('change', function () {
            if (document.querySelector('#billing-fields')) {
                setTimeout(checkAndInitializeInputs, 500);  // Reinitialize to check and initialize inputs again if needed
            }
        });
    }




    var inputTell = document.querySelector("#job_mobile, #_job_mobile");
    if (inputTell && !inputTell.getAttribute('placeholder')) {

        inputTell.setAttribute('dir', direction)
        window.intlTelInput(inputTell, {
            separateDialCode: false,
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
        function setDefaultCode(item){
            let iti = intlTelInput.getInstance(item);
            if(item.value==''){
                let country=iti.getSelectedCountryData();
                item.value = '+'+country.dialCode;
            }else{
                if(iti.getNumber()){
                    item.value=iti.getNumber().replace(/[-\s]/g, '')
                }else{
                    item.value=item.value.replace(/[-\s]/g, '')
                }
            }
        }
        inputTell.addEventListener('blur', function() {
            setDefaultCode(this)
        });
    }

}
