jQuery(document).ready(function () {
    wpsmsRepeatingMessages.init()

    jQuery(".wpsms-value").hide();
    jQuery(".wpsms-group").show();

    jQuery("select#select_sender").change(function () {
        recipientsSelect();
    });


    jQuery("#wp_get_message").counter({
        count: 'up',
        goal: 'sky',
        msg: WpSmsSendSmsTemplateVar.messageMsg
    });
    if (WpSmsSendSmsTemplateVar.proIsActive) {
        jQuery("#datepicker").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i:00",
            time_24hr: true,
            minuteIncrement: "10",
            minDate: WpSmsSendSmsTemplateVar.currentDateTime,
            disableMobile: true,
            defaultDate: WpSmsSendSmsTemplateVar.currentDateTime
        });

        jQuery("#schedule_status").change(function () {
            if (jQuery(this).is(":checked")) {
                jQuery('#schedule_date').show();
            } else {
                jQuery('#schedule_date').hide();
            }
        });
    }

    jQuery(".preview__message__number").html(jQuery("#wp_get_sender").val());

    if (jQuery("#wp_get_message").val()) {
        jQuery(".preview__message__message").html(jQuery("#wp_get_message").val());
    }

    jQuery("#wp_get_sender").on('keyup', function () {
        jQuery(".preview__message__number").html(jQuery("#wp_get_sender").val());
    });

    jQuery("#wp_get_message").on('keyup', function () {
        messageAutoScroll();
        var message = jQuery("#wp_get_message").val();
        var messageWithLineBreak = message.replace(/(\r\n|\n|\r)/gm, "<br>");
        jQuery(".preview__message__message").html(messageWithLineBreak);
        isRtl("#wp_get_message", ".preview__message__message");
    });

    jQuery('input[name="SendSMS"]').on('click', function (e) {
        e.preventDefault();
        sendSMS();
    });


    /**
     * Upload Media
     */
    var $uploadButton = jQuery('.wpsms-upload-button')
    var $removeButton = jQuery('.wpsms-remove-button')
    var $imageElement = jQuery('.wpsms-mms-image')

    // on upload button click
    $uploadButton.on('click', function (e) {
        e.preventDefault();

        var button = jQuery(this),
            wpsms_uploader = wp.media({
                title: 'Insert image',
                library: {
                    type: ['image']
                },
                button: {
                    text: 'Use this image'
                },
                multiple: false
            }).on('select', function () {
                var attachment = wpsms_uploader.state().get('selection').first().toJSON();

                button.html('<img width="300" src="' + attachment.url + '">');
                $imageElement.val(attachment.url)
                $removeButton.show()

            }).open();
    })

    // on remove button click
    $removeButton.on('click', function (e) {
        e.preventDefault();

        jQuery(this).hide()
        $imageElement.val('')
        $uploadButton.html('Upload image')
    });
});

function isRtl(input, output) {
    jQuery(input).off('keypress').on('keypress', function (e) {
        setTimeout(function () {
            if (jQuery(input).val().length > 1) {
                return;
            } else {
                const RTL_Regex = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/;
                const isRTL = RTL_Regex.test(String.fromCharCode(e.which));
                const Direction = isRTL ? 'rtl' : 'ltr';
                jQuery(input).css({ 'direction': Direction });
                if (isRTL) {
                    jQuery(output).css({ 'direction': 'rtl' });
                } else {
                    jQuery(output).css({ 'direction': 'ltr' });
                }
            }
        });
    });
}

function scrollToTop() {
    jQuery('html, body').animate({ scrollTop: 0 }, 1000);
}

function closeNotice() {
    jQuery(".wpsms-wrap__main__notice").removeClass('not-hidden');
}

function clearForm() {
    jQuery(".preview__message__humber").html('')
    jQuery(".preview__message__message").html('')
}

