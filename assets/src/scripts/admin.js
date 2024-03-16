jQuery(document).ready(function ($) {

    WpSMSGeneral.init();
    WpSmsNotifications.init();
    WpSmsWoocommerce.init();
    WpSmsJobManager.init();

    if (jQuery('#subscribe-meta-box').length) {
        WpSmsMetaBox.init();
    }

    if (jQuery('#wpcf7-contact-form-editor').length && jQuery('#wpsms-tab').length) {
        WpSmsContactForm7.init();
    }

    if (jQuery('.js-wpsms-chatbox-preview').length) {
        jQuery('.wpsms-chatbox').hide();
        $('.js-wpsms-chatbox-preview').click(function (e) {
            e.preventDefault();
            $('.wpsms-chatbox').fadeToggle();
        });
    }

    let WpSmsSelect2 = $('.js-wpsms-select2')
    let WpSmsExportForm = $('.js-wpSmsExportForm')

    function matchCustom(params, data) {
        // If there are no search terms, return all of the data
        if ($.trim(params.term) === '') {
            return data;
        }

        // Do not display the item if there is no 'text' property
        if (typeof data.text === 'undefined') {
            return null;
        }

        // `params.term` should be the term that is used for searching
        // `data.text` is the text that is displayed for the data object
        if (data.text.indexOf(params.term) > -1 || data.element.getAttribute('value') !== null && data.element.getAttribute('value').toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
            var modifiedData = $.extend({}, data, true);
            modifiedData.text += ' (matched)';

            // You can return modified objects from here
            // This includes matching the `children` how you want in nested data sets
            return modifiedData;
        }

        // Return `null` if the term should not be displayed
        return null;
    }

    const WpSmsSelect2Options = {
        placeholder: "Please select",
    };

    if (WpSmsExportForm.length) {
        WpSmsSelect2Options.dropdownParent = WpSmsSelect2.parent()
    }

    // Select2
    window.WpSmsSelect2 = WpSmsSelect2;
    WpSmsSelect2.select2(WpSmsSelect2Options);

    // Auto submit the gateways form, after changing value
    $("#wpsms_settings\\[gateway_name\\]").on('change', function () {
        $('input[name="submit"]').click();
    });

    //Initiate Color Picker
    if ($('.wpsms-color-picker').length) {
        $('.wpsms-color-picker').wpColorPicker();
    }
    ;

    if ($('.repeater').length) {
        $('.repeater').repeater({
            initEmpty: false,
            show: function () {
                $(this).slideDown();

                const uploadField = $(this).find('.wpsms_settings_upload_field');
                const uploadButton = $(this).find('.wpsms_settings_upload_button');
                // Check if repeater has upload filed
                if (uploadField.length && uploadButton.length) {
                    // Create unique ID based on element's index
                    const newFieldIndex = uploadButton.closest('[data-repeater-list]').children().length - 1;
                    const newFieldID = uploadField.attr('id') + '[' + newFieldIndex + ']';
                    // Assign a unique ID to upload fields to prevent conflict
                    uploadField.attr('id', newFieldID);
                    uploadButton.attr('data-target', newFieldID);
                }

                const checkbox = $(this).find('[type="checkbox"]');
                // Check if repeater has checkbox
                if (checkbox.length) {
                    // Create unique ID based on element's index
                    const newFieldIndex = checkbox.closest('[data-repeater-list]').children().length - 1;
                    const newFieldID = checkbox.attr('id') + '[' + newFieldIndex + ']';
                    // Assign a unique ID to checkbox fields to prevent conflict
                    checkbox.attr('id', newFieldID);
                    if (checkbox.next().is('label')) {
                        checkbox.next().attr('for', newFieldID);
                    }
                }
            },
            hide: function (deleteElement) {
                if (confirm('Are you sure you want to delete this item?')) {
                    $(this).slideUp(deleteElement);
                }
            },
            isFirstItemUndeletable: true
        });
    }

    if ($('.wpsms-tooltip').length) {
        $('.wpsms-tooltip').tooltipster({
            theme: 'tooltipster-flat',
            maxWidth: 400,
        });
    }

    // Open WordPress media library when user clicks on upload button
    $(document).on('click', '.wpsms_settings_upload_button', e => {
        const mediaUploader = wp.media({
            library: {
                type: 'image',
            },
            multiple: false,
        });

        mediaUploader.open();

        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            const targetInput = document.getElementById(e.target.dataset.target);
            targetInput.value = attachment.url;
        });
    });
});


/**
 * General
 * @type {{init: WpSMSGeneral.init, alreadyEnabled: ((function(): (boolean|undefined))|*), getFields: WpSMSGeneral.getFields}}
 */
