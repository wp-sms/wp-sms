jQuery(document).ready(function () {
    wpSmsImportSubscriber.init();
});

let wpSmsImportSubscriber = {

    init: function () {
        this.setFields()
        this.uploadEventListener()
        this.selectColumnFileHeaderEventListener()
        this.selectOrAddGroup()
        this.disableSelectedOptions()
        this.bindImportRequestBody()
    },

    setFields: function () {
        this.uploadForm = jQuery('.js-wpSmsUploadForm')
        this.importButton = jQuery('.js-wpSmsImportButton')
        this.uploadButton = jQuery('.js-wpSmsUploadButton')
        this.loadingSpinner = jQuery('.js-wpSmsOverlay')
        this.messageModal = jQuery('.js-wpSmsMessageModal')
        this.modalErrorMessage = jQuery('.js-wpSmsErrorMessage')
        this.hasHeader = jQuery('.js-wpSmsFileHasHeader')

        this.requestBody = {}
    },

    uploadEventListener: function () {
        let $this = this

        this.uploadForm.on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let fileData = jQuery('#wp-sms-input-file')[0].files
            let fromData = new FormData()

            if (fileData.length > 0) {
                fromData.append('file', fileData[0])
            }

            // check whether the file has header
            let hasHeader = false

            if ($this.hasHeader.is(':checked')) {
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
                    $this.uploadButton.attr('disabled', 'disabled')
                    $this.loadingSpinner.css('display', 'flex')
                },

                // successful request
                success: function (response, data, xhr) {
                    setTimeout(function () {

                        $this.uploadButton.prop('disabled', false)
                        $this.loadingSpinner.css('display', 'none')
                        $this.modalErrorMessage.removeClass('notice notice-error')
                        $this.modalErrorMessage.addClass('notice notice-success')
                        $this.modalErrorMessage.html('<p>' + response.data + '</p>')
                        $this.messageModal.removeClass('hidden')
                        $this.messageModal.addClass('not-hidden')
                        jQuery('.js-WpSmsHiddenAfterUpload').css('display', 'none')
                        jQuery('#first-row-label').css('display', 'block')
                        $this.uploadButton.css('display', 'none')
                        $this.importButton.css('display', 'block')

                        let firstRow = JSON.parse(xhr.getResponseHeader("X-FirstRow-content"))

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
                    $this.uploadButton.prop('disabled', false)

                    //disable loading spinner
                    $this.loadingSpinner.css('display', 'none')

                    //print error messages
                    $this.modalErrorMessage.removeClass('notice notice-success')
                    $this.modalErrorMessage.addClass('notice notice-error')
                    $this.modalErrorMessage.html("<p>" + data.responseJSON.data + "</p>");
                    $this.messageModal.removeClass('hidden')
                    $this.messageModal.addClass('not-hidden')
                }

            })

        }.bind(this))
    },

    selectColumnFileHeaderEventListener: function () {
        jQuery('body').on('change', '.js-wpSmsImportColumnType', function (event) {
            let isGroupSelected = false

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

            let selectedOptions = []

            jQuery('.js-wpSmsImportColumnType').each(function () {
                let value = jQuery(this).val()
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

    bindImportRequestBody: function () {
        let $this = this

        $this.importButton.on('click', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let selectGroupColumn = jQuery('.js-wpSmsImportColumnType')

            selectGroupColumn.each(function (index) {
                if (jQuery(this).find('option:selected').val() !== '0') {
                    let objectKey = jQuery(this).find('option:selected').val()
                    $this.requestBody[objectKey] = index
                }
            })

            if (!$this.requestBody.group) {
                let selectedGroupOption = jQuery('.js-wpSmsGroupSelect select').val()
                let groupName = jQuery('.js-wpSmsSelectGroupName').val()

                switch (selectedGroupOption) {
                    case '0':
                        $this.requestBody['state'] = 0
                        $this.requestBody['group'] = null
                        break

                    case 'new_group':
                        $this.requestBody['state'] = 'new_group'
                        $this.requestBody['group'] = groupName
                        break

                    default:
                        $this.requestBody['state'] = 'existed_group'
                        $this.requestBody['group'] = selectedGroupOption
                        break
                }
            }

            if ($this.hasHeader.is(':checked')) {
                $this.requestBody.hasHeader = true
            }

            //todo

            // $this.loadingSpinner.css('display', 'none')
            // $this.uploadForm.css('display', 'none')
            // $this.messageModal.css('display', 'none')
            // $this.progressBarSection.css('display', 'block')

            $this.importEventListener(0)
        })
    },

    importEventListener: function (startPoint) {
        let $this = this
        $this.requestBody.startPoint = startPoint

        jQuery.ajax({
            url: wpSmsGlobalTemplateVar.importSubscriberCsv,
            method: 'GET',
            data: $this.requestBody,

            // enabling loader
            beforeSend: function () {

            },

            // successful request
            success: function (request, data, response) {

                let isImportDone = response.responseJSON.data.importDone
                let getStartPoint = response.responseJSON.data.startPoint

                if (isImportDone) {
                    // location.reload()
                    return;
                }
                return $this.importEventListener(getStartPoint)
            },

            // failed request
            error: function (data, response, xhr) {

            }
        })
    },
}