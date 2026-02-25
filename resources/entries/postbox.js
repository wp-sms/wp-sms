/**
 * Post metabox: shortcode insertion and conditional field visibility.
 * Restored from admin.js after the React dashboard refactor split out the
 * legacy admin pages — the post/page metabox is NOT part of the React UI.
 */

/**
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

jQuery(document).ready(function () {
    if (jQuery('#subscribe-meta-box').length) {
        WpSmsMetaBox.init();
    }
});

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
                                        displayed = true;
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
    new ShowIfEnabled();
});
