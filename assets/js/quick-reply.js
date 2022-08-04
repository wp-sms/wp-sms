jQuery(document).ready(function () {
    quickReply.init();
});

let quickReply = {

    /**
     * initialize functions
     */

    init: function () {
        this.setFields()
        this.addEventListener()
    },

    /**
     * initialize JQ selectors
     */

    setFields: function () {
        this.fromNumber = jQuery('.js-replyModalToggle')
        this.toNumber = jQuery('.js-wpSmsQuickReplyTo')
        this.replyMessage = jQuery('.js-wpSmsQuickReplyMessage')
        this.submitButton = jQuery('.quick-reply-submit')
    },

    addEventListener: function () {

        /**
         * copy clicked number contents to TickBox form
         */

        this.fromNumber.on('click', function (event) {

            // clear the form
            this.replyMessage.val('')
            jQuery('.wpsms-quick-reply-popup').removeClass('not-hidden')
            jQuery('.wpsms-quick-reply-popup').addClass('hidden')


            // copy value of clicked item into ThickBox's To field
            this.toNumber.attr('value', event.delegateTarget.dataset.number)

            // copy group id of subscribers to ThickBox's to field. This attribute only generate in Groups page
            if (this.fromNumber.attr('data-group-id')) {
                this.toNumber.attr('data-group-id', event.delegateTarget.dataset.groupId)
            }
        }.bind(this))

        /**
         * This function sends AJAX request
         */

        this.submitButton.on('click', function (event) {

            let data = this.bindData()

            //generating request body
            let requestBody = {
                sender: wpSmsQuickReplyTemplateVar.senderID,
                recipients: data.recipient,
                message: this.replyMessage.val(),
                numbers: data.numbers,
                group_ids: data.groupId,
                media_urls: []
            }

            jQuery.ajax({
                url: wpSmsQuickReplyTemplateVar.restRootUrl + 'wpsms/v1/send',
                headers: {'X-WP-Nonce': wpSmsQuickReplyTemplateVar.nonce},
                dataType: 'json',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(requestBody),

                beforeSend: function () {
                    jQuery('input[name="SendSMS"]').attr('disabled', 'disabled')
                    jQuery('.wpsms-sendsms__overlay').css('display', 'flex')
                },

                success: function (data, status, xhr) {
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    jQuery('.wpsms-quick-reply-popup-message').removeClass('notice notice-error')
                    jQuery('.wpsms-quick-reply-popup-message').addClass('notice notice-success')
                    jQuery('.wpsms-quick-reply-popup-message').html('<p>' + data.message + '</p>')
                    jQuery('.wpsms-quick-reply-popup').removeClass('hidden')
                    jQuery('.wpsms-quick-reply-popup').addClass('not-hidden')
                    jQuery('input[name="SendSMS"]').removeAttr('disabled')

                    if (jQuery('.js-wpSmsQuickReply').attr('data-reload')) {
                        location.reload()
                    }
                },

                error: function (data, status, xhr) {
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    jQuery('.wpsms-quick-reply-popup-message').removeClass('notice notice-success')
                    jQuery('.wpsms-quick-reply-popup-message').addClass('notice notice-error')
                    jQuery('.wpsms-quick-reply-popup-message').html("<p>" + data.responseJSON.error.message + '</p>');
                    jQuery('.wpsms-quick-reply-popup').removeClass('hidden')
                    jQuery('.wpsms-quick-reply-popup').addClass('not-hidden')
                    jQuery('input[name="SendSMS"]').removeAttr('disabled')
                }
            })
        }.bind(this))
    },

    /**
     * generate request data
     * @returns string
     */

    bindData: function () {

        var requestInfo = {};

        if (this.fromNumber.attr('data-group-id')) {
            requestInfo.recipient = 'subscribers'
            requestInfo.numbers = []
            requestInfo.groupId = [this.toNumber.attr('data-group-id')]
        } else {
            requestInfo.recipient = 'numbers'
            requestInfo.numbers = [this.toNumber.attr('value')]
            requestInfo.groupId = []
        }

        return requestInfo
    }
}