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
        this.refreshEventListener()
    },

    setFields: function () {
        this.uploadForm = jQuery('.js-wpSmsUploadForm')
        this.importButton = jQuery('.js-wpSmsImportButton')
        this.uploadButton = jQuery('.js-wpSmsUploadButton')
        this.refreshButton = jQuery('.js-wpSmsRefreshButton')
        this.loadingSpinner = jQuery('.js-wpSmsOverlay')
        this.messageModal = jQuery('.js-wpSmsMessageModal')
        this.modalErrorMessage = jQuery('.js-wpSmsErrorMessage')
        this.importStep2 = jQuery('.js-WpSmsImportStep2')
        this.hasHeader = jQuery('.js-wpSmsFileHasHeader')
        this.importResult = jQuery('.js-WpSmsImportResult')
        this.importResultTable = jQuery('.js-WpSmsImportResult table tbody')

        this.requestBody = {}
        this.import_result = {}
        this.successUpload = 0
    },

    uploadEventListener: function ($this = this) {

        $this.uploadForm.on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            let fileData = jQuery('#wp-sms-input-file')[0].files
            let formData = new FormData()

            if (fileData.length > 0) {
                formData.append('file', fileData[0])
            }

            // check whether the file has header
            let hasHeader = false

            if ($this.hasHeader.is(':checked')) {
                hasHeader = true
            }

            // send AJAX request
            jQuery.ajax({
                url: WP_Sms_Admin_Object.ajaxUrls.uploadSubscriberCsv + '&hasHeader=' + hasHeader,
                method: 'post',
                data: formData,
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
                        $this.loadingSpinner.hide()
                        $this.modalErrorMessage.removeClass('notice notice-error')
                        $this.modalErrorMessage.addClass('notice notice-success')
                        $this.modalErrorMessage.html('<p>' + response.data + '</p>')
                        $this.messageModal.removeClass('hidden')
                        $this.messageModal.addClass('not-hidden')
                        jQuery('.js-WpSmsImportStep1').css('display', 'none')
                        jQuery('#first-row-label').css('display', 'block')
                        $this.uploadButton.hide()
                        $this.importButton.show()

                        let firstRow = JSON.parse(xhr.getResponseHeader("X-FirstRow-content"))

                        firstRow.forEach(function (item) {
                            jQuery('.js-wpSmsGroupSelect').before(
                                '<tr class="js-wpSmsDataTypeRow">' +
                                '<td>' + item + '</td>' +
                                '<td><span class="dashicons dashicons-arrow-right-alt"></span></td>' +
                                '<td>' +
                                '<select class="js-wpSmsImportColumnType">' +
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

    bindImportRequestBody: function ($this = this) {
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

            jQuery('#TB_ajaxContent').animate({scrollTop: '0px'}, 300);

            $this.importEventListener(0)
        })
    },

    importEventListener: function (startPoint, $this = this) {

        $this.requestBody.startPoint = startPoint

        jQuery.ajax({
            url: WP_Sms_Admin_Object.ajaxUrls.importSubscriberCsv,
            method: 'GET',
            data: $this.requestBody,

            // enabling loader
            beforeSend: function () {
                $this.uploadButton.attr('disabled', 'disabled')
                $this.loadingSpinner.css('display', 'flex')
            },

            // successful request
            success: function (request, data, response) {

                let isImportDone = response.responseJSON.data.importDone
                let getStartPoint = response.responseJSON.data.startPoint
                let totalSubscriber = response.responseJSON.data.count
                let errors = response.responseJSON.data.errors

                if (!isImportDone) {
                    for (var [key, value] of Object.entries(errors)) {
                        $this.import_result[key] = value
                    }
                }

                if (response.responseJSON.data.successUpload) {
                    $this.successUpload += parseInt(response.responseJSON.data.successUpload)
                }

                if (isImportDone) {
                    //disable loading spinner
                    $this.loadingSpinner.css('display', 'none')

                    $this.importStep2.css('display', 'none')
                    $this.importButton.css('display', 'none')
                    $this.refreshButton.css('display', 'block')

                    //print error messages and result
                    $this.messageModal.removeClass('hidden')
                    $this.messageModal.addClass('not-hidden')
                    $this.modalErrorMessage.removeClass('notice-error')
                    $this.modalErrorMessage.addClass('notice-success')

                    var $alert_message

                    switch ($this.successUpload) {
                        case totalSubscriber:
                            $alert_message = '<p>Subscribers have been imported successfully!</p>'
                            break

                        case 0:
                            $this.modalErrorMessage.removeClass('notice-success')
                            $this.modalErrorMessage.addClass('notice-error')

                            $alert_message = '<p>Subscribers have not been imported. Look for errors in the logs.</p>'
                            break

                        default:
                            $alert_message = '<p>' + $this.successUpload + ' of ' + totalSubscriber + ' subscribers have been imported successfully!</p>'
                    }

                    $this.modalErrorMessage.html($alert_message)

                    if (!jQuery.isEmptyObject($this.import_result)) {

                        $this.importResult.show()

                        for (var [number, failureMessage] of Object.entries($this.import_result)) {

                            $this.importResultTable.append(
                                "<tr><td><code>" + number + "</code></td><td>" + failureMessage + "</td></tr>"
                            )
                        }
                    }

                    return
                }
                return $this.importEventListener(getStartPoint)
            },

            // failed request
            error: function (response) {
                $this.uploadButton.prop('disabled', false)

                //disable loading spinner
                $this.loadingSpinner.css('display', 'none')

                //print error messages
                $this.messageModal.removeClass('hidden')
                $this.messageModal.addClass('not-hidden')
                $this.modalErrorMessage.removeClass('notice notice-success')
                $this.modalErrorMessage.addClass('notice notice-error')
                $this.modalErrorMessage.html("<p>" + response.responseJSON.data + "</p>");
            }
        })
    },

    refreshEventListener: function ($this = this) {
        $this.refreshButton.on('click', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            window.location.reload();

        })
    }
}