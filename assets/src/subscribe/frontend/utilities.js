import {
  disableActivationBtn,
  disableSubmitBtn, enableActivationBtn,
  enableSubmitBtn,
  hideFirstStep,
  hideProcessing, hideSecondStep,
  showProcessing,
  showSecondStep
} from "./processing";
import {hideMessages, ShowMessages} from "./messages";
const subscriber = Array();
export function sendSubscribeForm() {
  disableSubmitBtn();
  hideMessages();
  showProcessing();
  var verify = jQuery("#newsletter-form-verify").val();

  subscriber['name'] = jQuery("#wpsms-name").val();
  subscriber['mobile'] = jQuery("#wpsms-mobile").val();
  subscriber['group_id'] = jQuery("#wpsms-groups").val();
  subscriber['type'] = jQuery('input[name=subscribe_type]:checked').val();

  jQuery("#wpsms-subscribe").ajaxStart(function () {
    jQuery("#wpsms-submit").attr('disabled', 'disabled');
    jQuery("#wpsms-submit").text(wpsms_ajax_object.loading_text);
  });

  jQuery("#wpsms-subscribe").ajaxComplete(function () {
    disableSubmitBtn();
    jQuery("#wpsms-submit").text(wpsms_ajax_object.subscribe_text);
  });

  if (subscriber['type'] === 'subscribe') {
    var method = 'POST';
  } else {
    var method = 'DELETE';
  }

  var data_obj = Object.assign({}, subscriber);
  var ajax = jQuery.ajax({
    type: method,
    url: wpsms_ajax_object.ajaxurl,
    data: data_obj
  });

  ajax.fail(function (data) {
    var response = jQuery.parseJSON(data.responseText);
    var message = null;

    enableSubmitBtn();
    hideProcessing();

    if (typeof (response.error) != "undefined" && response.error !== null) {
      message = response.error.message;
    } else {
      message = wpsms_ajax_object.unknown_error;
    }

    ShowMessages();
    jQuery("#wpsms-result").html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>');
  });
  ajax.done(function (data) {
    var response = data;
    var message = response.message;

    enableSubmitBtn();
    hideProcessing();
    ShowMessages();
    hideFirstStep();
    jQuery("#wpsms-result").html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>');
    if (subscriber['type'] === 'subscribe' && verify === '1') {
      showSecondStep();
    }
  });
}

export function sendActivationForm() {
  hideMessages();
  disableActivationBtn();
  showProcessing();

  subscriber['activation'] = jQuery("#wpsms-ativation-code").val();
  disableActivationBtn();
  showProcessing();

  jQuery("#wpsms-subscribe").ajaxStart(function () {
    disableActivationBtn();
    jQuery("#activation").text(wpsms_ajax_object.loading_text);
  });

  jQuery("#wpsms-subscribe").ajaxComplete(function () {
    enableActivationBtn();
    jQuery("#activation").text(wpsms_ajax_object.activation_text);
  });

  var data_obj = Object.assign({}, subscriber);
  var ajax = jQuery.ajax({
    type: 'PUT',
    url: wpsms_ajax_object.ajaxurl,
    data: data_obj
  });
  ajax.fail(function (data) {
    var response = jQuery.parseJSON(data.responseText);
    var message = null;

    enableActivationBtn();
    hideProcessing();

    if (typeof (response.error) != "undefined" && response.error !== null) {
      message = response.error.message;
    } else {
      message = wpsms_ajax_object.unknown_error;
    }

    ShowMessages();
    jQuery("#wpsms-result").html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>');
  });
  ajax.done(function (data) {
    var response = data;
    var message = response.message;

    enableActivationBtn();
    showProcessing();
    ShowMessages();
    hideSecondStep();
    jQuery("#wpsms-result").html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>');
  });
}

export function gdprCheckbox() {
  if (jQuery('#wpsms-gdpr-confirmation').length) {
    if (jQuery('#wpsms-gdpr-confirmation').attr('checked')) {
      enableSubmitBtn();
    } else {
      disableSubmitBtn();
    }
  }
}