let WpSMSGeneral = {

    getFields: function () {
        this.fields = {
            mobileFieldStatus: {
                element: jQuery("#wpsms_settings\\[add_mobile_field\\]"),
            },
            ultimateMemberFieldSelector: {
                element: jQuery('#wpsms_settings\\[um_sync_field_name\\]'),
            },
            ultimateMemberSyncOldMembersField: {
                element: jQuery('#wpsms_settings\\[um_sync_previous_members\\]'),
            },
            buddyPressFieldSelector: {
                element: jQuery('#wpsms_settings\\[bp_mobile_field_id\\]'),
            },
            buddyPressSyncFields: {
                element: jQuery('#wpsms_settings\\[bp_sync_fields\\]'),
            },
            pmproFieldSelector: {
                element: jQuery('#wpsms_settings\\[pmpro_mobile_field_id\\]'),
            }
        }
    },

    addEventListener: function () {

        // Add event listener for mobile field status
        this.fields.mobileFieldStatus.element.on("change", function () {
                this.manageMobileFieldsVisibility();
            }.bind(this)
        );
    },

    manageMobileFieldsVisibility: function () {
        let mobileFieldValue = this.fields.mobileFieldStatus.element.val()

        // Firstly hide all related fields
        this.fields.buddyPressFieldSelector.element.closest("tr").hide()
        this.fields.buddyPressSyncFields.element.closest("tr").hide()
        this.fields.ultimateMemberFieldSelector.element.closest("tr").hide()
        this.fields.ultimateMemberSyncOldMembersField.element.closest("tr").hide()
        this.fields.pmproFieldSelector.element.closest("tr").hide()

        // Secondly show fields based on the selected mobile field status option
        switch (mobileFieldValue) {
            case 'use_ultimate_member_mobile_field':
                this.fields.ultimateMemberFieldSelector.element.closest("tr").show()
                this.fields.ultimateMemberSyncOldMembersField.element.closest("tr").show()
                break;

            case 'use_buddypress_mobile_field':
                this.fields.buddyPressFieldSelector.element.closest("tr").show()
                this.fields.buddyPressSyncFields.element.closest("tr").show()
                break;

            case 'use_current_field_in_pmpro':
                this.fields.pmproFieldSelector.element.closest("tr").show()
                break;
        }
    },

    init: function () {
        this.getFields();
        this.addEventListener();
        this.manageMobileFieldsVisibility();
    }
}


/**
 * Notifications
 * @type {{init: WpSmsNotifications.init, alreadyEnabled: ((function(): (boolean|undefined))|*), getFields: WpSmsNotifications.getFields}}
 */
let WpSmsNotifications = {

    getFields: function () {
        this.fields = {
            receiverField: {
                element: jQuery('#wpsms_settings\\[notif_publish_new_post_receiver\\]'),
            },
            subscriberField: {
                element: jQuery('#wpsms_settings\\[notif_publish_new_post_default_group\\]'),
            },
            numbersField: {
                element: jQuery('#wpsms_settings\\[notif_publish_new_post_numbers\\]'),
            },
            usersField: {
                element: jQuery('#wpsms_settings\\[notif_publish_new_post_users\\]'),
            }
        }
    },

    hideOrShowFields: function () {
        if (this.fields.receiverField.element.val() === 'subscriber') {
            this.fields.subscriberField.element.closest('tr').show()
            this.fields.numbersField.element.closest('tr').hide()
            this.fields.usersField.element.closest('tr').hide()
        } else if (this.fields.receiverField.element.val() === 'numbers') {
            this.fields.subscriberField.element.closest('tr').hide()
            this.fields.numbersField.element.closest('tr').show()
            this.fields.usersField.element.closest('tr').hide()
        } else if (this.fields.receiverField.element.val() === 'users') {
            this.fields.subscriberField.element.closest('tr').hide()
            this.fields.numbersField.element.closest('tr').hide()
            this.fields.usersField.element.closest('tr').show()
        }
    },

    addEventListener: function () {
        this.fields.receiverField.element.on('change', function () {
            this.hideOrShowFields();
        }.bind(this));
    },

    init: function () {
        this.getFields();
        this.hideOrShowFields();
        this.addEventListener();
    }

}


/**
 * Woocommerce
 * @type {{init: WpSmsWoocommerce.init, alreadyEnabled: ((function(): (boolean|undefined))|*), getFields: WpSmsWoocommerce.getFields}}
 */
