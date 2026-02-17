jQuery(document).ready(function () {

    setTimeout(init, 1500);
});

function init() {
    const body = document.body;
    const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';

    const { only_countries, preferred_countries } = wp_sms_intel_tel_input;
    let defaultCountry;

    const isWooCommerceCheckoutBlock = document.querySelector('.wc-block-checkout ');

    if (only_countries.length > 0) {
        if (preferred_countries.length > 0 && preferred_countries.every(country => only_countries.includes(country))) {
            defaultCountry = preferred_countries[0];
        } else {
            defaultCountry = only_countries[0];
        }
    } else {
        defaultCountry = preferred_countries.length > 0 ? preferred_countries[0] : 'us';
    }

    const sameAddressForBillingCheckbox = document.querySelector('#shipping-fields .wc-block-checkout__use-address-for-billing input');

    const useFullscreenPopupOption = typeof navigator !== "undefined" && typeof window !== "undefined" ? (
        //* We cannot just test screen size as some smartphones/website meta tags will report desktop resolutions.
        //* Note: to target Android Mobiles (and not Tablets), we must find 'Android' and 'Mobile'
        /Android.+Mobile|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
            navigator.userAgent
        ) || window.innerWidth <= 500
    ) : false;

    let allIntlTelInputs = [];

    // Initialize input fields with IntlTelInput
    function initializeInputs(inputTells) {
        // Remove all IntlTelInput fields
        allIntlTelInputs.forEach(input => input.destroy());
        allIntlTelInputs = [];

        // And re-instantiate them again
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
                    hiddenInput.name = sameAddressForBillingCheckbox && sameAddressForBillingCheckbox.checked ? 'shipping_phone' : 'billing_phone';
                    hiddenInput.value = inputTells[i].value;
                    inputTells[i].parentNode.insertBefore(hiddenInput, inputTells[i].nextSibling);
                }

                setDefaultCode(inputTells[i], iti);

                // Prevent events from getting registered multiple times on the same element
                inputTells[i].intlTelInput = iti;
                if (typeof inputTells[i].hasCheckoutBlockBlur == 'undefined' || !inputTells[i].hasCheckoutBlockBlur) {
                    inputTells[i].addEventListener('blur', function () {
                        if (typeof this.intlTelInput == 'undefined') {
                            return;
                        }

                        fillHiddenPhones(this.intlTelInput.getNumber());

                        setDefaultCode(this, this.intlTelInput);

                        if (isWooCommerceCheckoutBlock) {
                            let addressObject = { 'phone': this.intlTelInput.getNumber() };
                            if (wp_sms_intel_tel_input.add_mobile_field === 'add_mobile_field_in_wc_billing') {
                                addressObject = { 'phone': this.intlTelInput.getNumber(), 'mobile': this.intlTelInput.getNumber(), 'wpsms/mobile': this.intlTelInput.getNumber() };
                            }

                            if (wp_sms_intel_tel_input.wc_ship_to_destination === 'billing_only' || (sameAddressForBillingCheckbox && sameAddressForBillingCheckbox.checked)) {
                                wp.data.dispatch('wc/store/cart').setShippingAddress(addressObject);
                                wp.data.dispatch('wc/store/cart').setBillingAddress(addressObject);
                            } else {
                                wp.data.dispatch('wc/store/cart').setBillingAddress(addressObject);
                            }
                        }
                    });
                    inputTells[i].hasCheckoutBlockBlur = true;

                    fillHiddenPhones(inputTells[i].value);
                }

                allIntlTelInputs.push(iti);
            }
        }
    }

    function setDefaultCode(item, intlTelInputElement) {
        if (item.value == '') {
            let country = intlTelInputElement.getSelectedCountryData();
            item.value = '+' + country.dialCode;
        } else {
            if (intlTelInputElement.getNumber()) {
                item.value = intlTelInputElement.getNumber().replace(/[-\s]/g, '')
            } else {
                item.value = item.value.replace(/[-\s]/g, '')
            }
        }
    }

    // Check and initialize the main input fields
    function checkAndInitializeInputs() {
        let inputTells = [];
        if (isWooCommerceCheckoutBlock) {
            // WooCommerce checkout block

            let inputIds = wp_sms_intel_tel_input.mobile_field_id;

            // Initialize IntlTelInput on shipping phone fields if "Use same address for billing" is checked
            if (sameAddressForBillingCheckbox && sameAddressForBillingCheckbox.checked) {
                inputIds = inputIds.replaceAll('billing', 'shipping');
            }

            inputTells = document.querySelectorAll(inputIds);
        } else {
            // Classic checkout

            inputTells = document.querySelectorAll('#billing-wpsms\\/mobile,#billing-wpsms-mobile');
            if (!inputTells.length) {
                inputTells = document.querySelectorAll(".wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone,#billing-phone , #wp-sms-input-mobile, .user-mobile-wrap #mobile");
            }
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

        inputTell.addEventListener('blur', function () {
            setDefaultCode(this, iti_job);
        });
    }

    // Handle the change event for "Use same address for billing" checkbox
    if (sameAddressForBillingCheckbox) {
        sameAddressForBillingCheckbox.addEventListener('change', function () {
            setTimeout(checkAndInitializeInputs, 500);
        });
    }

    // Fill hidden phone inputs value on updated_checkout and blur
    function fillHiddenPhones(value) {
        let inputId = 'billing_phone';
        if (isWooCommerceCheckoutBlock && sameAddressForBillingCheckbox && sameAddressForBillingCheckbox.checked) {
            inputId = 'shipping_phone';
        }

        const hiddenInputs = document.querySelectorAll(`input[type="hidden"][name="${inputId}"],input[type="hidden"][name="mobile"]`);
        for (var i = 0; i < hiddenInputs.length; i++) {
            hiddenInputs[i].value = value;
        }
    }
}
