jQuery(document).ready(function () {
    getRecipientNumber.init();
});

let getRecipientNumber = {

    init: function () {
        this.setFields()
        this.addEventListener()
    },

    setFields: function () {
        this.fromNumber = jQuery('.js-replyModalToggle')
        this.toNumber = jQuery('.js-twoWayQuickReplyTo')
        this.replyMessage = jQuery('.js-twoWayQuickReplyMessage')
        this.submitButton = jQuery('.quick-reply-submit')
    },

    addEventListener: function () {
        this.fromNumber.on('click', function (event) {
            this.toNumber.attr('value', event.target.innerHTML)
        }.bind(this))

        this.submitButton.on('click', function (event) {
            let requestBody = {
                sender: wpSmsQuickReplyTemplateVar.senderID,
                recipients: 'numbers',
                message: this.replyMessage.val(),
                numbers: [this.toNumber.attr('value')],
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
                    jQuery('.tw-load-spinner-overlay').removeClass('hidden')
                },

                success: function (data, status, xhr) {
                    jQuery('.tw-load-spinner-overlay').addClass('hidden')
                    jQuery('.wpsmstw-wrap__main__notice').removeClass('quick-reply-notice-error')
                    jQuery('.wpsmstw-wrap__main__notice').addClass('quick-reply-notice-success')
                    jQuery('.wpsmstw-wrap__notice__text').html(data.message)
                    jQuery('.wpsmstw-wrap__account-balance').html('Your account credit: ' + data.data.balance);
                    jQuery('input[name="SendSMS"]').removeAttr('disabled')
                    clearForm()
                },

                error: function (data, status, xhr) {
                    jQuery('.tw-load-spinner-overlay').addClass('hidden')
                    jQuery('.wpsmstw-wrap__main__notice').removeClass('quick-reply-notice-success')
                    jQuery('.wpsmstw-wrap__main__notice').addClass('quick-reply-notice-error')
                    jQuery('.wpsmstw-wrap__notice__text').html("An error occurred while sending SMS! " + `(Error ${data.responseJSON.error.code}: ${data.responseJSON.error.message})`);
                    jQuery('input[name="SendSMS"]').removeAttr('disabled')
                }
            })
        }.bind(this))
    },

}