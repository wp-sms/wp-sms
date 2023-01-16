jQuery(document).ready(function () {
    wpSmsSubscribeForm.init();
});

let wpSmsSubscribeForm = {

    init: function () {
        this.setFields()
        this.gdprCheckbox()
        this.EventListener()
    },

    setFields: function () {
        this.wpSmsSubscribeOverlay = jQuery(".js-wpSmsSubscribeOverlay")
        this.wpSmsFormSubmitButton = jQuery(".js-wpSmsSubmitButton")
        this.wpSmsActivation = jQuery(".js-wpSmsActivationButton")
        this.wpSmsSubscribeStepTwo = jQuery(".js-wpSmsSubscribeStepTwo")
        this.wpSmsSubscribeResult = jQuery(".js-wpSmsSubscribeMessage")
        this.wpSmsSubscriberForm = jQuery(".js-wpSmsSubscribeForm")
        this.wpSmsGdprConfirmation = jQuery('.js-wpSmsGdprConfirmation')
        this.wpSmsEventType = jQuery(".js-wpSmsSubscribeType")
        this.wpSmsFormSubmitButtonByClass = jQuery('.js-wpSmsSubscribeFormButton')
    },

    showProcessing: function () {
        this.wpSmsSubscribeOverlay.css('display', 'flex');
    },

    hideProcessing: function () {
        this.wpSmsSubscribeOverlay.css('display', 'none');
    },

    enableSubmitButton: function (element = this.wpSmsFormSubmitButton) {
        element.prop('disabled', false);
    },

    disableSubmitButton: function (element = this.wpSmsFormSubmitButton) {
        element.prop('disabled', true);
    },

    enableActivationButton: function () {
        this.wpSmsActivation.prop('disabled', false);
    },

    disableActivationButton: function () {
        this.wpSmsActivation.attr('disabled', 'disabled');
    },

    hideSecondStep: function () {
        this.wpSmsSubscribeStepTwo.hide();
    },

    showMessages: function () {
        this.wpSmsSubscribeResult.fadeIn();
    },

    hideMessages: function () {
        this.wpSmsSubscribeResult.hide();
    },

    sendSubscriptionForm: function ($this = this) {

        $this.wpSmsSubscriberForm.each(function () {

            var subscriberInfo = Array()
            var submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubscribeFormButton')
            var subscribeFormContainer = jQuery(this).parents('.js-wpSmsSubscribeFormContainer')
            var responseMessageContainer = jQuery(this).children().find(".js-wpSmsSubscribeMessage")
            var processingOverlay = jQuery(this).children().find(".js-wpSmsSubscribeOverlay")
            var subscribingFirstStep = jQuery(this).children().find(".js-wpSmsSubscribeStepOne")
            var subscribingSecondStep = jQuery(this).children().find(".js-wpSmsSubscribeStepTwo")

            submitButton.prop('disabled', true)
            responseMessageContainer.hide()
            processingOverlay.css('display', 'flex')

            var verify = jQuery(this).children().find(".newsletter-form-verify").val()

            subscriberInfo['name'] = jQuery(this).children().find(".js-wpSmsSubscriberName input").val()
            subscriberInfo['mobile'] = jQuery(this).children().find(".js-wpSmsSubscriberMobile input").val()
            subscriberInfo['group_id'] = jQuery(this).children().find(".js-wpSmsSubscriberGroupId select").val()
            subscriberInfo['type'] = jQuery(this).children().find(".js-wpSmsSubscribeType").val()

            subscribeFormContainer.ajaxStart(function () {
                submitButton.attr('disabled', 'disabled')
                submitButton.text(wpsms_ajax_object.loading_text)
            })

            subscribeFormContainer.ajaxComplete(function () {
                submitButton.prop('disabled', true)
                submitButton.text(wpsms_ajax_object.subscribe_text)
            })

            if (subscriberInfo['type'] === 'subscribe') {
                var endpointUrl = wpsms_ajax_object.rest_endpoint_url
            } else {
                var endpointUrl = wpsms_ajax_object.rest_endpoint_url + '/unsubscribe'
            }

            var data_obj = Object.assign({}, subscriberInfo)

            var ajax = jQuery.ajax({
                type: 'POST',
                url: endpointUrl,
                data: data_obj
            })

            ajax.fail(function (data) {
                var response = jQuery.parseJSON(data.responseText)
                var message = null

                submitButton.prop('disabled', false)
                processingOverlay.css('display', 'none')

                if (typeof (response.error) != "undefined" && response.error !== null) {
                    message = response.error.message;
                } else {
                    message = wpsms_ajax_object.unknown_error;
                }

                responseMessageContainer.fadeIn()
                responseMessageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>')
            })

            ajax.done(function (data) {
                var message = data.message;

                submitButton.prop('disabled', false)
                processingOverlay.css('display', 'none')
                responseMessageContainer.fadeIn()
                subscribingFirstStep.hide()


                responseMessageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>')

                if (subscriberInfo['type'] === 'subscribe' && verify === '1') {
                    subscribingSecondStep.show()
                }
            })

        })

    },

    sendActivationCode: function ($this = this) {

        $this.wpSmsSubscriberForm.each(function () {

            var submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubscribeFormButton')
            var activationButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsActivationButton')
            var subscribeFormContainer = jQuery(this).parents('.js-wpSmsSubscribeFormContainer')
            var responseMessageContainer = jQuery(this).children().find(".js-wpSmsSubscribeMessage")
            var processingOverlay = jQuery(this).children().find(".js-wpSmsSubscribeOverlay")
            var subscribingFirstStep = jQuery(this).children().find(".js-wpSmsSubscribeStepOne")
            var subscribingSecondStep = jQuery(this).children().find(".js-wpSmsSubscribeStepTwo")

            activationButton.prop('disabled', true)
            responseMessageContainer.hide()
            processingOverlay.css('display', 'flex')

            subscriberInfo['activation'] = jQuery(this).children().find(".js-wpSmsActivationCode").val()

            subscribeFormContainer.ajaxStart(function () {
                activationButton.prop('disabled', true)
                activationButton.text(wpsms_ajax_object.loading_text)
            })

            subscribeFormContainer.ajaxComplete(function () {
                activationButton.prop('disabled', false)
                activationButton.text(wpsms_ajax_object.activation_text)
            })

            var data_obj = Object.assign({}, subscriberInfo)

            var ajax = jQuery.ajax({
                type: 'POST',
                url: wpsms_ajax_object.rest_endpoint_url + '/verify',
                data: data_obj
            })

            ajax.fail(function (data) {
                var response = jQuery.parseJSON(data.responseText)
                var message = null

                activationButton.prop('disabled', false)
                processingOverlay.css('display', 'none')

                if (typeof (response.error) != "undefined" && response.error !== null) {
                    message = response.error.message
                } else {
                    message = wpsms_ajax_object.unknown_error
                }

                responseMessageContainer.fadeIn()

                responseMessageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>')
            })

            ajax.done(function (data) {
                var message = data.message

                activationButton.prop('disabled', false)
                processingOverlay.css('display', 'none')
                responseMessageContainer.fadeIn()
                subscribingSecondStep.hide()

                responseMessageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>')
            })

        })

    },

    gdprCheckbox: function ($this = this) {
        $this.wpSmsGdprConfirmation.each(function () {
            if (jQuery(this).length && jQuery(this).attr('checked')) {
                $this.enableSubmitButton()
            } else {
                $this.disableSubmitButton()
            }
        })
    },

    EventListener: function ($this = this) {

        // GDPR confirmation
        // Enable and disable the form submit button by changing the status of GDPR checkbox
        $this.wpSmsGdprConfirmation.each(function () { // todo type value must be checked
            jQuery(this).on('change', function () {
                var submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubscribeFormButton')
                if (this.checked && $this.wpSmsEventType.val()) {
                    $this.enableSubmitButton(submitButton)
                } else {
                    $this.disableSubmitButton(submitButton)
                }
            })
        })

        // Subscribe or Unsubscribe
        // Change the text of submit button based on the chosen event, Subscribe or Unsubscribe
        $this.wpSmsEventType.each(function () {
            jQuery(this).on('click', function () {
                jQuery(this)
                    .parents('.js-wpSmsSubscribeFormField')
                    .nextAll('.js-wpSmsSubscribeFormButton').first()
                    .text(jQuery(this).data('label'))
            })
        })

        // Submitting the form
        $this.wpSmsFormSubmitButtonByClass.on('click', function () {
            if (jQuery(this).hasClass('wpsms-form-submit')) {
                $this.sendSubscriptionForm()
            }

            if (jQuery(this).hasClass('wpsms-activation-submit')) {
                $this.sendActivationCode()
            }
        })

    }

}