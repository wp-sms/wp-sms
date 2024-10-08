jQuery(document).ready(function () {

    setTimeout(init, 1500);
});


function init() {
    const body = document.body;
    const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';

    const { only_countries, preferred_countries } = wp_sms_intel_tel_input;
    let defaultCountry;

    if (only_countries.length > 0) {
        if (preferred_countries.length > 0 && preferred_countries.every(country => only_countries.includes(country))) {
            defaultCountry = preferred_countries[0];
        } else {
            defaultCountry = only_countries[0];
        }
    } else {
        defaultCountry = preferred_countries.length > 0 ? preferred_countries[0] : 'us';
    }

    // Initialize input fields with intlTelInput

    const useFullscreenPopupOption = typeof navigator !== "undefined" && typeof window !== "undefined" ? (
        //* We cannot just test screen size as some smartphones/website meta tags will report desktop resolutions.
        //* Note: to target Android Mobiles (and not Tablets), we must find 'Android' and 'Mobile'
        /Android.+Mobile|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
            navigator.userAgent
        ) || window.innerWidth <= 500
    ) : false;

    function initializeInputs(inputTells) {
        const isWooCommerceCheckoutBlock = document.querySelector('.wc-block-checkout ');

        for (var i = 0; i < inputTells.length; i++) {
            if (inputTells[i] && inputTells[i].nodeName === 'INPUT') {
                inputTells[i].setAttribute('dir', direction);
                let iti = window.intlTelInput(inputTells[i], {
                    separateDialCode: false,
                    allowDropdown: true,
                    strictMode: true,
                    onlyCountries: wp_sms_intel_tel_input.only_countries,
                    countryOrder: wp_sms_intel_tel_input.preferred_countries,
                    nationalMode: true,
                    useFullscreenPopup: useFullscreenPopupOption,
                    dropdownContainer: body.classList.contains('rtl') ? null : body,
                    utilsScript: wp_sms_intel_tel_input.util_js,
                    hiddenInput: () => ({ phone: inputTells[i].name }),
                    formatOnDisplay: false,
                    initialCountry: defaultCountry
                });

                // Manually create a hidden input field for the phone number in WooCommerce's checkout block
                if (isWooCommerceCheckoutBlock) {
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'billing_phone';
                    hiddenInput.value = inputTells[i].value;
                    inputTells[i].parentNode.insertBefore(hiddenInput, inputTells[i].nextSibling);
                }

                function setDefaultCode(item) {
                    if (item.value == '') {
                        let country = iti.getSelectedCountryData();
                        item.value = '+' + country.dialCode;
                    } else {
                        if (iti.getNumber()) {
                            item.value = iti.getNumber().replace(/[-\s]/g, '')
                        } else {
                            item.value = item.value.replace(/[-\s]/g, '')
                        }
                    }
                }
                setDefaultCode(inputTells[i]);

                inputTells[i].addEventListener('blur', function () {
                    setDefaultCode(this)

                    if (isWooCommerceCheckoutBlock) {
                        wp.data.dispatch('wc/store/cart').setBillingAddress({ 'phone': iti.getNumber() });
                    }
                });
            }

        }
    }

    // Check and initialize the main input fields
    function checkAndInitializeInputs() {
        const primaryInput = document.querySelectorAll('#billing-wpsms\\/mobile');
        const isWooCommerceCheckoutBlock = document.querySelector('.wc-block-checkout ');
        if (isWooCommerceCheckoutBlock) {
            inputTells = document.querySelectorAll('#billing-phone');
        } else if (!primaryInput.length) {
            inputTells = document.querySelectorAll(".wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone,#billing-phone , #wp-sms-input-mobile, .user-mobile-wrap #mobile");
        }
        initializeInputs(inputTells);
    }

    checkAndInitializeInputs();

    // Additional specific input field initialization
    var inputTell = document.querySelector("#job_mobile, #_job_mobile");
    if (inputTell && !inputTell.getAttribute('placeholder')) {
        inputTell.setAttribute('dir', direction)
        let iti_job = window.intlTelInput(inputTell, {
            autoInsertDialCode: true,
            autoPlaceholder: "aggressive",
            allowDropdown: true,
            strictMode: true,
            useFullscreenPopup: false,
            dropdownContainer: body.classList.contains('rtl') ? null : body,
            onlyCountries: wp_sms_intel_tel_input.only_countries,
            countryOrder: wp_sms_intel_tel_input.preferred_countries,
            autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
            nationalMode: true,
            utilsScript: wp_sms_intel_tel_input.util_js,
            formatOnDisplay: false,
            initialCountry: defaultCountry
        });
        function setDefaultCode(item) {
            if (item.value == '') {
                let country = iti_job.getSelectedCountryData();
                item.value = '+' + country.dialCode;
            } else {
                if (iti_job.getNumber()) {
                    item.value = iti_job.getNumber().replace(/[-\s]/g, '')
                } else {
                    item.value = item.value.replace(/[-\s]/g, '')
                }
            }
        }
        inputTell.addEventListener('blur', function () {
            setDefaultCode(this)
        });
    }

    // Handle the change event for the checkbox
    const shippingCheckbox = document.querySelector('#shipping-fields .wc-block-checkout__use-address-for-billing input');
    if (shippingCheckbox) {
        shippingCheckbox.addEventListener('change', function () {
            if (document.querySelector('#billing-fields')) {
                setTimeout(checkAndInitializeInputs, 500);
            }
        });
    }
}