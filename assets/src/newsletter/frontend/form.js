import {gdprCheckbox, sendActivationForm, sendSubscribeForm} from "./utilities";

gdprCheckbox();

jQuery('.wpsms-button').on('click', function () {
  if (jQuery(this).hasClass('wpsms-form-submit')) {
    sendSubscribeForm();
  }

  if (jQuery(this).hasClass('wpsms-activation-submit')) {
    sendActivationForm();
  }
});


