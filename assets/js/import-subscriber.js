jQuery(document).ready(function () {
    wpSmsImportSubscriber.init();
});

let wpSmsImportSubscriber = {

    /**
     * initialize functions
     */
    init: function () {
        this.setFields()
        this.uploadEventListener()
        this.selectColumnFileHeaderEventListener()
        this.selectOrAddGroup()
        this.importEventListener()
    },

    /**
     * initialize JQ selectors
     */
    setFields: function () {
        this.uploadForm = jQuery('.js-wpSmsUploadForm')
        this.importButton = jQuery('.js-wpSmsImportButton')
    },

    uploadEventListener: function () {
        this.uploadForm.on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let uploadButton = jQuery('.js-wpSmsUploadButton')
            let importButton = jQuery('.js-wpSmsImportButton')

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
                    jQuery('#first-row-label').css('display', 'block')
                    uploadButton.css('display', 'none')
                    importButton.css('display', 'block')

                    var firstRow = JSON.parse(xhr.getResponseHeader("FirstRow-content"))

                    firstRow.forEach(function (item) {
                        jQuery('#wp-sms-group-select').before(
                            '<tr>' +
                            '<td>' +
                            item +
                            '</td>' +
                            '<td>' +
                            '<select class="import-column-type">' +
                            '<option value="0">Please Select</option>' +
                            '<option value="name">Name</option>' +
                            '<option value="mobile">Mobile</option>' +
                            '<option value="group">Group ID</option>' +
                            '</select>' +
                            '</td>' +
                            '</tr>'
                        )
                    })

                },

                // failed request
                error: function (data, response, xhr) {
                    uploadButton.prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                }

            })

        }.bind(this))
    },

    selectColumnFileHeaderEventListener: function () {
        jQuery('body').on('change', '.import-column-type', function (event) {
            var isGroupSelected = false

            jQuery('.import-column-type').each(function () {
                // check if the group id is selected
                if (jQuery(this).val() === 'group') {
                    isGroupSelected = true
                }
            })

            if (isGroupSelected) {
                jQuery('#wp-sms-group-select').css('display', 'none')
                jQuery('.js-wpSmsUploadForm').addClass('hasGroup')
                jQuery('.js-wpSmsUploadForm').removeClass('noGroup')
            } else {
                jQuery('#wp-sms-group-select').css('display', 'block')
                jQuery('.js-wpSmsUploadForm').addClass('noGroup')
                jQuery('.js-wpSmsUploadForm').removeClass('hasGroup')
            }
        })

    },

    selectOrAddGroup: function () {

        jQuery('body').on('change', '#wp-sms-group-select select', function (event) {
            if (jQuery('#wp-sms-group-select select').val() === 'new_group') {
                jQuery('#wp-sms-group-name').css('display', 'block')
            } else {
                jQuery('#wp-sms-group-name').css('display', 'none')
            }
        })

    },

    importEventListener: function () {
        this.importButton.on('click', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let importButton = jQuery('.js-wpSmsImportButton')
            let requestBody = {}
            let selectGroupColumn = jQuery('.import-column-type')

            selectGroupColumn.each(function (index) {
                if (jQuery(this).val() !== '0') {
                    var objectKey = jQuery(this).val()
                    requestBody[objectKey] = index
                }
            })

            if (!requestBody.group) {
                var selectedGroupOption = jQuery('#wp-sms-group-select select').val()
                var groupName = jQuery('#wp-sms-select-group-name').val()

                switch (selectedGroupOption) {
                    case '0':
                        requestBody['state'] = 0
                        requestBody['group'] = null
                        break

                    case 'new_group':
                        requestBody['state'] = 'new_group'
                        requestBody['group'] = groupName
                        break

                    default:
                        requestBody['state'] = 'existed_group'
                        requestBody['group'] = selectedGroupOption
                        break
                }
            }

            if (jQuery('#file-has-header').is(':checked')) {
                requestBody.hasHeader = true
            }

            if (!requestBody.name || !requestBody.mobile) {
                //TODO error handler
            }

            jQuery.ajax({
                url: wpSmsGlobalTemplateVar.importSubscriberCsv,
                method: 'GET',
                data: requestBody,

                // enabling loader
                beforeSend: function () {
                    jQuery('.js-wpSmsUploadButton').attr('disabled', 'disabled')
                    jQuery('.wpsms-sendsms__overlay').css('display', 'flex')
                },

                // successful request
                success: function (request, data, xhr) {
                    importButton.prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')

                    location.reload()
                },

                // failed request
                error: function (data, response, xhr) {
                    importButton.prop('disabled', false)
                    jQuery('.wpsms-sendsms__overlay').css('display', 'none')
                    console.log('failed')
                }
            })

        }.bind(this))
    },

}