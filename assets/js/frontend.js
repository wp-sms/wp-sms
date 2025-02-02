/******/ (() => { // webpackBootstrap
jQuery(document).ready(function () {
  wpSmsSubscribeForm.init();
  jQuery('.wpsms-sendSmsForm').each(function () {
    wpSmsSendSmsBlockForm.init(this);
  });
});
var wpSmsSubscribeForm = {
  init: function init() {
    this.info = [];
    this.setFields();
    this.setEventListener();
  },
  getGroupId: function getGroupId(element) {
    var group_id = [];
    var groupIdCheckboxes = element.find('input[name="group_id_checkbox"]');
    var groupIdSelect = element.find('select[name="group_id_select"]');
    groupIdCheckboxes.each(function () {
      if (this.checked) {
        group_id.push(this.value);
      }
    });
    if (groupIdSelect && groupIdSelect.val()) {
      group_id.push(groupIdSelect.val());
    }

    // Return an empty array instead of undefined
    return group_id.length ? group_id : [];
  },
  setFields: function setFields() {
    this.wpSmsGdprCheckbox = jQuery('.js-wpSmsGdprConfirmation');
    this.wpSmsEventType = jQuery(".js-wpSmsSubscribeType");
    this.wpSmsSubmitTypeButton = jQuery('.js-wpSmsSubmitTypeButton');
    this.mandatoryVerify = jQuery('.js-wpSmsMandatoryVerify').val();
  },
  sendSubscriptionForm: function sendSubscriptionForm(element) {
    var $this = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : this;
    var submitButton = element.find('.js-wpSmsSubmitButton');
    var messageContainer = element.find('.js-wpSmsSubscribeMessage');
    var processingOverlay = element.find('.js-wpSmsSubscribeOverlay');
    var firstStep = element.find('.js-wpSmsSubscribeStepOne');
    var firstStepSubmitButton = element.find('.js-wpSmsSubmitButton');
    var secondStep = element.find('.js-wpSmsSubscribeStepTwo');
    var customFields = element.find('.js-wpSmsSubscriberCustomFields');
    var mobileField = element.find(".js-wpSmsSubscriberMobile");
    submitButton.prop('disabled', true);
    messageContainer.hide();
    processingOverlay.css('display', 'flex');
    var formData = new FormData();
    formData.append('name', element.find(".js-wpSmsSubscriberName input").val());
    formData.append('mobile', mobileField.find(".iti--show-flags").length > 0 ? mobileField.find("input.wp-sms-input-mobile").val() : mobileField.find("input").val());
    var groupIds = this.getGroupId(element);
    if (groupIds.length > 0) {
      formData.append('group_id', JSON.stringify(groupIds));
    }
    formData.append('type', element.find(".js-wpSmsSubscribeType:checked").val());
    customFields.each(function () {
      var label = jQuery(this).data('field-name');
      var value = jQuery(this).find('input').val();
      formData.append("custom_fields[".concat(label, "]"), value);
    });
    var endpointUrl = formData.get('type') === 'subscribe' ? wpsms_ajax_object.subscribe_ajax_url : wpsms_ajax_object.unsubscribe_ajax_url;
    jQuery.ajax({
      type: 'POST',
      url: endpointUrl,
      contentType: false,
      processData: false,
      data: formData,
      beforeSend: function beforeSend() {
        submitButton.prop('disabled', true).text(wpsms_ajax_object.loading_text);
      },
      complete: function complete() {
        submitButton.prop('disabled', false).text(wpsms_ajax_object.subscribe_text);
      },
      success: function success(data) {
        submitButton.prop('disabled', false);
        processingOverlay.hide();
        messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + data.data + '</span>');
        firstStep.hide();
        if (formData.get('type') === 'subscribe' && $this.mandatoryVerify === '1') {
          firstStepSubmitButton.prop('disabled', true);
          secondStep.show();
        }
      },
      error: function error(data) {
        submitButton.prop('disabled', false);
        processingOverlay.hide();
        messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + data.responseJSON.data + '</span>');
      }
    });
    $this.info = Object.fromEntries(formData.entries());
  },
  sendActivationCode: function sendActivationCode(element) {
    var $this = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : this;
    var activationButton = element.find('.js-wpSmsActivationButton');
    var subscribeFormContainer = element.parents('.js-wpSmsSubscribeFormContainer');
    var messageContainer = element.find('.js-wpSmsSubscribeMessage');
    var processingOverlay = element.find('.js-wpSmsSubscribeOverlay');
    var secondStep = element.find('.js-wpSmsSubscribeStepTwo');
    activationButton.prop('disabled', true);
    messageContainer.hide();
    processingOverlay.css('display', 'flex');

    // Update info with activation code
    $this.info.activation = element.find('.js-wpSmsActivationCode').val();

    // Create a new FormData object
    var formData = new FormData();

    // Append each key-value pair from $this.info into the FormData object
    for (var key in $this.info) {
      if ($this.info.hasOwnProperty(key)) {
        formData.append(key, $this.info[key]);
      }
    }
    jQuery.ajax({
      type: 'POST',
      url: wpsms_ajax_object.verify_subscribe_ajax_url,
      contentType: false,
      processData: false,
      data: formData,
      beforeSend: function beforeSend() {
        activationButton.prop('disabled', true).text(wpsms_ajax_object.loading_text);
      },
      complete: function complete() {
        activationButton.prop('disabled', false).text(wpsms_ajax_object.activation_text);
      },
      success: function success(data) {
        activationButton.prop('disabled', false);
        processingOverlay.hide();
        messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + data.data + '</span>');
        secondStep.hide();
      },
      error: function error(data) {
        activationButton.prop('disabled', false);
        processingOverlay.hide();
        messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + data.responseJSON.data + '</span>');
      }
    });
  },
  setEventListener: function setEventListener() {
    var $this = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : this;
    this.wpSmsGdprCheckbox.on('change', function () {
      var submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first();
      submitButton.prop('disabled', !this.checked);
    });
    this.wpSmsEventType.on('click', function () {
      var submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first();
      submitButton.text(jQuery(this).data('label'));
    });
    this.wpSmsSubmitTypeButton.on('click', function (event) {
      event.preventDefault();
      if (jQuery(this).hasClass('js-wpSmsSubmitButton')) {
        $this.sendSubscriptionForm(jQuery(this).parents('.js-wpSmsSubscribeForm'));
      }
      if (jQuery(this).hasClass('js-wpSmsActivationButton')) {
        $this.sendActivationCode(jQuery(this).parents('.js-wpSmsSubscribeForm'));
      }
    });
  }
};
var wpSmsSendSmsBlockForm = {
  init: function init(SBForm) {
    SBForm = jQuery(SBForm);
    this.setSendSmsBlockFields(SBForm);
  },
  setSendSmsBlockFields: function setSendSmsBlockFields(SBForm) {
    var SBSubscriberGroup = SBForm.find('input[name=subscriberGroup]');
    var SBSubmit = SBForm.find('input[type=submit]');
    var SBMessage = SBForm.find('textarea.wpsms-sendSmsForm__messageField');
    var SBReceiver = SBForm.find('input[name=receiver]');
    var SBPhoneNumber = SBForm.find('input.wpsms-sendSmsForm__receiverField');
    var SBMessageAlert = SBForm.find('p.wpsms-sendSmsForm__messageField__alert');
    var SBResult = SBForm.find('div.wpsms-sendSmsForm__resultMessage');
    var SBOverlay = SBForm.find('div.wpsms-sendSmsForm__overlay');
    var SBMaxCount = SBMessage.data('max');
    var elements = {
      SBSubscriberGroup: SBSubscriberGroup,
      SBSubmit: SBSubmit,
      SBMessage: SBMessage,
      SBReceiver: SBReceiver,
      SBPhoneNumber: SBPhoneNumber,
      SBMessageAlert: SBMessageAlert,
      SBResult: SBResult,
      SBOverlay: SBOverlay,
      SBMaxCount: SBMaxCount
    };
    this.setSendSmsBlockEventListeners(elements);
  },
  setSendSmsBlockEventListeners: function setSendSmsBlockEventListeners(elements) {
    jQuery(elements.SBSubmit).on('click', function (event) {
      event.preventDefault();
      var formData = new FormData();
      formData.append('sender', wpsms_ajax_object.sender);
      formData.append('recipients', elements.SBReceiver.val());
      formData.append('message', elements.SBMessage.val());
      formData.append('group_ids', elements.SBSubscriberGroup.val());
      formData.append('numbers', elements.SBPhoneNumber.val());
      formData.append('maxCount', elements.SBMaxCount);
      jQuery.ajax({
        url: wpsms_ajax_object.front_sms_endpoint_url,
        method: 'POST',
        contentType: false,
        cache: false,
        processData: false,
        data: formData,
        beforeSend: function beforeSend() {
          jQuery(elements.SBResult).text('').fadeOut().removeClass('failed success');
          jQuery(elements.SBOverlay).fadeIn();
        },
        success: function success(data) {
          jQuery(elements.SBResult).text(data.data).fadeIn().addClass('success');
          jQuery(elements.SBMessage).val('').trigger('input');
          jQuery(elements.SBOverlay).fadeOut();
        },
        error: function error(data) {
          var message = data.responseJSON.data && data.responseJSON.data.message ? data.responseJSON.data.message : data.responseJSON.data || 'An unexpected error occurred.';
          jQuery(elements.SBResult).text(message).fadeIn().addClass('failed');
          jQuery(elements.SBOverlay).fadeOut();
        }
      });
    });
    jQuery(elements.SBMessage).on('input', function () {
      var currentCharacterCount = jQuery(this).val().length;
      var remainingCharacterCount = elements.SBMaxCount - currentCharacterCount;
      if (currentCharacterCount >= elements.SBMaxCount - 8) {
        jQuery(elements.SBMessageAlert).fadeIn();
        jQuery(elements.SBMessageAlert).find('span').text(remainingCharacterCount);
      } else {
        jQuery(elements.SBMessageAlert).fadeOut();
      }
    });
  }
};
/******/ })()
;