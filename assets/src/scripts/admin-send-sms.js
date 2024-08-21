jQuery(document).ready(function () {
    wpsmsRepeatingMessages.init();

    jQuery("#wp_get_message").counter({
        count: 'up',
        goal: 'sky',
        msg: WP_Sms_Admin_Object.messageMsg
    });

    if (WP_Sms_Admin_Object.proIsActive) {
        // Ensure the flatpickr function is available
        if (typeof jQuery("#datepicker").flatpickr === 'function') {
            jQuery("#datepicker").flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i:00",
                time_24hr: true,
                minuteIncrement: 10, // should be a number, not a string
                minDate: WP_Sms_Admin_Object.currentDateTime,
                disableMobile: true,
                defaultDate: WP_Sms_Admin_Object.currentDateTime
            });
        }

        // Event listener for schedule status checkbox
        jQuery("#schedule_status").on('change', function () {
            if (jQuery(this).is(":checked")) {
                jQuery('#schedule_date').show();
            } else {
                jQuery('#schedule_date').hide();
            }
        });
    }

    jQuery(".preview__message__number").html(jQuery("#wp_get_sender").val());

    if (jQuery("#wp_get_message").val()) {
        jQuery(".preview__message__message").html(jQuery("#wp_get_message").val());
    }

    jQuery("#wp_get_sender").on('keyup', function () {
        jQuery(".preview__message__number").html(jQuery("#wp_get_sender").val());
    });

    jQuery("#wp_get_message").on('keyup', function () {
        messageAutoScroll();
        var message = jQuery("#wp_get_message").val();
        var messageWithLineBreak = message.replace(/(\r\n|\n|\r)/gm, "<br>");
        jQuery(".preview__message__message").html(messageWithLineBreak);
        isRtl("#wp_get_message", ".preview__message__message");
    });


    // For receivers in message preview
    function updateReceiverPreview() {
        let toFieldValue = jQuery("#select_sender").find('option:selected').text();
        jQuery(".preview__message__receiver").text(toFieldValue);
    }

    updateReceiverPreview();

    jQuery("#select_sender").on('change', function () {
        updateReceiverPreview();
    });

    jQuery('button[name="SendSMS"]').on('click', function (e) {
        e.preventDefault();
        sendSMS();
    });

    jQuery('#SendSMSAgain').on('click', function () {
        jQuery('.sendsms-content .summary').fadeOut();
        jQuery('#content').trigger('click');
        jQuery('button[name="SendSMS"]').removeClass('inactive');
        hideResult();
    });

    function hideResult() {
        jQuery('.wpsms-sendsms-result').fadeOut();
    }

    jQuery('.sendsms-content .previous-button').on('click', hideResult);
    jQuery('.sendsms-content .next-button').on('click', hideResult);
    jQuery('.sendsms-tabs .tab').on('click', hideResult);

    /**
     * Upload Media
     */
    var $uploadButton = jQuery('.wpsms-upload-button')
    var $removeButton = jQuery('.wpsms-remove-button')
    var $imageElement = jQuery('.wpsms-mms-image')

    // on upload button click
    $uploadButton.on('click', function (e) {
        e.preventDefault();

        var button = jQuery(this),
            wpsms_uploader = wp.media({
                title: 'Insert image',
                library: {
                    type: ['image']
                },
                button: {
                    text: 'Use this image'
                },
                multiple: false
            }).on('select', function () {
                var attachment = wpsms_uploader.state().get('selection').first().toJSON();

                button.html('<img width="300" src="' + attachment.url + '">');
                $imageElement.val(attachment.url)
                $removeButton.show()

            }).open();
    })

    // on remove button click
    $removeButton.on('click', function (e) {
        e.preventDefault();

        jQuery(this).hide()
        $imageElement.val('')
        $uploadButton.html('Upload image')
    });


    /**
     * Manage Send SMS New Page
     */
    let WpSendSMSPageManager = {

        getFields: function () {
            this.fields = {
                contentTab: {
                    element: jQuery('.wpsms-sendsms .tab#content'),
                },
                receiverTab: {
                    element: jQuery('.wpsms-sendsms .tab#receiver'),
                },
                optionsTab: {
                    element: jQuery('.wpsms-sendsms .tab#options'),
                },
                sendTab: {
                    element: jQuery('.wpsms-sendsms .tab#send'),
                },
                allTab: {
                    element: jQuery('.wpsms-sendsms .tab'),
                },
                fromField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .from-field'),
                },
                toField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .to-field'),
                },
                groupField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-group-field'),
                },
                usersField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-users-field'),
                },
                searchUserField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-search-user-field'),
                },
                numbersField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .wpsms-numbers-field'),
                },
                bulkField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .bulk-field'),
                },
                contentField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .content-field'),
                },
                mmsMediaField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .mms-media-field'),
                },
                scheduleField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .schedule-field'),
                },
                setDateField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .set-date-field'),
                },
                repeatField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .repeat-field'),
                },
                repeatEveryField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .repeat-every-field'),
                },
                repeatEndField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .repeat-end-field'),
                },
                flashField: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .flash-field'),
                },
                summary: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .summary'),
                },
                submitButton: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .sendsms-button'),
                },
                sendAgainButton: {
                    element: jQuery('.wpsms-sendsms .sendsms-content .sendsms-again-button'),
                },
                nextButton: {
                    element: jQuery('#wpbody-content .next-button'),
                },
                prevButton: {
                    element: jQuery('#wpbody-content .previous-button'),
                }
            }
        },

        addEventListener: function () {
            let self = this;
            self.manageNavigationKeys()

            self.fields.allTab.element.on('click', function () {
                self.fields.allTab.element.removeClass('active passed')
                jQuery(this).addClass('active')

                let prevElements = jQuery(this).prevAll()
                prevElements.addClass('passed')
                self.manageFieldsVisibility()
                self.manageNavigationKeys()
            });


            self.fields.nextButton.element.on('click', function () {
                let activeTab = jQuery('.wpsms-sendsms .tab.active')
                let nextTab = activeTab.next('.tab')

                if (nextTab.length > 0) {
                    self.fields.allTab.element.removeClass('active passed')
                    nextTab.addClass('active');
                    let prevElements = nextTab.prevAll()
                    prevElements.addClass('passed')
                    self.manageFieldsVisibility()
                }
                self.manageNavigationKeys()
            });

            self.fields.prevButton.element.on('click', function () {
                let activeTab = jQuery('.wpsms-sendsms .tab.active')
                let prevTab = activeTab.prev('.tab')

                if (prevTab.length > 0) {
                    self.fields.allTab.element.removeClass('active passed')
                    prevTab.addClass('active');
                    let prevElements = prevTab.prevAll()
                    prevElements.addClass('passed')
                    self.manageFieldsVisibility()
                }
                self.manageNavigationKeys()
            });

            self.fields.toField.element.find('select').on('change', function () {
                self.manageRecipients()
            });

            self.fields.scheduleField.element.find('input[type="checkbox"]').on('change', function () {
                self.manageProOptions()
            });

            self.fields.repeatField.element.find('input[type="checkbox"]').on('change', function () {
                self.manageProOptions()
            });
        },

        manageProOptions: function () {
            let activeTab = jQuery('.wpsms-sendsms .tab.active')
            let activeTabId = activeTab.attr("id")
            let scheduleFieldState = jQuery("#schedule_status").is(":checked")
            let repeatFieldState = jQuery("#wpsms_repeat_status").is(":checked")

            if (activeTabId == 'options' && scheduleFieldState) {
                this.fields.setDateField.element.fadeIn()
                this.fields.repeatField.element.fadeIn()
            } else {
                this.fields.setDateField.element.hide()
                this.fields.repeatField.element.hide()
            }

            if (activeTabId == 'options' && scheduleFieldState && repeatFieldState) {
                this.fields.repeatEveryField.element.fadeIn()
                this.fields.repeatEndField.element.fadeIn()
            } else {
                this.fields.repeatEveryField.element.hide()
                this.fields.repeatEndField.element.hide()
            }
        },

        manageNavigationKeys: function () {
            let activeTab = jQuery('.wpsms-sendsms .tab.active')
            let prevTab = activeTab.prev('.tab')
            let prevTabs = activeTab.prevAll()

            let nextTab = activeTab.next('.tab')
            let nextTabs = activeTab.nextAll()

            if (nextTabs.length < 1) {
                this.fields.nextButton.element.addClass('inactive')
            } else {
                this.fields.nextButton.element.removeClass('inactive')
            }

            if (prevTabs.length < 1) {
                this.fields.prevButton.element.addClass('inactive')
            } else {
                this.fields.prevButton.element.removeClass('inactive')
            }
        },

        manageFieldsVisibility: function () {
            let activeTab = jQuery('.wpsms-sendsms .tab.active')
            let activeTabId = activeTab.attr("id")


            // Firstly hide all fields
            const fields = [
                this.fields.fromField,
                this.fields.toField,
                this.fields.searchUserField,
                this.fields.groupField,
                this.fields.usersField,
                this.fields.numbersField,
                this.fields.bulkField,
                this.fields.contentField,
                this.fields.mmsMediaField,
                this.fields.scheduleField,
                this.fields.setDateField,
                this.fields.repeatField,
                this.fields.repeatEveryField,
                this.fields.repeatEndField,
                this.fields.flashField,
                this.fields.summary,
                this.fields.submitButton,
                this.fields.sendAgainButton
            ];

            // Loop through the fields and hide each one
            for (const field of fields) {
                field.element.hide();
            }

            // Disable send sms button
            this.fields.submitButton.element.prop('disabled', true);

            // Secondly show fields based on the selected tab
            switch (activeTabId) {
                case 'content':
                    this.fields.contentField.element.fadeIn()
                    break;

                case 'receiver':
                    this.fields.fromField.element.fadeIn()
                    this.fields.toField.element.fadeIn()
                    this.manageRecipients()
                    break;

                case 'options':
                    this.fields.bulkField.element.fadeIn()
                    this.fields.mmsMediaField.element.fadeIn()
                    this.fields.scheduleField.element.fadeIn()
                    this.fields.flashField.element.fadeIn()
                    this.manageProOptions()
                    break;

                case 'send':
                    this.fields.summary.element.fadeIn()
                    this.fields.submitButton.element.fadeIn()
                    this.fields.submitButton.element.prop('disabled', false);
                    break;
            }
        },

        manageRecipients: function () {
            let activeTabId = jQuery('.wpsms-sendsms .tab.active').attr("id")
            let toFieldState = this.fields.toField.element.find('select option:selected').attr("id")
            if (activeTabId !== 'receiver') {
                return
            }

            // Firstly hide all the related fields
            jQuery(".wpsms-value").hide();

            switch (toFieldState) {
                case 'wp_subscribe_username':
                    jQuery(".wpsms-group").fadeIn();
                    break;

                case 'wp_roles':
                    jQuery(".wpsms-roles").fadeIn();
                    break;

                case 'wp_users':
                    jQuery(".wpsms-users").fadeIn();
                    break;

                case 'wc_users':
                    jQuery(".wpsms-wc-users").fadeIn();
                    break;

                case 'bp_users':
                    jQuery(".wpsms-bp-users").fadeIn();
                    jQuery(".wpsms-search-user-field").fadeIn();
                    break;

                case 'wp_tellephone':
                    jQuery(".wpsms-numbers").fadeIn();
                    jQuery("#wp_get_number").focus();
                    break;

                case 'wp_role':
                    jQuery(".wprole-group").fadeIn();
                    break;
            }
        },

        addSearchUserEventListener: function () {
            var selectedOptions = [];
            let selectElement = jQuery('.wpsms-sendsms .wpsms-search-user select.js-wpsms-select2');

            // Store selected options when an option is selected
            selectElement.on('select2:select', function (e) {
                let selectedOption = e.params.data;
                if (selectedOption) {

                    // Check if the selected option is not already in the selectedOptions array
                    const index = selectedOptions.findIndex(option => option.id == selectedOption.id);
                    if (index == -1) {
                        selectedOptions.push(selectedOption);
                    }
                }
            });

            // Remove unselected option when an option is unselected
            selectElement.on('select2:unselect', function (e) {
                let unselectedOption = e.params.data;
                if (unselectedOption) {

                    // Check if the selected option is not already in the selectedOptions array
                    const indexToRemove = selectedOptions.findIndex(option => option.id == unselectedOption.id);
                    if (indexToRemove !== -1) {
                        selectedOptions.splice(indexToRemove, 1)
                    }
                }
            });

            selectElement.select2({
                ajax: {
                    url: WP_Sms_Admin_Object.restUrls.users,
                    method: 'GET',
                    dataType: 'json',
                    headers: {
                        'X-WP-Nonce': WP_Sms_Admin_Object.nonce,
                    },
                    data: function (params) {
                        return {
                            search: params.term,
                        };
                    },

                    processResults: function (users) {
                        let results = [];
                        // Process each user
                        users.forEach(function (user) {
                            if (user.id && user.id > 0) {
                                optionTitle = user.slug + ' ( ' + user.name + ' )';
                                // Check if the user is not already in the selectedOptions array
                                const index = selectedOptions.findIndex(option => option.id == user.id);
                                if (index == -1) {
                                    results.push({
                                        id: user.id,
                                        text: optionTitle,
                                    });
                                }
                            }
                        });

                        // Return the processed results
                        return {
                            results: results,
                        };
                    },
                },

                templateResult: function (result) {
                    return jQuery('<span>' + result.text + '</span>');
                },

                escapeMarkup: function (markup) {
                    return markup;
                },
            });
        },

        init: function () {
            this.getFields();
            this.addEventListener();
            this.addSearchUserEventListener();
            this.manageFieldsVisibility();
        }
    }

    WpSendSMSPageManager.init();

});

