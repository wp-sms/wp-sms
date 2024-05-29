jQuery(document).ready(function () {

    setTimeout(init, 1500);
});


function init() {
    const body = document.body;
    const direction = body.classList.contains('rtl') ? 'rtl' : 'ltr';

    // Initialize input fields with intlTelInput

    function initializeInputs(inputTells) {
        for (var i = 0; i < inputTells.length; i++) {
              if (inputTells[i] && inputTells[i].nodeName === 'INPUT') {
                 inputTells[i].setAttribute('dir', direction);
                window.intlTelInput(inputTells[i], {
                    separateDialCode: false,
                    allowDropdown: true,
                    strictMode: true,
                    onlyCountries: wp_sms_intel_tel_input.only_countries,
                    countryOrder: wp_sms_intel_tel_input.preferred_countries,
                     nationalMode: true,
                    useFullscreenPopup: false,
                    dropdownContainer: body.classList.contains('rtl') ? null : body,
                    utilsScript: wp_sms_intel_tel_input.util_js,
                    hiddenInput: () => ({ phone: inputTells[i].name}),
                    formatOnDisplay: false,
                    initialCountry: wp_sms_intel_tel_input.only_countries.length > 0 ? wp_sms_intel_tel_input.only_countries[0] :  'us'
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
                  setDefaultCode(inputTells[i]);
                  inputTells[i].addEventListener('blur', function() {
                      setDefaultCode(this)
                  });
            }

         }
    }

    // Check and initialize the main input fields
    function checkAndInitializeInputs() {
        const primaryInput = document.querySelectorAll('#billing-wpsms\\/mobile');
        const isWooCommerceCheckoubBlock = document.querySelector('.wc-block-checkout ');
        if (isWooCommerceCheckoubBlock) {
            return null;
        }
        if (!primaryInput.length) {
            inputTells = document.querySelectorAll(".wp-sms-input-mobile, .wp-sms-input-mobile #billing_phone,#billing-phone , #wp-sms-input-mobile, .user-mobile-wrap #mobile");
        }
        initializeInputs(inputTells);
    }

    checkAndInitializeInputs();

    // Additional specific input field initialization
    var inputTell = document.querySelector("#job_mobile, #_job_mobile");
    if (inputTell && !inputTell.getAttribute('placeholder')) {
        inputTell.setAttribute('dir', direction)
        window.intlTelInput(inputTell, {
            autoInsertDialCode: true,
            autoPlaceholder: "aggressive",
            allowDropdown: true,
            strictMode: true,
            useFullscreenPopup: false,
            dropdownContainer: body.classList.contains('rtl') ? null : body,
            onlyCountries: wp_sms_intel_tel_input.only_countries,
            countryOrder: wp_sms_intel_tel_input.preferred_countries,
            autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
            nationalMode: wp_sms_intel_tel_input.national_mode,
            utilsScript: wp_sms_intel_tel_input.util_js,
            formatOnDisplay: false,
            initialCountry: wp_sms_intel_tel_input.only_countries.length > 0 ? wp_sms_intel_tel_input.only_countries[0] :  'us'
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

    // Handle the change event for the checkbox
    const shippingCheckbox = document.querySelector('#shipping-fields .wc-block-checkout__use-address-for-billing input');
    if (shippingCheckbox) {
        shippingCheckbox.addEventListener('change', function() {
            if (document.querySelector('#billing-fields')) {
                setTimeout(checkAndInitializeInputs, 500);
            }
        });
    }
}