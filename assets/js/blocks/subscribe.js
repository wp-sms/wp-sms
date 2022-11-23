jQuery(document).ready(function () {
    function showProcessing() {
        jQuery(".wpsms-subscribe__overlay").css('display', 'flex');
    }

    function hideProcessing() {
        jQuery(".wpsms-subscribe__overlay").css('display', 'none');
    }

    function disableSubmitBtn() {
        jQuery("#wpsms-submit").attr('disabled', 'disabled');
    }

    function enableSubmitBtn() {
        jQuery("#wpsms-submit").prop('disabled', false);
    }

    function disableActivationBtn() {
        jQuery("#activation").attr('disabled', 'disabled');
    }

    function enableActivationBtn() {
        jQuery("#activation").prop('disabled', false);
    }

    function hideFirstStep() {
        jQuery("#wpsms-step-1").hide();
    }

    function showSecondStep() {
        jQuery("#wpsms-step-2").show();
    }

    function hideSecondStep() {
        jQuery("#wpsms-step-2").hide();
    }

    function hideMessages() {
        jQuery("#wpsms-result").hide();
    }

    function ShowMessages() {
        jQuery("#wpsms-result").fadeIn();
    }

    const subscriber = Array();

    function sendSubscribeForm() {
        disableSubmitBtn();
        hideMessages();
        showProcessing();
        var verify = jQuery("#newsletter-form-verify").val();

        subscriber['name'] = jQuery("#wpsms-name").val();
        subscriber['mobile'] = jQuery("#wpsms-mobile").val();
        subscriber['group_id'] = jQuery("#wpsms-groups").val();
        subscriber['type'] = jQuery('.wpsms-subscribe-type__field__input:checked').val();

        jQuery("#wpsms-subscribe").ajaxStart(function () {
            jQuery("#wpsms-submit").attr('disabled', 'disabled');
            jQuery("#wpsms-submit").text(wpsms_ajax_object.loading_text);
        });

        jQuery("#wpsms-subscribe").ajaxComplete(function () {
            disableSubmitBtn();
            jQuery("#wpsms-submit").text(wpsms_ajax_object.subscribe_text);
        });

        if (subscriber['type'] === 'subscribe') {
            var endpointUrl = wpsms_ajax_object.rest_endpoint_url;
        } else {
            var endpointUrl = wpsms_ajax_object.rest_endpoint_url + '/unsubscribe';
        }

        var data_obj = Object.assign({}, subscriber);
        var ajax = jQuery.ajax({
            type: 'POST',
            url: endpointUrl,
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

    function sendActivationForm() {
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
            type: 'POST',
            url: wpsms_ajax_object.rest_endpoint_url + '/verify',
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
            hideProcessing();
            ShowMessages();
            hideSecondStep();
            jQuery("#wpsms-result").html('<span class="wpsms-subscribe__message wpsms-subscribe__message--success">' + message + '</div>');
        });
    }

    function gdprCheckbox() {
        if (jQuery('#wpsms-gdpr-confirmation').length) {
            if (jQuery('#wpsms-gdpr-confirmation').attr('checked')) {
                enableSubmitBtn();
            } else {
                disableSubmitBtn();
            }
        }
    }

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
});