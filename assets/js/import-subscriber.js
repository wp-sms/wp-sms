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
        this.disableSelectedOptions()
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

            if (jQuery('.js-wpSmsFileHasHeader').is(':checked')) {
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
                    jQuery('.js-wpSmsOverlay').css('display', 'flex')
                },

                // successful request
                success: function (response, data, xhr) {
                    setTimeout(function () {

                        uploadButton.prop('disabled', false)
                        jQuery('.js-wpSmsOverlay').css('display', 'none')
                        jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').removeClass('notice notice-error')
                        jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').addClass('notice notice-success')
                        jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').html('<p>' + response.data + '</p>')
                        jQuery('.js-wpSmsImportPopup').removeClass('hidden')
                        jQuery('.js-wpSmsImportPopup').addClass('not-hidden')
                        jQuery('.js-WpSmsHiddenAfterUpload').css('display', 'none')
                        jQuery('#first-row-label').css('display', 'block')
                        uploadButton.css('display', 'none')
                        importButton.css('display', 'block')

                        var firstRow = JSON.parse(xhr.getResponseHeader("X-FirstRow-content"))

                        firstRow.forEach(function (item) {
                            jQuery('.js-wpSmsGroupSelect').before(
                                '<tr class="wp-sms-data-type-row js-wpSmsDataTypeRow">' +
                                '<td class="wp-sms-data-type-header">' +
                                item +
                                '</td>' +
                                '<td class="wp-sms-data-type-select-tag">' +
                                '<select class="import-column-type js-wpSmsImportColumnType">' +
                                '<option value="0">Please Select</option>' +
                                '<option value="name">Name</option>' +
                                '<option value="mobile">Mobile</option>' +
                                '<option value="group">Group ID</option>' +
                                '</select>' +
                                '</td>' +
                                '</tr>'
                            )
                        })

                    }, 1000)
                },

                // failed request
                error: function (data, response, xhr) {
                    uploadButton.prop('disabled', false)

                    //disable loading spinner
                    jQuery('.js-wpSmsOverlay').css('display', 'none')

                    //print error messages
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').removeClass('notice notice-success')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').addClass('notice notice-error')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').html("<p>" + data.responseJSON.data + "</p>");
                    jQuery('.js-wpSmsImportPopup').removeClass('hidden')
                    jQuery('.js-wpSmsImportPopup').addClass('not-hidden')
                }

            })

        }.bind(this))
    },

    selectColumnFileHeaderEventListener: function () {
        jQuery('body').on('change', '.js-wpSmsImportColumnType', function (event) {
            var isGroupSelected = false

            jQuery('.js-wpSmsImportColumnType').each(function () {
                // check if the group id is selected
                if (jQuery(this).val() === 'group') {
                    isGroupSelected = true
                }
            })

            if (isGroupSelected) {
                jQuery('.js-wpSmsGroupSelect').css('display', 'none')
            } else {
                jQuery('.js-wpSmsGroupSelect').css('display', 'block')
            }
        })

    },

    selectOrAddGroup: function () {
        jQuery('body').on('change', '.js-wpSmsGroupSelect select', function (event) {
            if (jQuery('.js-wpSmsGroupSelect select').val() === 'new_group') {
                jQuery('.js-wpSmsGroupName').css('display', 'block')
            } else {
                jQuery('.js-wpSmsGroupName').css('display', 'none')
            }
        })
    },

    disableSelectedOptions: function () {
        jQuery('body').on('change', '.js-wpSmsImportColumnType', function (event) {

            var selectedOptions = []

            jQuery('.js-wpSmsImportColumnType').each(function () {
                var value = jQuery(this).val()
                if (value !== '0' && !selectedOptions.includes(value)) {
                    selectedOptions.push(value)
                }

                jQuery('.js-wpSmsImportColumnType option').each(function () {
                    if (!selectedOptions.includes(jQuery(this).val())) {
                        jQuery(this).attr('disabled', false)
                    }
                })

                jQuery('.js-wpSmsImportColumnType option').each(function () {
                    if (selectedOptions.includes(jQuery(this).val())) {
                        jQuery(this).attr('disabled', true)
                    }
                })
            })
        })
    },

    importEventListener: function () {
        this.importButton.on('click', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let importButton = jQuery('.js-wpSmsImportButton')
            let requestBody = {}
            let selectGroupColumn = jQuery('.js-wpSmsImportColumnType')

            selectGroupColumn.each(function (index) {
                if (jQuery(this).find('option:selected').val() !== '0') {
                    var objectKey = jQuery(this).find('option:selected').val()
                    requestBody[objectKey] = index
                }
            })

            if (!requestBody.group) {
                var selectedGroupOption = jQuery('.js-wpSmsGroupSelect select').val()
                var groupName = jQuery('.js-wpSmsSelectGroupName').val()

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

            if (jQuery('.js-wpSmsFileHasHeader').is(':checked')) {
                requestBody.hasHeader = true
            }

            jQuery.ajax({
                url: wpSmsGlobalTemplateVar.importSubscriberCsv,
                method: 'GET',
                data: requestBody,

                // enabling loader
                beforeSend: function () {
                    jQuery('.js-wpSmsUploadButton').attr('disabled', 'disabled')
                    jQuery('.js-wpSmsOverlay').css('display', 'flex')
                },

                // successful request
                success: function (request, data, response) {
                    importButton.prop('disabled', false)
                    jQuery('.js-wpSmsOverlay').css('display', 'none')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').removeClass('notice notice-error')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').addClass('notice notice-success')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').html('<p>' + response.responseJSON.data + '</p>')
                    jQuery('.js-wpSmsImportPopup').removeClass('hidden')
                    jQuery('.js-wpSmsImportPopup').addClass('not-hidden')

                    setTimeout(function () {
                        location.reload()
                    }, 1000)
                },

                // failed request
                error: function (data, response, xhr) {
                    importButton.prop('disabled', false)
                    jQuery('.js-wpSmsOverlay').css('display', 'none')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').removeClass('notice notice-success')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').addClass('notice notice-error')
                    jQuery('.js-wpSmsImportPopup .js-wpSmsPopupMessage').html("<p>" + data.responseJSON.data + "</p>");
                    jQuery('.js-wpSmsImportPopup').removeClass('hidden')
                    jQuery('.js-wpSmsImportPopup').addClass('not-hidden')
                }
            })

        }.bind(this))
    },

}