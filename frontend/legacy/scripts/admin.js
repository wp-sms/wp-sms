jQuery(document).ready(function ($) {

    jQuery( 'body' ).on( 'thickbox:removed', function() {
        jQuery('.iti__country-container').trigger('click');
    });


    if (jQuery('#subscribe-meta-box').length) {
        WpSmsMetaBox.init();
    }

    const tablenavPages = document.querySelector('.wpsms-wrap__main .tablenav-pages');
    if (tablenavPages && tablenavPages.classList.contains('no-pages')) {
        // Remove margin and padding
        tablenavPages.parentElement.style.margin = '0';
        tablenavPages.parentElement.style.padding = '0';
        tablenavPages.parentElement.style.height = '0';
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
 * Meta Box
 * @type {{init: WpSmsMetaBox.init, setFields: WpSmsMetaBox.setFields}}
 */
let WpSmsMetaBox = {

    /**
     * Initialize Functions
     */
    init: function () {
        this.setFields()
        this.insertShortcode()
    },

    /**
     * Initialize jQuery Selectors
     */
    setFields: function () {
        this.fields = {
            short_codes: {
                element: jQuery('#wpsms-short-codes'),
            }
        }
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
        elements.forEach(element => {
            const classListArray = [...element.className.split(' ')];

            const toggleElement = () => {
                let displayed = false;
                classListArray.forEach(className => {
                    if (className.includes('_enabled') || className.includes('_disabled')) {
                        const id = this.extractId(element);
                        const checkbox = document.querySelector(`#wpsms_settings\\[${id}\\]`);
                        if (checkbox) {
                            if (checkbox.checked && className.includes('_enabled')) {
                                this.toggleDisplay(element);

                            } else if (!checkbox.checked && className.includes('_disabled')) {
                                this.toggleDisplay(element);
                            } else {
                                element.style.display = 'none';
                            }
                        }
                    } else if (className.includes('_equal_')) {
                        const {id, value} = this.extractIdAndValue(className);
                        if (id && value) {
                            const item = document.querySelector(`#wpsms_settings\\[${id}\\], #wps_pp_settings\\[${id}\\], #${id}`);
                            if (item && item.type === 'select-one') {
                                if (item.value == value) {
                                    if (!displayed) {
                                        this.toggleDisplay(element);
                                        displayed = true
                                    }

                                }
                                if (item.value != value) {
                                    if (!displayed) {
                                        element.style.display = 'none';
                                    }
                                }
                            }
                        }
                    }
                });
            };

            toggleElement();

            classListArray.forEach(className => {
                if (className.includes('_enabled') || className.includes('_disabled')) {
                    const id = this.extractId(element);
                    const checkbox = document.querySelector(`#wpsms_settings\\[${id}\\]`);
                    if (checkbox) {
                        checkbox.addEventListener('change', toggleElement);
                    }
                } else if (className.includes('_equal_')) {
                    const {id} = this.extractIdAndValue(className);
                    if (id) {
                        const item = document.querySelector(`#wpsms_settings\\[${id}\\], #wps_pp_settings\\[${id}\\], #${id}`);
                        if (item && item.type === 'select-one') {
                            item.addEventListener('change', toggleElement);
                        }
                    }
                }
            });
        });
    }


    toggleDisplay(element) {
        const displayType = element.tagName.toLowerCase() === 'tr' ? 'table-row' : 'table-cell';
        element.style.display = displayType;
    }

    extractId(element) {
        const classes = element.className.split(' ');
        for (const className of classes) {
            if (className.startsWith('js-wpsms-show_if_')) {
                const id = className.replace('js-wpsms-show_if_', '').replace('_enabled', '').replace('_disabled', '');
                if (id) {
                    return id;
                }
            }
        }
        return null;
    }

    extractIdAndValue(className) {
        let id, value;
        if (className.startsWith('js-wpsms-show_if_')) {
            const parts = className.split('_');
            const indexOfEqual = parts.indexOf('equal');
            if (indexOfEqual !== -1 && indexOfEqual > 2 && indexOfEqual < parts.length - 1) {
                id = parts.slice(2, indexOfEqual).join('_');
                value = parts.slice(indexOfEqual + 1).join('_');
            }
        }

        return {id, value};
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const notices = document.querySelectorAll('.notice');
    const promotionModal = document.querySelector('.promotion-modal');
     if (notices.length > 0  && (document.body.classList.contains('post-type-wpsms-command') || document.body.classList.contains('post-type-sms-campaign') || document.body.classList.contains('sms_page_wp-sms') || document.body.classList.contains('sms-woo-pro_page_wp-sms-woo-pro-cart-abandonment') || document.body.classList.contains('sms-woo-pro_page_wp-sms-woo-pro-settings')) ) {
        notices.forEach(notice => {
            notice.classList.remove('inline');
            if (promotionModal) {
                notice.style.display = 'none'
            }
        })
    }
    new ShowIfEnabled();
});

/**
 * FeedbackBird position
 * */
function moveFeedbackBird() {
    let windowWidth = window.outerWidth || document.documentElement.clientWidth;
    const feedbackBird = document.getElementById('feedback-bird-app');
    const feedbackBirdTitle = document.querySelector('.c-fbb-widget__header__title');
    const license = document.querySelector('.wpsms-menu-content .wpsms-license');
    const support = document.querySelector('.wpsms-header-items-side');
    if (feedbackBird && (document.body.classList.contains('post-type-wpsms-command') || document.body.classList.contains('sms_page_wp-sms') || document.body.classList.contains('sms-woo-pro_page_wp-sms-woo-pro-cart-abandonment') || document.body.classList.contains('sms-woo-pro_page_wp-sms-woo-pro-settings'))) {
        if (windowWidth <= 1030) {
            const cutDiv = feedbackBird.parentNode.removeChild(feedbackBird);
            license.parentNode.insertBefore(cutDiv, license);
        } else {
            const cutDiv = feedbackBird.parentNode.removeChild(feedbackBird);
            support.appendChild(cutDiv);
        }
        feedbackBird.style.display = 'block';
        feedbackBird.setAttribute('title',feedbackBirdTitle.innerHTML);
    }
}

window.onload = moveFeedbackBird;
window.addEventListener('resize', moveFeedbackBird);
