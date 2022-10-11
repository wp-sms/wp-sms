jQuery(document).ready(function () {
    wpSmsUploadSubscriberCsv.init();
});

let wpSmsUploadSubscriberCsv = {

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
        this.uploadForm = jQuery('.js-wpSmsUploadForm')
    },

    addEventListener: function () {
        this.uploadForm.on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let uploadForm = jQuery('.js-wpSmsUploadForm')
            let uploadButton = jQuery('.js-wpSmsUploadButton')

            var fileData = jQuery('#wp-sms-input-file')[0].files
            var fromData = new FormData()

            if (fileData.length > 0) {
                fromData.append('file', fileData[0])
            }

            // check whether the file has header
            var hasHeader = false

            if (jQuery('#file-has-header').is(':checked')) {
                hasHeader = true
            }

            // send AJAX request
            jQuery.ajax({
                url: wpSmsGlobalTemplateVar.uploadSubscriberCsv + '&hasHeader=' + hasHeader,
                method: 'post',
                data: fromData,
                contentType: false,
                cache: false,
                processData: false,

                // enabling loader
                beforeSend: function () {
                    jQuery('.js-wpSmsUploadButton').attr('disabled', 'disabled')
                    jQuery('.wpsms-sendsms__overlay').css('display', 'flex')
                },

                // successful request
                success: function (request, data, xhr) {
                    uploadButton.prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    jQuery('.js-WpSmsHiddenAfterUpload').css('display', 'none')
                    uploadButton.prop('value', 'Import')
                    jQuery('#first-row-label').css('display', 'block')


                    var firstRow = JSON.parse(xhr.getResponseHeader("FirstRow-content"))

                    firstRow.forEach(function (item) {
                        uploadButton.before(
                            '<tr>' +
                            '<td>' +
                            item +
                            '</td>' +
                            '<td>' +
                            '<select class="import-column-type">' +
                            '<option>Please Select</option>' +
                            '<option value="name">Name</option>' +
                            '<option value="mobile">Mobile</option>' +
                            '<option value="group_ID">Group ID</option>' +
                            '</select>' +
                            '</td>' +
                            '</tr>'
                        )
                    })

                    uploadForm.addClass('js-wpSmsImportForm')
                    uploadButton.addClass('js-wpSmsImportButton')
                    uploadForm.removeClass('js-wpSmsUploadForm')
                    uploadButton.removeClass('js-wpSmsUploadButton')

                },

                // failed request
                error: function (data, response, xhr) {

                    uploadButton.prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')

                }

            })

        }.bind(this))
    },

}