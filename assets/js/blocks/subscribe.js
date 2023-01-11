jQuery(document).ready(function () {
    wpSmsSubscribeForm.init();
});

let wpSmsSubscribeForm = {

    init: function () {
        this.subscriber = Array()

        this.setFields()
        this.gdprCheckbox()
        this.EventListener()
    },

    setFields: function () {
        this.wpSmsSubscribeOverlay = jQuery(".wpsms-subscribe__overlay")
        this.wpSmsFormSubmitButton = jQuery("#wpsms-submit")
        this.wpSmsActivation = jQuery("#activation")
        this.wpSmsSubscribeStepOne = jQuery("#wpsms-step-1")
        this.wpSmsSubscribeStepTwo = jQuery("#wpsms-step-2")
        this.wpSmsSubscribeResult = jQuery("#wpsms-result")
        this.wpSmsSubscribe = jQuery("#wpsms-subscribe")
        this.wpSmsGdprConfirmation = jQuery('#wpsms-gdpr-confirmation')
        this.wpSmsEventType = jQuery(".wpsms-subscribe-type__field__input")
        this.wpSmsFormSubmitButtonByClass = jQuery('.wpsms-button')
        this.wpSmsFormSubmit = jQuery('.wpsms-form-submit')
    },

    showProcessing: function () {
        this.wpSmsSubscribeOverlay.css('display', 'flex');
    },

    hideProcessing: function () {
        this.wpSmsSubscribeOverlay.css('display', 'none');
    },

    enableSubmitButton: function () {
        this.wpSmsFormSubmitButton.prop('disabled', false);
    },

    disableSubmitButton: function () {
        this.wpSmsFormSubmitButton.attr('disabled', 'disabled');
    },

    enableActivationButton: function () {
        this.wpSmsActivation.prop('disabled', false);
    },

    disableActivationButton: function () {
        this.wpSmsActivation.attr('disabled', 'disabled');
    },

    hideFirstStep: function () {
        this.wpSmsSubscribeStepOne.hide();
    },

    showSecondStep: function () {
        this.wpSmsSubscribeStepTwo.show();
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
        $this.disableSubmitButton()
        $this.hideMessages()
        $this.showProcessing()

        var verify = jQuery("#newsletter-form-verify").val()

        $this.subscriber['name'] = jQuery("#wpsms-name").val()
        $this.subscriber['mobile'] = jQuery("#wpsms-mobile").val()
        $this.subscriber['group_id'] = jQuery("#wpsms-groups").val()
        $this.subscriber['type'] = jQuery('.wpsms-subscribe-type__field__input:checked').val()

        $this.wpSmsSubscribe.ajaxStart(function () {
            $this.wpSmsFormSubmitButton.attr('disabled', 'disabled')
            $this.wpSmsFormSubmitButton.text(wpsms_ajax_object.loading_text)
        })

        $this.wpSmsSubscribe.ajaxComplete(function () {
            $this.disableSubmitButton()
            $this.wpSmsFormSubmitButton.text(wpsms_ajax_object.subscribe_text)
        })

        if ($this.subscriber['type'] === 'subscribe') {
            var endpointUrl = wpsms_ajax_object.rest_endpoint_url
        } else {
            var endpointUrl = wpsms_ajax_object.rest_endpoint_url + '/unsubscribe'
        }

        var data_obj = Object.assign({}, $this.subscriber)

        var ajax = jQuery.ajax({
            type: 'POST',
            url: endpointUrl,
            data: data_obj
        })

        ajax.fail(function (data) {
            var response = jQuery.parseJSON(data.responseText)
            var message = null

            $this.enableSubmitButton()
            $this.hideProcessing()

            if (typeof (response.error) != "undefined" && response.error !== null) {
                message = response.error.message;
            } else {
                message = wpsms_ajax_object.unknown_error;
            }

            $this.showMessages()
            $this.wpSmsSubscribeResult.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>')
        })

        ajax.done(function (data) {
            var message = data.message;

            $this.enableSubmitButton()
            $this.hideProcessing()
            $this.showMessages()
            $this.hideFirstStep()

            $this.wpSmsSubscribeResult.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>')

            if ($this.subscriber['type'] === 'subscribe' && verify === '1') {
                $this.showSecondStep();
            }
        })
    },

    sendActivationCode: function ($this = this) {
        $this.hideMessages()
        $this.disableActivationButton()
        $this.showProcessing()

        $this.subscriber['activation'] = jQuery("#wpsms-ativation-code").val()
        $this.disableActivationButton()
        $this.showProcessing()

        $this.wpSmsSubscribe.ajaxStart(function () {
            $this.disableActivationButton()
            $this.wpSmsActivation.text(wpsms_ajax_object.loading_text)
        })

        $this.wpSmsSubscribe.ajaxComplete(function () {
            $this.enableActivationButton()
            $this.wpSmsActivation.text(wpsms_ajax_object.activation_text)
        })

        var data_obj = Object.assign({}, $this.subscriber)

        var ajax = jQuery.ajax({
            type: 'POST',
            url: wpsms_ajax_object.rest_endpoint_url + '/verify',
            data: data_obj
        })

        ajax.fail(function (data) {
            var response = jQuery.parseJSON(data.responseText)
            var message = null

            $this.enableActivationButton()
            $this.hideProcessing()

            if (typeof (response.error) != "undefined" && response.error !== null) {
                message = response.error.message
            } else {
                message = wpsms_ajax_object.unknown_error
            }

            $this.showMessages()

            $this.wpSmsSubscribeResult.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>')
        })

        ajax.done(function (data) {
            var message = data.message

            $this.enableActivationButton()
            $this.hideProcessing()
            $this.showMessages()
            $this.hideSecondStep()

            $this.wpSmsSubscribeResult.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>')
        })
    },

    gdprCheckbox: function () {
        if (this.wpSmsGdprConfirmation.length && this.wpSmsGdprConfirmation.attr('checked')) {
            this.enableSubmitButton();
        } else {
            this.disableSubmitButton();
        }
    },

    EventListener: function ($this = this) {

        // GDPR confirmation
        // Enable and disable the form submit button by changing the status of GDPR checkbox
        $this.wpSmsGdprConfirmation.on('change', function () {
            if (this.checked) {
                $this.enableSubmitButton()
            } else {
                $this.disableSubmitButton()
            }
        })

        // Subscribe or Unsubscribe
        // Change the text of submit button based on the chosen event, Subscribe or Unsubscribe
        $this.wpSmsEventType.on('click', function () {
            $this.wpSmsFormSubmit.text(jQuery(this).data('label'))
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