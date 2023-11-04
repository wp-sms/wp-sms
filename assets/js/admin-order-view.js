// Send sms from the WooCommerce order page
jQuery(document).ready(function () {
    wooCommerceOrderPage.init();
});

let wooCommerceOrderPage = {
    /**
     * initialize functions
     */
    init: function () {
        this.setFields()
        this.addEventListeners()
    },

    setFields: function () {
        this.parent = jQuery('#wpsms-woocommerceSendSMS')
    },

    sendSMS: function () {
        let receiver = this.parent.find('select[name="phone_number"]').val();
        let message = this.parent.find('textarea[name="message_content"]').val();
        let orderId = this.parent.find('input[name="send_sms_box_order_id"]').val();

        let requestBody = {
            message: message,
            recipients: 'numbers',
            numbers: [receiver],
            notification_handler: 'WooCommerceOrderNotification',
            handler_id: orderId,
            sender: wpSmsWooCommerceTemplateVar.senderID,
            flash: wpSmsWooCommerceTemplateVar.flashState,
        };

        jQuery.ajax(wpSmsWooCommerceTemplateVar.restUrls.sendSms,
            {
                headers: {'X-WP-Nonce': wpSmsWooCommerceTemplateVar.nonce},
                dataType: 'json',
                type: 'post',
                contentType: 'application/json',
                data: JSON.stringify(requestBody),
                beforeSend: function () {
                    this.parent.find('.wpsms-orderSmsMetabox__overlay').css('display', 'flex');
                    this.parent.find('.wpsms-orderSmsMetabox__variables__shortCodes').slideUp();
                    this.parent.find('.wpsms-orderSmsMetabox__result__tryAgain').hide();
                }.bind(this),
                success: function (data, status, xhr) {
                    this.parent.find('.wpsms-orderSmsMetabox').fadeOut();
                    this.parent.find('.wpsms-orderSmsMetabox__result__report p').html(data.message);
                    this.parent.find('.wpsms-orderSmsMetabox__result__report').removeClass('error');
                    this.parent.find('.wpsms-orderSmsMetabox__result__report').addClass('success');
                    this.parent.find('.wpsms-orderSmsMetabox__result__receiver p').html(receiver);
                    this.parent.find('.wpsms-orderSmsMetabox__result__message p').html(message);
                    this.parent.find(' .wpsms-orderSmsMetabox__result').fadeIn();
                }.bind(this),
                error: function (data, status, xhr) {
                    this.parent.find('.wpsms-orderSmsMetabox').fadeOut();
                    this.parent.find('.wpsms-orderSmsMetabox__result__report').removeClass('success');
                    this.parent.find('.wpsms-orderSmsMetabox__result__report').addClass('error');
                    this.parent.find('.wpsms-orderSmsMetabox__result__report p').html(data.responseJSON.error.message);
                    this.parent.find('.wpsms-orderSmsMetabox__result__tryAgain').show();
                    this.parent.find('.wpsms-orderSmsMetabox__result').fadeIn();
                }.bind(this)
            });
    },

    addEventListeners: function () {
        // Try again
        this.parent.find('.wpsms-orderSmsMetabox__result__tryAgain').on('click', (event) => {
            event.preventDefault();
            this.parent.find('.wpsms-orderSmsMetabox__result').fadeOut();
            this.parent.find('.wpsms-orderSmsMetabox__overlay').css('display', 'none');
            this.parent.find('.wpsms-orderSmsMetabox').fadeIn();
        });

        // Set event listener for the send sms button
        this.parent.find('button[name="send_sms"]').on('click', (event) => {
            event.preventDefault();
            this.sendSMS();
        });

        // Set event listener for shortcode blocks
        this.parent.find('.wpsms-orderSmsMetabox__variables__shortCodes code').on('click', function () {
            var codeValue = jQuery(this).text();
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
        this.parent.find('.wpsms-orderSmsMetabox__variables__header').on('click', function () {
            jQuery(this).next('.wpsms-orderSmsMetabox__variables__shortCodes').slideToggle();
            jQuery(this).find('.wpsms-orderSmsMetabox__variables__icon').toggleClass('expanded');
        });
    }
};