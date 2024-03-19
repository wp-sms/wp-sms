jQuery(document).ready(function ($) {


    if (jQuery('#subscribe-meta-box').length) {
        WpSmsMetaBox.init();
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

            for (let i = 0; i < classListArray.length; i++) {
                const className = classListArray[i];
                if (className.includes('_enabled') || className.includes('_disabled')) {
                    const id = this.extractId(element);
                    const checkbox = document.querySelector(`#wpsms_settings\\[${id}\\]`);

                    if (checkbox) {
                        if (checkbox && className.includes('_enabled')) {
                            const toggleElement = () => {
                                if (checkbox.checked) {
                                    this.toggleDisplay(element);
                                } else {
                                    element.style.display = 'none';
                                }
                            }
                            toggleElement()
                            checkbox.addEventListener('change', toggleElement);
                        }
                        if (checkbox && className.includes('_disabled')) {
                            const toggleElement = () => {
                                if (checkbox.checked) {
                                    element.style.display = 'none';
                                } else {
                                    this.toggleDisplay(element);
                                }
                            }
                            toggleElement()
                            checkbox.addEventListener('change', toggleElement);
                        }
                    }

                }
                if (className.includes('_equal_')) {
                    const {id, value} = this.extractIdAndValue(className);
                    if (id && value) {
                        const item = document.querySelector(`#wpsms_settings\\[${id}\\], #wps_pp_settings\\[${id}\\], #${id}`);
                        item.addEventListener('change', () => {
                            this.initialize();
                        });
                        if (item && item.type === 'select-one') {
                            if (item.value == value) {
                                this.toggleDisplay(element);
                                break;
                            } else {
                                element.style.display = 'none';
                            }
                        }
                    }
                }
            }
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
    new ShowIfEnabled();
});
