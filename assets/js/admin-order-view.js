// Send sms from the WooCommerce order page
jQuery(document).ready(function () {
    wooCommerceOrderPage.init();
});

let wooCommerceOrderPage = {
    /**
     * initialize functionality
     */
    init: function () {
        this.setFields();
        this.addSendSMSEventListeners();
        this.addNoteEventListeners();
        this.setupNotesMetabox();
    },

    setFields: function () {
        this.SMSMetabox = jQuery('#wpsms-woocommerceSendSMS');
        this.NotesMetabox = jQuery('#woocommerce-order-notes');
    },

    sendSMS: function () {
        let receiver = this.SMSMetabox.find('select[name="phone_number"]').val();
        let message = this.SMSMetabox.find('textarea[name="message_content"]').val();
        let orderId = wpSmsWooCommerceTemplateVar.order_id;

        let requestBody = {
            message: message,
            recipients: 'numbers',
            numbers: [receiver],
            notification_handler: 'WooCommerceOrderNotification',
            handler_id: orderId,
            sender: wpSmsWooCommerceTemplateVar.sender_id,
        };

        jQuery.ajax(wpSmsWooCommerceTemplateVar.rest_urls.send_sms,
            {
                headers: {'X-WP-Nonce': wpSmsWooCommerceTemplateVar.nonce},
                dataType: 'json',
                type: 'post',
                contentType: 'application/json',
                data: JSON.stringify(requestBody),
                beforeSend: function () {
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__overlay').css('display', 'flex');
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__variables__shortCodes').slideUp();
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').hide();
                }.bind(this),
                success: function (data, status, xhr) {
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox').fadeOut();
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__report p').html(data.message);
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__report').removeClass('error');
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__report').addClass('success');
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__receiver p').html(receiver);
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__message p').html(message);
                    this.SMSMetabox.find(' .wpsms-orderSmsMetabox__result').fadeIn();
                }.bind(this),
                error: function (data, status, xhr) {
                    var errorMessage = data.responseJSON.message ? data.responseJSON.message : data.responseJSON.error.message;
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox').fadeOut();
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__report').removeClass('success');
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__report').addClass('error');
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__report p').html(errorMessage);
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').show();
                    this.SMSMetabox.find('.wpsms-orderSmsMetabox__result').fadeIn();
                }.bind(this)
            });
    },

    addSendSMSEventListeners: function () {
        // Try again
        this.SMSMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').on('click', (event) => {
            event.preventDefault();
            this.SMSMetabox.find('.wpsms-orderSmsMetabox__result').fadeOut();
            this.SMSMetabox.find('.wpsms-orderSmsMetabox__overlay').css('display', 'none');
            this.SMSMetabox.find('.wpsms-orderSmsMetabox').fadeIn();
        });

        // Set event listener for the send sms button
        this.SMSMetabox.find('button[name="send_sms"]').on('click', (event) => {
            event.preventDefault();
            this.sendSMS();
        });

        // Set event listener for shortcode blocks
        this.SMSMetabox.find('.wpsms-orderSmsMetabox__variables__shortCodes code').on('click', function () {
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
        this.SMSMetabox.find('.wpsms-orderSmsMetabox__variables__header').on('click', function () {
            jQuery(this).next('.wpsms-orderSmsMetabox__variables__shortCodes').slideToggle();
            jQuery(this).find('.wpsms-orderSmsMetabox__variables__icon').toggleClass('expanded');
        });
    },

    addNoteEventListeners: function () {
        // Set up an event listener for adding notes
        this.NotesMetabox.find('button.add_note').on('click', (event) => {
            this.sendNoteSMS();
        });
    },

    setupNotesMetabox: function () {
        // Set up needed fields in the order notes metabox
        jQuery('#woocommerce-order-notes div.add_note').prepend(
            '<div class="wpsms-addNoteMetabox__result__report">' +
            '<span class="wpsms-addNoteMetabox__result__icon"></span>' +
            '<p></p>' +
            '</div>'
        );

        jQuery('#add_order_note').after('<label for="wpsms_note_send">' +
            '<input type="checkbox" id="wpsms_note_send" name="wpsms_note_send">'
            + wpSmsWooCommerceTemplateVar.lang.checkbox_label
            + '</label>' +
            '<p>' + wpSmsWooCommerceTemplateVar.lang.checkbox_desc + '</p>');
    },

    sendNoteSMS: function () {
        let message = this.NotesMetabox.find('textarea[name=order_note]').val();
        let sendSMS = this.NotesMetabox.find('input[name=wpsms_note_send]').prop('checked');
        let noteType = this.NotesMetabox.find('select[name=order_note_type]').val();
        let receiver = wpSmsWooCommerceTemplateVar.receiver;
        let orderId = wpSmsWooCommerceTemplateVar.order_id;

        if (!sendSMS || !message || noteType !== 'customer') {
            return;
        }

        let requestBody = {
            message: message,
            recipients: 'numbers',
            numbers: [receiver],
            notification_handler: 'WooCommerceOrderNotification',
            handler_id: orderId,
            sender: wpSmsWooCommerceTemplateVar.sender_id,
        };

        jQuery.ajax(wpSmsWooCommerceTemplateVar.rest_urls.send_sms,
            {
                headers: {'X-WP-Nonce': wpSmsWooCommerceTemplateVar.nonce},
                dataType: 'json',
                type: 'post',
                contentType: 'application/json',
                data: JSON.stringify(requestBody),
                beforeSend: function () {
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').removeClass('error success');
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').fadeOut();
                }.bind(this),
                success: function (data, status, xhr) {
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report p').html(data.message);
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').addClass('success');
                    this.NotesMetabox.find(' .wpsms-addNoteMetabox__result__report').fadeIn();
                }.bind(this),
                error: function (data, status, xhr) {
                    var errorMessage = data.responseJSON.message ? data.responseJSON.message : data.responseJSON.error.message;
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').addClass('error');
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report p').html(errorMessage);
                    this.NotesMetabox.find('.wpsms-addNoteMetabox__result__report').fadeIn();
                }.bind(this)
            });
    },
};