let WpSmsWoocommerce = {

    getFields: function () {
        this.fields = {
            newProductSmsReceiverField: {
                element: jQuery('#wps_pp_settings\\[wc_notify_product_receiver\\]'),
            },
            newProductSubscriberField: {
                element: jQuery('#wps_pp_settings\\[wc_notify_product_cat\\]'),
            },
            newProductNumbersField: {
                element: jQuery('#wps_pp_settings\\[wc_notify_product_roles\\]'),
            },
            checkoutMobileField: {
                element: jQuery('#wps_pp_settings\\[wc_mobile_field\\]'),
            },
            mobileFieldNecessity: {
                element: jQuery('#wps_pp_settings\\[wc_mobile_field_optional\\]'),
            }
        }
    },

    hideOrShowNewProductSmsReceiver: function () {
        if (this.fields.newProductSmsReceiverField.element.val() === 'subscriber') {
            this.fields.newProductSubscriberField.element.closest('tr').show()
            this.fields.newProductNumbersField.element.closest('tr').hide()
        } else {
            this.fields.newProductSubscriberField.element.closest('tr').hide()
            this.fields.newProductNumbersField.element.closest('tr').show()
        }
    },

    hideOrShowCheckoutMobileField: function () {
        if (this.fields.checkoutMobileField.element.val() === 'add_new_field') {
            this.fields.mobileFieldNecessity.element.closest('tr').show()
        } else {
            this.fields.mobileFieldNecessity.element.closest('tr').hide()
        }
    },

    newProductSmsReceiverEventListener: function () {
        this.fields.newProductSmsReceiverField.element.on('change', function () {
            this.hideOrShowNewProductSmsReceiver();
        }.bind(this));
    },

    checkoutMobileFieldEventListener: function () {
        this.fields.checkoutMobileField.element.on('change', function () {
            this.hideOrShowCheckoutMobileField();
        }.bind(this));
    },

    init: function () {
        this.getFields();
        this.hideOrShowNewProductSmsReceiver();
        this.hideOrShowCheckoutMobileField();
        this.newProductSmsReceiverEventListener();
        this.checkoutMobileFieldEventListener();
    }

}

/**
 * Job Manager
 * @type {{init: WpSmsJobManager.init, alreadyEnabled: ((function(): (boolean|undefined))|*), getFields: WpSmsJobManager.getFields}}
 */
let WpSmsJobManager = {

    getFields: function () {
        this.fields = {
            receiverField: {
                element: jQuery('#wps_pp_settings\\[job_notify_receiver\\]'),
            },
            subscriberField: {
                element: jQuery('#wps_pp_settings\\[job_notify_receiver_subscribers\\]'),
            },
            numbersField: {
                element: jQuery('#wps_pp_settings\\[job_notify_receiver_numbers\\]'),
            }
        }
    },

    hideOrShowFields: function () {
        if (this.fields.receiverField.element.val() === 'subscriber') {
            this.fields.subscriberField.element.closest('tr').show()
            this.fields.numbersField.element.closest('tr').hide()
        } else {
            this.fields.subscriberField.element.closest('tr').hide()
            this.fields.numbersField.element.closest('tr').show()
        }
    },

    addEventListener: function () {
        this.fields.receiverField.element.on('change', function () {
            this.hideOrShowFields();
        }.bind(this));
    },

    init: function () {
        this.getFields();
        this.hideOrShowFields();
        this.addEventListener();
    }

}


/**
 * Contact Form 7
 * @type {{init: WpSmsContactForm7.init, hideOrShowFields: WpSmsContactForm7.hideOrShowFields, setFields: WpSmsContactForm7.setFields, addEventListener: WpSmsContactForm7.addEventListener}}
 */
let WpSmsContactForm7 = {

    /**
     * Initialize Functions
     */
    init: function () {
        this.setFields()
        this.hideOrShowFields()
        this.addEventListener()
    },

    /**
     * Initialize jQuery Selectors
     */
    setFields: function () {
        this.fields = {
            recipient: {
                element: jQuery('#wpcf7-sms-recipient')
            },
            recipient_numbers: {
                element: jQuery('#wp-sms-recipient-numbers')
            },
            recipient_groups: {
                element: jQuery('#wp-sms-recipient-groups')
            },
            message_body: {
                element: jQuery('#wp-sms-cf7-message-body')
            }
        }
    },

    /**
     *  Show or Hide content by changing the Select HTMl tag
     */
    hideOrShowFields: function () {
        if (this.fields.recipient.element.val() === 'number') {
            this.fields.recipient_numbers.element.show()
            this.fields.recipient_groups.element.hide()
            this.fields.message_body.element.show()

        } else {
            this.fields.recipient_numbers.element.hide()
            this.fields.recipient_groups.element.show()
            this.fields.message_body.element.show()

        }
    },

    addEventListener: function () {
        this.fields.recipient.element.on('change', function () {
            this.hideOrShowFields();
        }.bind(this));
    },

}

/**
 * Meta Box
 * @type {{init: WpSmsMetaBox.init, hideOrShowFields: WpSmsMetaBox.hideOrShowFields, setFields: WpSmsMetaBox.setFields, addEventListener: WpSmsMetaBox.addEventListener}}
 */
