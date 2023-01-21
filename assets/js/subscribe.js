jQuery(document).ready(function () {
    wpSmsSubscribeForm.init();
});

let wpSmsSubscribeForm = {

    init: function () {
        this.info = Array()

        this.setFields()
        this.EventListener()
    },

    setFields: function () {
        this.wpSmsGdprCheckbox = jQuery('.js-wpSmsGdprConfirmation')
        this.wpSmsEventType = jQuery(".js-wpSmsSubscribeType")
        this.wpSmsSubmitTypeButton = jQuery('.js-wpSmsSubmitTypeButton')
        this.mandatoryVerify = jQuery('.js-wpSmsMandatoryVerify').val()
    },

    sendSubscriptionForm: function (element, $this = this) {
        let submitButton = element.children().find('.js-wpSmsSubmitButton')
        let messageContainer = element.children().find('.js-wpSmsSubscribeMessage')
        let processingOverlay = element.children().find('.js-wpSmsSubscribeOverlay')
        let firstStep = element.children().find('.js-wpSmsSubscribeStepOne')
        let secondStep = element.children().find('.js-wpSmsSubscribeStepTwo')
        let customFields = element.children().find('.js-wpSmsSubscriberCustomFields')

        submitButton.prop('disabled', true)
        messageContainer.hide()
        processingOverlay.css('display', 'flex')

        let requestBody = {
            name: element.children().find(".js-wpSmsSubscriberName input").val(),
            mobile: element.children().find(".js-wpSmsSubscriberMobile input").val(),
            group_id: element.children().find(".js-wpSmsSubscriberGroupId select").val(),
            type: element.children().find(".js-wpSmsSubscribeType:checked").val()
        }

        if (customFields.length) {
            var fields = {}

            customFields.each(function (index, item) {
                var label = jQuery(item).data('field-name')
                var value = jQuery(item).find('input').val()

                fields[label] = value
            })

            requestBody.custom_fields = fields
        }

        element.ajaxStart(function () {
            submitButton.attr('disabled', 'disabled')
            submitButton.text(wpsms_ajax_object.loading_text)
        })

        element.ajaxComplete(function () {
            submitButton.prop('disabled', true)
            submitButton.text(wpsms_ajax_object.subscribe_text)
        })

        if (requestBody.type === 'subscribe') {
            var endpointUrl = wpsms_ajax_object.rest_endpoint_url
        } else {
            var endpointUrl = wpsms_ajax_object.rest_endpoint_url + '/unsubscribe'
        }

        var ajax = jQuery.ajax({
            type: 'POST',
            url: endpointUrl,
            contentType: 'application/json',
            data: JSON.stringify(requestBody)
        })

        ajax.fail(function (data) {
            var response = JSON.parse(data.responseText)
            var message = null

            submitButton.prop('disabled', false)
            processingOverlay.css('display', 'none')

            if (typeof (response.error) != "undefined" && response.error !== null) {
                message = response.error.message;
            } else {
                message = wpsms_ajax_object.unknown_error;
            }

            messageContainer.fadeIn()
            messageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>')
        })

        ajax.done(function (data) {
            var message = data.message;

            submitButton.prop('disabled', false)
            processingOverlay.css('display', 'none')
            messageContainer.fadeIn()
            firstStep.hide()

            messageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>')

            if (requestBody.type === 'subscribe' && $this.mandatoryVerify === '1') {
                secondStep.show()
            }
        })

        $this.info = requestBody

    },

    sendActivationCode: function (element, $this = this) {

        let activationButton = element.children().find('.js-wpSmsActivationButton')
        let subscribeFormContainer = element.parents('.js-wpSmsSubscribeFormContainer')
        let messageContainer = element.children().find('.js-wpSmsSubscribeMessage')
        let processingOverlay = element.children().find('.js-wpSmsSubscribeOverlay')
        let secondStep = element.children().find('.js-wpSmsSubscribeStepTwo')

        activationButton.prop('disabled', true)
        messageContainer.hide()
        processingOverlay.css('display', 'flex')

        $this.info.activation = element.children().find('.js-wpSmsActivationCode').val()

        subscribeFormContainer.ajaxStart(function () {
            activationButton.prop('disabled', true)
            activationButton.text(wpsms_ajax_object.loading_text)
        })

        subscribeFormContainer.ajaxComplete(function () {
            activationButton.prop('disabled', false)
            activationButton.text(wpsms_ajax_object.activation_text)
        })

        var ajax = jQuery.ajax({
            type: 'POST',
            url: wpsms_ajax_object.rest_endpoint_url + '/verify',
            contentType: 'application/json',
            data: JSON.stringify($this.info)
        })

        ajax.fail(function (data) {
            var response = JSON.parse(data.responseText)
            var message = null

            activationButton.prop('disabled', false)
            processingOverlay.css('display', 'none')

            if (typeof (response.error) != "undefined" && response.error !== null) {
                message = response.error.message
            } else {
                message = wpsms_ajax_object.unknown_error
            }

            messageContainer.fadeIn()

            messageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + message + '</div>')
        })

        ajax.done(function (data) {
            var message = data.message

            activationButton.prop('disabled', false)
            processingOverlay.css('display', 'none')
            messageContainer.fadeIn()
            secondStep.hide()

            messageContainer.html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>')
        })

    },

    EventListener: function ($this = this) {

        // GDPR Confirmation
        // Enable and disable the form submit button by changing the status of GDPR checkbox
        this.wpSmsGdprCheckbox.on('change', function () {
            if (this.checked) {
                jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first().prop('disabled', false)
            } else {
                jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first().prop('disabled', true)
            }
        })

        // Subscribe or Unsubscribe
        // Change the text of submit button based on the chosen event, Subscribe or Unsubscribe
        this.wpSmsEventType.on('click', function () {
            jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first().text(jQuery(this).data('label'))
        })

        // Submitting The Form
        this.wpSmsSubmitTypeButton.on('click', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            if (jQuery(this).hasClass('js-wpSmsSubmitButton')) {
                $this.sendSubscriptionForm(jQuery(this).parents('.js-wpSmsSubscribeForm'))
            }

            if (jQuery(this).hasClass('js-wpSmsActivationButton')) {
                $this.sendActivationCode(jQuery(this).parents('.js-wpSmsSubscribeForm'))
            }
        })

    }

}