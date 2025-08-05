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
        this.SmsMetabox = jQuery('#wpsms-woocommerceSendSMS');
        this.NotesMetabox = jQuery('#woocommerce-order-notes');
    },

    sendSMS: function () {
        let receiver = this.SmsMetabox.find('select[name="phone_number"]').val();
        let message = this.SmsMetabox.find('textarea[name="message_content"]').val();
        let orderId = WP_Sms_Admin_Object.order_id;

        let requestBody = {
            message: message,
            recipients: 'numbers',
            numbers: [receiver],
            notification_handler: 'WooCommerceOrderNotification',
            handler_id: orderId,
            sender: WP_Sms_Admin_Object.senderID,
        };

        jQuery.ajax(WP_Sms_Admin_Object.restUrls.sendSms,
            {
                headers: {'X-WP-Nonce': WP_Sms_Admin_Object.nonce},
                dataType: 'json',
                type: 'post',
                contentType: 'application/json',
                data: JSON.stringify(requestBody),
                beforeSend: function () {
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__overlay').css('display', 'flex');
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__variables__shortCodes').slideUp();
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').hide();
                }.bind(this),
                success: function (data, status, xhr) {
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox').fadeOut();
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report p').html(data.message);
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').removeClass('error');
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').addClass('success');
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__receiver p').html(receiver);
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__message p').html(message);
                    this.SmsMetabox.find(' .wpsms-orderSmsMetabox__result').fadeIn();
                }.bind(this),
                error: function (data, status, xhr) {
                    var errorMessage = data.responseJSON.message ? data.responseJSON.message : data.responseJSON.error.message;
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox').fadeOut();
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').removeClass('success');
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report').addClass('error');
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__report p').html(errorMessage);
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').show();
                    this.SmsMetabox.find('.wpsms-orderSmsMetabox__result').fadeIn();
                }.bind(this)
            });
    },

    addSendSMSEventListeners: function () {
        // Try again
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__result__tryAgain').on('click', (event) => {
            event.preventDefault();
            this.SmsMetabox.find('.wpsms-orderSmsMetabox__result').fadeOut();
            this.SmsMetabox.find('.wpsms-orderSmsMetabox__overlay').css('display', 'none');
            this.SmsMetabox.find('.wpsms-orderSmsMetabox').fadeIn();
        });

        // Set event listener for the send sms button
        this.SmsMetabox.find('button[name="send_sms"]').on('click', (event) => {
            event.preventDefault();
            this.sendSMS();
        });

        // Set event listener for shortcode blocks
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__variables__shortCodes code').on('click', function () {
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
        this.SmsMetabox.find('.wpsms-orderSmsMetabox__variables__header').on('click', function () {
            jQuery(this).next('.wpsms-orderSmsMetabox__variables__shortCodes').slideToggle();
            jQuery(this).find('.wpsms-orderSmsMetabox__variables__icon').toggleClass('expanded');
        });
    },

    addNoteEventListeners: function () {
        // Set up an event listener for adding notes
        this.NotesMetabox.find('button.add_note').on('click', (event) => {
            this.sendNoteSMS();
        });

        // Show and hide sms to customer elements
        this.NotesMetabox.find('select[name=order_note_type]').on('change', () => {
            let noteType = this.NotesMetabox.find('select[name=order_note_type]').val();
            this.NotesMetabox.find('.wpsms-addNoteMetabox__elements').toggle(noteType === 'customer');
        });
    },

    setupNotesMetabox: function () {
        // Set up needed fields in the order notes metabox
        jQuery('#woocommerce-order-notes div.add_note').append(
            '<div class="wpsms-addNoteMetabox__elements">' +
            '<label for="wpsms_note_send">' +
            '<input type="checkbox" id="wpsms_note_send" name="wpsms_note_send">'
            + WP_Sms_Admin_Object.lang.checkbox_label
            + '</label>' +
            '<div class="wpsms-addNoteMetabox__result__report">' +
            '<span class="wpsms-addNoteMetabox__result__icon"></span>' +
            '<p></p>' +
            '</div>' +
            '</div>'
        );
    },

    sendNoteSMS: function () {
        let message = this.NotesMetabox.find('textarea[name=order_note]').val();
        let sendSMS = this.NotesMetabox.find('input[name=wpsms_note_send]').prop('checked');
        let noteType = this.NotesMetabox.find('select[name=order_note_type]').val();
        let receiver = WP_Sms_Admin_Object.receiver;
        let orderId = WP_Sms_Admin_Object.order_id;

        if (!sendSMS || !message || noteType !== 'customer') {
            return;
        }

        let requestBody = {
            message: message,
            recipients: 'numbers',
            numbers: [receiver],
            notification_handler: 'WooCommerceOrderNotification',
            handler_id: orderId,
            sender: WP_Sms_Admin_Object.senderID,
        };

        jQuery.ajax(WP_Sms_Admin_Object.restUrls.sendSms,
            {
                headers: {'X-WP-Nonce': WP_Sms_Admin_Object.nonce},
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