function isRtl(input, output) {
    jQuery(input).off('keypress').on('keypress', function (e) {
        setTimeout(function () {
            if (jQuery(input).val().length > 1) {
                return;
            } else {
                const RTL_Regex = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/;
                const isRTL = RTL_Regex.test(String.fromCharCode(e.which));
                const Direction = isRTL ? 'rtl' : 'ltr';
                jQuery(input).css({'direction': Direction});
                if (isRTL) {
                    jQuery(output).css({'direction': 'rtl'});
                } else {
                    jQuery(output).css({'direction': 'ltr'});
                }
            }
        });
    });
}

function scrollToTop() {
    jQuery('html, body').animate({scrollTop: 0}, 1000);
}

function closeNotice() {
    jQuery(".wpsms-sendsms-result").fadeOut();
}

function clearForm() {
    jQuery(".preview__message__message").html('')
    jQuery("#repeat-interval").val(1);
    jQuery("#repeat-forever").prop("checked", false);
    jQuery("#schedule_status").prop("checked", false);
    jQuery("#wpsms_repeat_status").prop("checked", false);
    jQuery("#repeat-interval-unit").val("day");
    jQuery(".wpsms-mms-image").val([]).trigger('change');
    jQuery(".js-wpsms-select2").val([]).trigger('change');
    jQuery("#wp_get_number").val('').trigger('change');
    jQuery("#wp_get_message").val('').trigger('change');
}

