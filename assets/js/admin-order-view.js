// Send sms from the WooCommerce order page
jQuery(function ($) {
    // Store the parent element as a variable
    var parent = $('#wp-sms-woocommerce-send-sms');

    function sendSMS() {
        let receiver = parent.find('select[name="phone_number"]').val();
        let message = parent.find('textarea[name="message_content"]').val();
        let orderId = parent.find('input[name="send_sms_box_order_id"]').val();

        let requestBody = {
            sender: wpSmsWooCommerceTemplateVar.senderID,
            recipients: 'numbers',
            numbers: [receiver],
            message: message,
            notification_handler: 'WooCommerceOrderNotification',
            handler_id: orderId,
        };

        jQuery.ajax(wpSmsWooCommerceTemplateVar.restUrls.sendSms,
            {
                headers: {'X-WP-Nonce': wpSmsWooCommerceTemplateVar.nonce},
                dataType: 'json',
                type: 'post',
                contentType: 'application/json',
                data: JSON.stringify(requestBody),
                beforeSend: function () {
                    parent.find('button[name="send_sms"]').html('Sending...');
                    parent.find('.wpsms-orderSmsMetabox__overlay').css('display', 'flex');
                    parent.find('.wpsms-orderSmsMetabox__variables__shortCodes').slideUp();
                },
                success: function (data, status, xhr) {
                    parent.find('.wpsms-orderSmsMetabox').fadeOut();
                    parent.find('.wpsms-orderSmsMetabox__result__report p').html(data.message);
                    parent.find('.wpsms-orderSmsMetabox__result__report').removeClass('error');
                    parent.find('.wpsms-orderSmsMetabox__result__report').addClass('success');
                    parent.find('.wpsms-orderSmsMetabox__result__receiver p').html(receiver);
                    parent.find('.wpsms-orderSmsMetabox__result__message p').html(message);
                    parent.find(' .wpsms-orderSmsMetabox__result').fadeIn();
                },
                error: function (data, status, xhr) {
                    parent.find('.wpsms-orderSmsMetabox').fadeOut();
                    parent.find('.wpsms-orderSmsMetabox__result__report').removeClass('success');
                    parent.find('.wpsms-orderSmsMetabox__result__report').addClass('error');
                    parent.find('.wpsms-orderSmsMetabox__result__report p').html(data.responseJSON.error.message);
                    parent.find('.wpsms-orderSmsMetabox__result').fadeIn();
                }
            });
    }

    // Set event listener for the send sms button
    parent.find('button[name="send_sms"]').on('click', function () {
        event.preventDefault();
        sendSMS();
    });

    // Set event listener for shortcode blocks
    parent.find('.wpsms-orderSmsMetabox__variables__shortCodes code').on('click', function () {
        var codeValue = $(this).text();
        var textarea = document.getElementById('message_content');

        // Get the current cursor position in the textarea
        var cursorPos = textarea.selectionStart;

        // Get the text before and after the cursor position
        var textBeforeCursor = textarea.value.substring(0, cursorPos);
        var textAfterCursor = textarea.value.substring(cursorPos);

        // Insert the clicked code value at the cursor position and update the textarea value
        codeValue = ' ' + codeValue;
        textarea.value = textBeforeCursor + codeValue + textAfterCursor;

        // Set the new cursor position after the inserted code value
        textarea.setSelectionRange(cursorPos + codeValue.length, cursorPos + codeValue.length);
    });

    // Set event listener for shortcodes collapsable
    parent.find('.wpsms-orderSmsMetabox__variables__header').on('click', function () {
        $(this).next('.wpsms-orderSmsMetabox__variables__shortCodes').slideToggle();
        $(this).find('.wpsms-orderSmsMetabox__variables__icon').toggleClass('expanded');
    });
});