let WpSmsMetaBox = {

    /**
     * Initialize Functions
     */
    init: function () {
        this.setFields()
        this.hideOrShowFields()
        this.addEventListener()
        this.insertShortcode()
    },

    /**
     * Initialize jQuery Selectors
     */
    setFields: function () {
        this.fields = {
            recipient: {
                element: jQuery('#wps-send-to'),

                subscriber: {
                    element: jQuery('#wpsms-select-subscriber-group'),
                },

                numbers: {
                    element: jQuery('#wpsms-select-numbers'),
                },

                users: {
                    element: jQuery('#wpsms-select-users'),
                }
            },
            message_body: {
                element: jQuery('#wpsms-custom-text'),
            },
            short_codes: {
                element: jQuery('#wpsms-short-codes'),
            }
        }
    },

    /**
     *  Show or Hide content by changing the Select HTMl tag
     */
    hideOrShowFields: function () {
        if (this.fields.recipient.element.val() === 'subscriber') {
            this.fields.recipient.subscriber.element.show()
            this.fields.recipient.numbers.element.hide()
            this.fields.recipient.users.element.hide()
            this.fields.message_body.element.show()

        } else if (this.fields.recipient.element.val() === 'numbers') {
            this.fields.recipient.subscriber.element.hide()
            this.fields.recipient.numbers.element.show()
            this.fields.recipient.users.element.hide()
            this.fields.message_body.element.show()

        } else if (this.fields.recipient.element.val() === 'users') {
            this.fields.recipient.subscriber.element.hide()
            this.fields.recipient.numbers.element.hide()
            this.fields.recipient.users.element.show()
            this.fields.message_body.element.show()

        } else {
            this.fields.recipient.subscriber.element.hide()
            this.fields.recipient.numbers.element.hide()
            this.fields.recipient.users.element.hide()
            this.fields.message_body.element.hide()
        }
    },

    addEventListener: function () {
        this.fields.recipient.element.on('change', function () {
            this.hideOrShowFields();
        }.bind(this));
    },

    insertShortcode: function () {
        this.fields.short_codes.element.find("code").each(function (index) {
            jQuery(this).on('click', function () {
                var shortCodeValue = ' ' + jQuery(this).text() + ' ';
                jQuery('#wpsms-text-template').val(function (i, text) {
                    const cursorPosition = jQuery(this)[0].selectionStart;
                    return text.substring(0, cursorPosition) + shortCodeValue + text.substring(cursorPosition);
                })
            })
        })
    },
}


class ShowIfEnabled {
    constructor() {
        this.initialize();
    }

    initialize() {
        const elements = document.querySelectorAll('[class^="js-wpsms-show_if_"]');
        // Loop through each element
        elements.forEach(element => {
            const classListString = Array.from(element.classList).join(' ');

            if (classListString.includes('_enabled')) {
                const id = this.extractId(element);
                const checkbox = document.querySelector(`#wpsms_settings\\[${id}\\]`);
                if (checkbox && checkbox.checked) {
                    element.style.display = 'table-row';
                } else {
                    element.style.display = 'none';
                }
                if (checkbox) {
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked) {
                            element.style.display = 'table-row';
                        } else {
                            element.style.display = 'none';
                        }
                    });
                }
            }
            if (classListString.includes('_equal_')) {
                const {id, value} = this.extractIdAndValue(element);
                if (id && value) {
                    const item = document.querySelector(`#wpsms_settings\\[${id}\\]`);
                    if (item && item.type === 'checkbox') {
                        if (item.checked == value) {
                            element.style.display = 'table-row';
                        } else {
                            element.style.display = 'none';
                        }
                        item.addEventListener('change', () => {
                            if (!item.checked) {
                                element.style.display = 'table-row';
                            } else {
                                element.style.display = 'none';
                            }
                        });
                    }
                    if (item && item.type === 'select') {
                        let itemValue = item.val();
                        switch (itemValue) {

                        }

                    }
                }

            }
        });
    }

    extractId(element) {
        // Extract the ID from the class name
        const classes = element.className.split(' ');
        for (const className of classes) {
            if (className.startsWith('js-wpsms-show_if_') && className.endsWith('_enabled')) {
                return className.replace('js-wpsms-show_if_', '').replace('_enabled', '');
            }
        }
        return null;
    }

    extractIdAndValue(element) {
        const classes = element.className.split(' ');
        let id, value;
        for (const className of classes) {
            if (className.startsWith('js-wpsms-show_if_')) {
                const parts = className.split('_');
                const indexOfEqual = parts.indexOf('equal');
                if (indexOfEqual !== -1 && indexOfEqual > 2 && indexOfEqual < parts.length - 1) {
                    id = parts.slice(2, indexOfEqual).join('_');
                    value = parts.slice(indexOfEqual + 1).join('_');
                    break;
                }
            }
        }
        return {id, value};
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new ShowIfEnabled();
});