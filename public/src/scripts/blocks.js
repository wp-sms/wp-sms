jQuery(document).ready(function () {
    wpSmsSubscribeForm.init();

    jQuery('.wpsms-sendSmsForm').each(function () {
        wpSmsSendSmsBlockForm.init(this);
    });
});

let wpSmsSubscribeForm = {
    init: function () {
        this.info = [];

        this.setFields();
        this.setEventListener();
    },

    getGroupId: function (element) {
        let group_id = [];
        let groupIdCheckboxes = element.find('input[name="group_id_checkbox"]');
        let groupIdSelect = element.find('select[name="group_id_select"]');

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

    setFields: function () {
        this.wpSmsGdprCheckbox = jQuery('.js-wpSmsGdprConfirmation');
        this.wpSmsEventType = jQuery(".js-wpSmsSubscribeType");
        this.wpSmsSubmitTypeButton = jQuery('.js-wpSmsSubmitTypeButton');
        this.mandatoryVerify = jQuery('.js-wpSmsMandatoryVerify').val();
    },

    sendSubscriptionForm: function (element, $this = this) {
        let submitButton = element.find('.js-wpSmsSubmitButton');
        let messageContainer = element.find('.js-wpSmsSubscribeMessage');
        let processingOverlay = element.find('.js-wpSmsSubscribeOverlay');
        let firstStep = element.find('.js-wpSmsSubscribeStepOne');
        let firstStepSubmitButton = element.find('.js-wpSmsSubmitButton');
        let secondStep = element.find('.js-wpSmsSubscribeStepTwo');
        let customFields = element.find('.js-wpSmsSubscriberCustomFields');
        let mobileField = element.find(".js-wpSmsSubscriberMobile");

        submitButton.prop('disabled', true);
        messageContainer.hide();
        processingOverlay.css('display', 'flex');

        let formData = new FormData();
        formData.append('name', element.find(".js-wpSmsSubscriberName input").val());
        formData.append('mobile', mobileField.find(".iti--show-flags").length > 0 ? mobileField.find("input.wp-sms-input-mobile").val() : mobileField.find("input").val());

        let groupIds = this.getGroupId(element);
        if (groupIds.length > 0) {
            formData.append('group_id', JSON.stringify(groupIds));
        }

        formData.append('type', element.find(".js-wpSmsSubscribeType:checked").val());

        customFields.each(function () {
            let label = jQuery(this).data('field-name');
            let value = jQuery(this).find('input').val();
            formData.append(`custom_fields[${label}]`, value);
        });

        let endpointUrl = formData.get('type') === 'subscribe' ? wpsms_ajax_object.subscribe_ajax_url : wpsms_ajax_object.unsubscribe_ajax_url;

        jQuery.ajax({
            type: 'POST',
            url: endpointUrl,
            contentType: false,
            processData: false,
            data: formData,
            beforeSend: function () {
                submitButton.prop('disabled', true).text(wpsms_ajax_object.loading_text);
            },
            complete: function () {
                submitButton.prop('disabled', false).text(wpsms_ajax_object.subscribe_text);
            },
            success: function (data) {
                submitButton.prop('disabled', false);
                processingOverlay.hide();
                messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + data.data + '</span>');
                firstStep.hide();
                if (formData.get('type') === 'subscribe' && $this.mandatoryVerify === '1') {
                    firstStepSubmitButton.prop('disabled', true);
                    secondStep.show();
                }
            },
            error: function (data) {
                submitButton.prop('disabled', false);
                processingOverlay.hide();
                messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + data.responseJSON.data + '</span>');
            }
        });

        $this.info = Object.fromEntries(formData.entries());
    },

    sendActivationCode: function (element, $this = this) {
        let activationButton = element.find('.js-wpSmsActivationButton');
        let subscribeFormContainer = element.parents('.js-wpSmsSubscribeFormContainer');
        let messageContainer = element.find('.js-wpSmsSubscribeMessage');
        let processingOverlay = element.find('.js-wpSmsSubscribeOverlay');
        let secondStep = element.find('.js-wpSmsSubscribeStepTwo');

        activationButton.prop('disabled', true);
        messageContainer.hide();
        processingOverlay.css('display', 'flex');

        // Update info with activation code
        $this.info.activation = element.find('.js-wpSmsActivationCode').val();

        // Create a new FormData object
        let formData = new FormData();

        // Append each key-value pair from $this.info into the FormData object
        for (let key in $this.info) {
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
            beforeSend: function () {
                activationButton.prop('disabled', true).text(wpsms_ajax_object.loading_text);
            },
            complete: function () {
                activationButton.prop('disabled', false).text(wpsms_ajax_object.activation_text);
            },
            success: function (data) {
                activationButton.prop('disabled', false);
                processingOverlay.hide();
                messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + data.data + '</span>');
                secondStep.hide();
            },
            error: function (data) {
                activationButton.prop('disabled', false);
                processingOverlay.hide();
                messageContainer.fadeIn().html('<span class="wpsms-subscribe__message wpsms-subscribe__message--error">' + data.responseJSON.data + '</span>');
            }
        });
    },

    setEventListener: function ($this = this) {
        this.wpSmsGdprCheckbox.on('change', function () {
            let submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first();
            submitButton.prop('disabled', !this.checked);
        });

        this.wpSmsEventType.on('click', function () {
            let submitButton = jQuery(this).parents('.js-wpSmsSubscribeFormField').nextAll('.js-wpSmsSubmitButton').first();
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

let wpSmsSendSmsBlockForm = {
    init: function (SBForm) {
        SBForm = jQuery(SBForm);
        this.setSendSmsBlockFields(SBForm);
    },

    setSendSmsBlockFields: function (SBForm) {
        let SBSubscriberGroup = SBForm.find('input[name=subscriberGroup]');
        let SBSubmit = SBForm.find('input[type=submit]');
        let SBMessage = SBForm.find('textarea.wpsms-sendSmsForm__messageField');
        let SBReceiver = SBForm.find('input[name=receiver]');
        let SBPhoneNumber = SBForm.find('input.wpsms-sendSmsForm__receiverField');
        let SBMessageAlert = SBForm.find('p.wpsms-sendSmsForm__messageField__alert');
        let SBResult = SBForm.find('div.wpsms-sendSmsForm__resultMessage');
        let SBOverlay = SBForm.find('div.wpsms-sendSmsForm__overlay');
        let SBMaxCount = SBMessage.data('max');

        let elements = {SBSubscriberGroup, SBSubmit, SBMessage, SBReceiver, SBPhoneNumber, SBMessageAlert, SBResult, SBOverlay, SBMaxCount};
        this.setSendSmsBlockEventListeners(elements);
    },

    setSendSmsBlockEventListeners: function (elements) {
        jQuery(elements.SBSubmit).on('click', function (event) {
            event.preventDefault();

            let formData = new FormData();
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
                beforeSend: function () {
                    jQuery(elements.SBResult).text('').fadeOut().removeClass('failed success');
                    jQuery(elements.SBOverlay).fadeIn();
                },
                success: function (data) {
                    jQuery(elements.SBResult).text(data.data).fadeIn().addClass('success');
                    jQuery(elements.SBMessage).val('').trigger('input');
                    jQuery(elements.SBOverlay).fadeOut();
                },
                error: function (data) {
                    var message = data.responseJSON.data && data.responseJSON.data.message ? data.responseJSON.data.message : data.responseJSON.data || 'An unexpected error occurred.';
                    jQuery(elements.SBResult).text(message).fadeIn().addClass('failed');
                    jQuery(elements.SBOverlay).fadeOut();
                }
            });
        });

        jQuery(elements.SBMessage).on('input', function () {
            let currentCharacterCount = jQuery(this).val().length;
            let remainingCharacterCount = elements.SBMaxCount - currentCharacterCount;

            if (currentCharacterCount >= elements.SBMaxCount - 8) {
                jQuery(elements.SBMessageAlert).fadeIn();
                jQuery(elements.SBMessageAlert).find('span').text(remainingCharacterCount);
            } else {
                jQuery(elements.SBMessageAlert).fadeOut();
            }
        });
    },
};
