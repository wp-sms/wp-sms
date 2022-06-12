import {gdprCheckbox, sendActivationForm, sendSubscribeForm} from "./utilities";
import {disableSubmitBtn, enableSubmitBtn} from "./processing";

gdprCheckbox();

jQuery("#wpsms-gdpr-confirmation").on('change', function () {
  if (this.checked) {
    enableSubmitBtn();
  } else {
    disableSubmitBtn();
  }
});

jQuery(".wpsms-subscribe-type__field__input").on('click', function () {
  jQuery('.wpsms-form-submit').text(jQuery(this).data('label'));
})

jQuery('.wpsms-button').on('click', function () {
  if (jQuery(this).hasClass('wpsms-form-submit')) {
    sendSubscribeForm();
  }

  if (jQuery(this).hasClass('wpsms-activation-submit')) {
    sendActivationForm();
  }
});