function sendSMS() {
    let smsFrom = jQuery("#wp_get_sender").val(),
        smsTo = { type: jQuery("select[name='wp_send_to'] option:selected").val() },
        smsMessage = jQuery("#wp_get_message").val(),
        smsMedia = jQuery(".wpsms-mms-image").val(),
        smsScheduled = { scheduled: jQuery("#schedule_status").is(":checked") },
        smsRepeating = wpsmsRepeatingMessages.getData(),
        smsFlash = jQuery('[name="wp_flash"]:checked').val();

    if (smsTo.type === "subscribers") {
        smsTo.groups = jQuery('.wpsms-group select[name="wpsms_groups[]"]').val();
    } else if (smsTo.type === "users") {
        smsTo.roles = jQuery('select[name="wpsms_roles[]"]').val();
    } else if (smsTo.type === "numbers") {
        smsTo.numbers = jQuery('textarea[name="wp_get_number"]').val();
        smsTo.numbers = smsTo.numbers.replace(/\n/g, ",").split(",");
    }

    if (smsScheduled.scheduled) {
        smsScheduled.date = jQuery("#schedule_date .flatpickr-input").val();
    }

    let requestBody = {
        sender: smsFrom,
        recipients: smsTo.type,
        group_ids: smsTo.groups,
        role_ids: smsTo.roles,
        message: smsMessage,
        numbers: smsTo.numbers,
        flash: smsFlash,
        media_urls: [smsMedia],
        schedule: smsScheduled.date,
        repeat: smsRepeating,
    };

    jQuery('.wpsms-wrap__main__notice').removeClass('not-hidden');

    jQuery.ajax(WpSmsSendSmsTemplateVar.restRootUrl + 'wpsms/v1/send',
        {
            headers: { 'X-WP-Nonce': WpSmsSendSmsTemplateVar.nonce },
            dataType: 'json',
            type: 'post',
            contentType: 'application/json',
            data: JSON.stringify(requestBody),
            beforeSend: function () {
                jQuery(".wpsms-sendsms__overlay").css('display', 'flex');
                jQuery('input[name="SendSMS"]').attr('disabled', 'disabled');
            },
            success: function (data, status, xhr) {
                Object.keys(smsTo).forEach(key => {
                    delete smsTo[key];
                })
                jQuery(".wpsms-mms-image").val([]).trigger('change');
                jQuery(".js-wpsms-select2").val([]).trigger('change');
                jQuery("#wp_get_number").val('').trigger('change');
                jQuery("#wp_get_message").val('').trigger('change');
                jQuery(".wpsms-remove-button").trigger('click');
                scrollToTop();
                jQuery(".wpsms-sendsms__overlay").css('display', 'none');
                jQuery('input[name="SendSMS"]').removeAttr('disabled');
                jQuery('.wpsms-wrap__main__notice').removeClass('notice-error');
                jQuery('.wpsms-wrap__main__notice').addClass('notice-success');
                jQuery('.wpsms-wrap__notice__text').html(data.message);
                jQuery('.wpsms-wrap__account-balance').html('Your account credit: ' + data.data.balance);
                jQuery('.wpsms-wrap__main__notice').addClass('not-hidden');
                jQuery(".wpsms-sendsms__overlay").css('display', 'none');
                clearForm();
            },
            error: function (data, status, xhr) {
                scrollToTop();
                jQuery('.wpsms-wrap__main__notice').removeClass('notice-success');
                jQuery('.wpsms-wrap__main__notice').addClass('notice-error');
                jQuery('.wpsms-wrap__notice__text').html(data.responseJSON.error.message);
                jQuery('.wpsms-wrap__main__notice').addClass('not-hidden');
                jQuery(".wpsms-sendsms__overlay").css('display', 'none');
                jQuery('input[name="SendSMS"]').removeAttr('disabled');
            }
        });
}

function recipientsSelect() {
    jQuery(".js-wpsms-select2").val([]).trigger('change');
    jQuery("#wp_get_number").val('').trigger('change');
    var get_method = "";
    jQuery("select#select_sender option:selected").each(
        function () {
            get_method += jQuery(this).attr('id');
        }
    );
    if (get_method == 'wp_subscribe_username') {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-group").fadeIn();
    } else if (get_method == 'wp_users') {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-users").fadeIn();
    } else if (get_method == 'wc_users') {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-wc-users").fadeIn();
    } else if (get_method == 'bp_users') {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-bp-users").fadeIn();
    } else if (get_method == 'wp_tellephone') {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-numbers").fadeIn();
        jQuery("#wp_get_number").focus();
    } else if (get_method == 'wp_role') {
        jQuery(".wpsms-value").hide();
        jQuery(".wprole-group").fadeIn();
    }
}

function messageAutoScroll() {
    jQuery('.preview__message__message-wrapper').scrollTop(jQuery('.preview__message__message').height());
}

const wpsmsRepeatingMessages = {
    init: function () {
        if (!WpSmsSendSmsTemplateVar.proIsActive) return
        this.setElements()
        this.initElements()
        this.handleFieldsVisibility()
        this.handleEndDateField()
    },

    setElements: function () {
        this.elements = {
            statusCheckbox: jQuery('#wpsms_repeat_status'),
            parentCheckbox: jQuery('#schedule_status'),
            subFields: jQuery('.repeat-subfield'),
            repeatInterval: jQuery('#repeat-interval'),
            repeatUnit: jQuery('#repeat-interval-unit'),
            endDatepicker: jQuery('#repeat_ends_on'),
            foreverCheckbox: jQuery('#repeat-forever'),
        }

    },

    initElements: function () {
        this.elements.endDatepicker.flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i:00",
            time_24hr: true,
            minuteIncrement: "10",
            minDate: WpSmsSendSmsTemplateVar.currentDateTime,
            disableMobile: true,
            defaultDate: WpSmsSendSmsTemplateVar.currentDateTime
        })
    },

    handleFieldsVisibility: function () {
        const handler = function () {
            if (this.elements.parentCheckbox.is(':checked')) {
                this.elements.statusCheckbox.closest('tr').show()
            } else {
                this.elements.statusCheckbox.closest('tr').hide()
            }

            if (this.elements.parentCheckbox.is(':checked') && this.elements.statusCheckbox.is(':checked')) {
                this.elements.subFields.show()
                this.isActive = true
            } else {
                this.elements.subFields.hide()
                this.isActive = false
            }
        }.bind(this)

        handler();

        //Event listeners
        this.elements.statusCheckbox.change(handler)
        this.elements.parentCheckbox.change(handler)
    },

    handleEndDateField: function () {
        const handler = function () {
            if (this.elements.foreverCheckbox.is(':checked')) {
                this.elements.endDatepicker.attr('disabled', 'disabled')
            } else {
                this.elements.endDatepicker.removeAttr('disabled', 'disabled')
            }
        }.bind(this)

        handler()

        //Event listener
        this.elements.foreverCheckbox.change(handler)
    },

    getData: function () {

        if (!this.isActive) return

        const elements = this.elements
        const data = {
            interval: {
                value: elements.repeatInterval.val(),
                unit: elements.repeatUnit.val()
            }
        }
        elements.foreverCheckbox.is(':checked') ? (data.repeatForever = true) : (data.endDate = elements.endDatepicker.val())

        return data
    }

}
