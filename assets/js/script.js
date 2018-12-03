jQuery(document).ready(function ($) {
    // Check the GDPR enabled.

    if ($('#wpsms-gdpr-confirmation').length) {
        if ($('#wpsms-gdpr-confirmation').attr('checked')) {
            $("#wpsms-submit").removeAttr('disabled');
        } else {
            $("#wpsms-submit").attr('disabled', 'disabled');
        }
        $("#wpsms-gdpr-confirmation").click(function () {
            if (this.checked) {
                $("#wpsms-submit").removeAttr('disabled');
            } else {
                $("#wpsms-submit").attr('disabled', 'disabled');
            }
        });
    }

    $("#wpsms-subscribe #wpsms-submit").click(function () {
        $("#wpsms-result").hide();

        subscriber = [];
        subscriber['name'] = $("#wpsms-name").val();
        subscriber['mobile'] = $("#wpsms-mobile").val();
        subscriber['groups'] = $("#wpsms-groups").val();
        subscriber['type'] = $('input[name=subscribe_type]:checked').val();

        $("#wpsms-subscribe").ajaxStart(function () {
            $("#wpsms-submit").attr('disabled', 'disabled');
            $("#wpsms-submit").text("Loading...");
        });

        $("#wpsms-subscribe").ajaxComplete(function () {
            $("#wpsms-submit").removeAttr('disabled');
            $("#wpsms-submit").text("Subscribe");
        });
        if (subscriber['type'] === 'subscribe') {
            var method = 'POST';
        } else {
            var method = 'DELETE';
        }
        var data_obj = { name: subscriber['name'], mobile: subscriber['mobile'], grpup_id: subscriber['groups'] };
        console.log(data_obj);
        var ajax = $.ajax({
            type: method,
            widget_id: $('#wpsms-widget-id').attr('value'),
            url: ajax_object.ajaxurl,
            data: data_obj
        });
        ajax.fail(function (data) {
            var response = $.parseJSON(data.responseText);
            var error = null;

            if (typeof (response.message) != "undefined" && response.message !== null) {
                error = response.message;
            } else {
                if (subscriber['type'] === 'subscribe') {
                    error = response.errors.subscribe;
                } else {
                    error = response.errors.unsubscribe;
                }
            }

            $("#wpsms-result").fadeIn();
            $("#wpsms-result").html('<span class="wpsms-message-error">' + error + '</div>');
            data_obj= null;
        });
        ajax.done(function (data) {
            $("#wpsms-result").fadeIn();
            $("#wpsms-step-1").hide();
            $("#wpsms-result").html('<span class="wpsms-message-success">' + data.responseText + '</div>');
            $("#wpsms-step-2").show();
        });
    });
//TODO
    $("#wpsms-subscribe #activation").on('click', function () {
        $("#wpsms-result").hide();
        subscriber['activation'] = $("#wpsms-ativation-code").val();

        $("#wpsms-subscribe").ajaxStart(function () {
            $("#activation").attr('disabled', 'disabled');
            $("#activation").text('Loading...');
        });

        $("#wpsms-subscribe").ajaxComplete(function () {
            $("#activation").removeAttr('disabled');
            $("#activation").text('Activation');
        });

        $.post(ajax_object.ajaxurl, {
            widget_id: $('#wpsms-widget-id').attr('value'),
            action: 'activation_ajax_action',
            name: subscriber['name'],
            mobile: subscriber['mobile'],
            activation: subscriber['activation'],
        }, function (data, status) {
            var response = $.parseJSON(data);

            if (response.status == 'error') {
                $("#wpsms-result").fadeIn();
                $("#wpsms-result").html('<span class="wpsms-message-error">' + response.response + '</div>');
            }

            if (response.status == 'success') {
                $("#wpsms-result").fadeIn();
                $("#wpsms-step-2").hide();
                $("#wpsms-result").html('<span class="wpsms-message-success">' + response.response + '</div>');
            }
        });
    });
});