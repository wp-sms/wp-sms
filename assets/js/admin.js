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
    WpSmsSelect2.select2(WpSmsSelect2Options);

    // Auto submit the gateways form, after changing value
    $("#wpsms_settings\\[gateway_name\\]").on('change', function () {
        $('input[name="submit"]').click();
    });

    if ($('.repeater').length) {
        $('.repeater').repeater({
            initEmpty: false,
            show: function () {
                $(this).slideDown();
            },
            hide: function (deleteElement) {
                if (confirm('Are you sure you want to delete this item?')) {
                    $(this).slideUp(deleteElement);
                }
            },
            isFirstItemUndeletable: true
        });
    }
});


/**
 * General
 * @type {{init: WpSMSGeneral.init, alreadyEnabled: ((function(): (boolean|undefined))|*), getFields: WpSMSGeneral.getFields}}
 */
let WpSMSGeneral = {

    getFields: function () {
        this.fields = {
            internatioanlMode: {
                element: jQuery('#wpsms_settings\\[international_mobile\\]'),
            },
            mobileMinimumChar: {
                element: jQuery('#wpsms_settings\\[mobile_terms_minimum\\]'),
            },
            mobileMaximumChar: {
                element: jQuery('#wpsms_settings\\[mobile_terms_maximum\\]'),
            },
            onlyCountries: {
                element: jQuery('#wpsms_settings\\[international_mobile_only_countries\\]'),
            },
            preferredCountries: {
                element: jQuery('#wpsms_settings\\[international_mobile_preferred_countries\\]'),
            },
            newsletterFormGroups: {
                element: jQuery("#wpsms_settings\\[newsletter_form_groups\\]"),
            },
            newsletterFormMultipleSelect: {
                element: jQuery("#wpsms_settings\\[newsletter_form_multiple_select\\]"),
            },
            newsletterFormSpecifiedGroups: {
                element: jQuery("#wpsms_settings\\[newsletter_form_specified_groups\\]"),
            },
            newsletterFormDefaultGroup: {
                element: jQuery("#wpsms_settings\\[newsletter_form_default_group\\]"),
            },
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
            },
            onlyLocalNumbers: {
                element: jQuery('#wpsms_settings\\[send_only_local_numbers\\]'),
            },
            onlyLocalNumbersCountries: {
                element: jQuery('#wpsms_settings\\[only_local_numbers_countries\\]'),
            },
        }
    },

    hideOrShowFields: function () {
        if (this.fields.internatioanlMode.element.is(':checked')) {
            this.fields.onlyCountries.element.closest('tr').show()
            this.fields.preferredCountries.element.closest('tr').show()
            this.fields.mobileMinimumChar.element.closest('tr').hide()
            this.fields.mobileMaximumChar.element.closest('tr').hide()
        } else {
            this.fields.onlyCountries.element.closest('tr').hide()
            this.fields.preferredCountries.element.closest('tr').hide()
            this.fields.mobileMinimumChar.element.closest('tr').show()
            this.fields.mobileMaximumChar.element.closest('tr').show()
        }

        if (this.fields.newsletterFormGroups.element.is(":checked")) {
            this.fields.newsletterFormMultipleSelect.element.closest("tr").show();
            this.fields.newsletterFormSpecifiedGroups.element.closest("tr").show();
            this.fields.newsletterFormDefaultGroup.element.closest("tr").show();
        } else {
            this.fields.newsletterFormMultipleSelect.element.closest("tr").hide();
            this.fields.newsletterFormSpecifiedGroups.element.closest("tr").hide();
            this.fields.newsletterFormDefaultGroup.element.closest("tr").hide();
        }

        if (this.fields.onlyLocalNumbers.element.is(":checked")) {
            this.fields.onlyLocalNumbersCountries.element.closest("tr").show();
        } else {
            this.fields.onlyLocalNumbersCountries.element.closest("tr").hide();
        }
    },

    addEventListener: function () {
        ["internatioanlMode", "newsletterFormGroups", "onlyLocalNumbers"].forEach(field => {
            this.fields[field].element.on("change", () => {
                this.hideOrShowFields();
            });
        });

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
        this.hideOrShowFields();
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