function sendSMS() {
    let smsFrom = jQuery("#wp_get_sender").val(),
        smsTo = {type: jQuery("select[name='wp_send_to'] option:selected").val()},
        smsMessage = jQuery("#wp_get_message").val(),
        smsMedia = jQuery(".wpsms-mms-image").val(),
        smsScheduled = {scheduled: jQuery("#schedule_status").is(":checked")},
        smsRepeating = wpsmsRepeatingMessages.getData(),
        smsFlash = jQuery('[name="wp_flash"]:checked').val();

    if (smsTo.type === "subscribers") {
        smsTo.groups = jQuery('.wpsms-group select[name="wpsms_groups[]"]').val();
    } else if (smsTo.type === "roles") {
        smsTo.roles = jQuery('select[name="wpsms_roles[]"]').val();
    } else if (smsTo.type === "users") {
        smsTo.users = jQuery('select[name="wpsms_users[]"]').val();
    } else if (smsTo.type === "numbers") {
        smsTo.numbers = jQuery('textarea[name="wp_get_number"]').val();
        smsTo.numbers = smsTo.numbers.replace(/\n/g, ",").split(",");
    }

    if (smsScheduled.scheduled) {
        smsScheduled.date = jQuery("#datepicker").val();
    }

    let requestBody = {
        sender: smsFrom,
        recipients: smsTo.type,
        group_ids: smsTo.groups,
        role_ids: smsTo.roles,
        users: smsTo.users,
        message: smsMessage,
        numbers: smsTo.numbers,
        flash: smsFlash,
        media_urls: [smsMedia],
        schedule: smsScheduled.date,
        repeat: smsRepeating,
    };

    requestBody = wp.hooks.applyFilters('wp_sms_send_request_body', requestBody);

    jQuery('.wpsms-sendsms-result').fadeOut();

    jQuery.ajax(WP_Sms_Admin_Object.restUrls.sendSms,
        {
            headers: {'X-WP-Nonce': WP_Sms_Admin_Object.nonce},
            dataType: 'json',
            type: 'post',
            contentType: 'application/json',
            data: JSON.stringify(requestBody),
            beforeSend: function () {
                jQuery(".wpsms-sendsms__overlay").css('display', 'flex');
                jQuery('button[name="SendSMS"]').fadeOut();
            },
            success: function (data, status, xhr) {
                Object.keys(smsTo).forEach(key => {
                    delete smsTo[key];
                })
                jQuery(".wpsms-remove-button").trigger('click');
                jQuery(".wpsms-sendsms__overlay").css('display', 'none');
                jQuery('.wpsms-sendsms-result').removeClass('error');
                jQuery('.wpsms-sendsms-result').addClass('success');
                jQuery('.wpsms-sendsms-result p').html(data.message);
                jQuery('#wpsms_account_credit').html(data.data.balance);
                jQuery('.wpsms-sendsms-result').fadeIn();
                jQuery('#SendSMSAgain').fadeIn();
                // clearForm();
                scrollToTop();
            },
            error: function (data, status, xhr) {
                jQuery('.wpsms-sendsms-result').removeClass('success');
                jQuery('.wpsms-sendsms-result').addClass('error');
                jQuery('.wpsms-sendsms-result p').html(data.responseJSON.error.message);
                jQuery('.wpsms-sendsms-result').fadeIn();
                jQuery(".wpsms-sendsms__overlay").css('display', 'none');
                jQuery('button[name="SendSMS"]').removeClass('inactive');
                scrollToTop();
            }
        });
}

