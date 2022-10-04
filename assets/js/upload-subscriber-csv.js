jQuery(document).ready(function () {
    wpSmsUploadCsv.init();
});

let wpSmsUploadCsv = {

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
        this.importForm = jQuery('.js-wpSmsImportForm')
    },

    addEventListener: function () {
        this.importForm.on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

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
                    jQuery('.js-wpSmsImportButton').attr('disabled', 'disabled')
                    jQuery('.wpsms-sendsms__overlay').css('display', 'flex')
                },

                // successful request
                success: function (request, data, xhr) {
                    jQuery('.js-wpSmsImportButton').prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    jQuery('.js-WpSmsHiddenAfterUpload').css('display', 'none')
                    jQuery('.js-wpSmsImportButton').prop('value', 'Import')
                    jQuery('#first-row-label').css('display', 'block')

                    var firstRow = JSON.parse(xhr.getResponseHeader("FirstRow-content"))

                    firstRow.forEach(function (item) {
                        jQuery('.js-wpSmsImportButton').before(
                            '<tr>' +
                                '<td>' +
                                item +
                                '</td>' +
                                '<td>' +
                                    '<select id="first-row-dropdown">' +
                                        '<option>Please Select</option>' +
                                        '<option value="ID">ID</option>' +
                                        '<option value="date">Date</option>' +
                                        '<option value="name">Name</option>' +
                                        '<option value="mobile">Mobile</option>' +
                                        '<option value="status">Status</option>' +
                                        '<option value="group_ID">Group ID</option>' +
                                    '</select>' +
                                '</td>' +
                            '</tr>'
                        )
                    })
                },

                // failed request
                error: function (data, response, xhr) {
                    jQuery('.js-wpSmsImportButton').prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')

                    console.log('Failed')
                }

            })

        }.bind(this));
    },

}