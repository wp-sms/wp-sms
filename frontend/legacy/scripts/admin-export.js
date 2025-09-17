jQuery(document).ready(function () {
    wpSmsExport.init();
});

let wpSmsExport = {

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
        this.exportForm = jQuery('.js-wpSmsExportForm')
        this.exportGroup = jQuery('#wpsms_groups')
    },

    addEventListener: function () {
        this.exportForm.on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            // get type of data from a hidden input in the form
            let type = jQuery('.wp-sms-export-type').val()

            // generating request body data
            let requestBody = {
                'type': type,
            }

            if (type == 'subscriber') {
                Object.assign(requestBody, {'groupIds': this.exportGroup.val()})
            }

            // send AJAX request
            jQuery.ajax({
                url: WP_Sms_Admin_Object.ajaxUrls.export,
                type: 'GET',
                xhrFields: {
                    responseType: 'blob'
                },
                contentType: 'application/json',
                data: requestBody,

                // enabling loader
                beforeSend: function () {
                    jQuery('.js-wpSmsExportButton').attr('disabled', 'disabled')
                    jQuery('.wpsms-sendsms__overlay').css('display', 'flex')
                },

                // successful request
                success: function (blob, status, xhr) {
                    jQuery('.js-wpSmsExportButton').prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    jQuery('.wpsms-export-popup .wp-sms-popup-messages').removeClass('notice notice-error')
                    jQuery('.wpsms-export-popup .wp-sms-popup-messages').addClass('notice notice-success')
                    jQuery('.wpsms-export-popup .wp-sms-popup-messages').html('<p>The data exported successfully.</p>')

                    var fileName = xhr.getResponseHeader('Content-Disposition')

                    fileName = fileName.slice(fileName.indexOf('filename') + 9)

                    var downloadUrl = window.URL.createObjectURL(blob);
                    var URL = window.URL

                    var a = document.createElement('a');

                    if (typeof a.download === 'undefined') {
                        window.location.href = downloadUrl;
                    } else {
                        a.href = downloadUrl;
                        a.download = fileName;
                        document.body.appendChild(a);
                        a.click();
                    }

                    //clean up
                    setTimeout(function () {
                        URL.revokeObjectURL(downloadUrl);
                    }, 100);
                },

                // failed request
                error: function (data, response, xhr) {
                    jQuery('.js-wpSmsExportButton').prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    jQuery('.wpsms-export-popup .wp-sms-popup-messages').removeClass('notice notice-success')
                    jQuery('.wpsms-export-popup .wp-sms-popup-messages').addClass('notice notice-error')
                    jQuery('.wpsms-export-popup .wp-sms-popup-messages').html('<p>Failed to export the data.</p>')
                }

            })

        }.bind(this));
    },

}