function messageAutoScroll() {
    jQuery('.preview__message__message-wrapper').scrollTop(jQuery('.preview__message__message').height());
}

const wpsmsRepeatingMessages = {
    init: function () {
        if (!WP_Sms_Admin_Object.proIsActive) return
        this.setElements()
        this.initElements()
        this.handleFieldsVisibility()
        this.handleEndDateField()
    },

    setElements: function () {
        this.elements = {
            statusCheckbox: jQuery('#wpsms_repeat_status'),
            parentCheckbox: jQuery('#schedule_status'),
            subFields: jQuery('.repeat-subfield'),
            repeatInterval: jQuery('#repeat-interval'),
            repeatUnit: jQuery('#repeat-interval-unit'),
            endDatepicker: jQuery('#repeat_ends_on'),
            foreverCheckbox: jQuery('#repeat-forever'),
        }

    },

    initElements: function () {
        // Ensure the endDatepicker element exists and flatpickr is a function
        if (this.elements.endDatepicker && typeof this.elements.endDatepicker.flatpickr === 'function') {
            this.elements.endDatepicker.flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i:00",
                time_24hr: true,
                minuteIncrement: 10, // should be a number, not a string
                minDate: WP_Sms_Admin_Object.currentDateTime,
                disableMobile: true,
                defaultDate: WP_Sms_Admin_Object.currentDateTime
            });
        }
    },

    handleFieldsVisibility: function () {
        const handler = function () {
            if (this.elements.parentCheckbox.is(':checked')) {
                this.elements.statusCheckbox.closest('tr').show()
            } else {
                this.elements.statusCheckbox.closest('tr').hide()
            }

            if (this.elements.parentCheckbox.is(':checked') && this.elements.statusCheckbox.is(':checked')) {
                this.elements.subFields.show()
                this.isActive = true
            } else {
                this.elements.subFields.hide()
                this.isActive = false
            }
        }.bind(this)

        handler();

        //Event listeners
        this.elements.statusCheckbox.on('change', handler)
        this.elements.parentCheckbox.on('change', handler)
    },

    handleEndDateField: function () {
        const handler = function () {
            if (this.elements.foreverCheckbox.is(':checked')) {
                this.elements.endDatepicker.attr('disabled', 'disabled')
            } else {
                this.elements.endDatepicker.prop('disabled', false)
            }
        }.bind(this)

        handler()

        //Event listener
        this.elements.foreverCheckbox.on('change', handler)
    },

    getData: function () {

        if (!this.isActive) return

        const elements = this.elements
        const data = {
            interval: {
                value: elements.repeatInterval.val(),
                unit: elements.repeatUnit.val()
            }
        }
        elements.foreverCheckbox.is(':checked') ? (data.repeatForever = true) : (data.endDate = elements.endDatepicker.val())

        return data
    }

}