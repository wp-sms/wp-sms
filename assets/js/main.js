jQuery(document).ready(function () {
    webhookCopyButton.init()
    resetTokenButton.init()
    responseMetabox.init()
})

var webhookCopyButton = {

    init: function () {
        this.setFields()
        this.addEventListener()
    },

    setFields: function () {
        this.button = jQuery('#two-way-copy-webhook-btn')
        this.webhookUrlField = jQuery('#two-way-webhook-url-field span')
    },

    addEventListener: function () {
        this.button.on('click', function () {
            this.copyUrl()
        }.bind(this))
    },

    copyUrl: function () {
        navigator.clipboard.writeText(this.webhookUrlField.text());
        this.button.addClass('green');
        this.button.text('Copied');
    }
}

var resetTokenButton = {

    init: function () {
        this.setFields()
        this.addEventListener()
    },

    isSure: false,

    setFields: function () {
        this.button = jQuery('#two-way-reset-token-btn')
    },

    makeSure: function () {
        this.button.addClass('red')
        this.button.text('Are you sure?')
        this.isSure = true
    },

    addEventListener: function () {
        this.button.on('click', function () {
            if (this.isSure) {
                this.resetToken()
            }
            else this.makeSure()

        }.bind(this))
    },

    resetToken: function () {
        jQuery.ajax({
            url: WPSmsTwoWayAdmin.routes.resetToken.href,
            data: {
                _wpnonce: WPSmsTwoWayAdmin.wp_nonce,
                [WPSmsTwoWayAdmin.nonce_field_name]: WPSmsTwoWayAdmin.routes.resetToken.nonce,
            },
            success: function (data, textStatus) {
                window.location.reload()
            }
        });
    }
}

var responseMetabox = {
    init: function () {
        this.setElements()
        this.addEventListener()
        this.enableDisableFields()
    },

    setElements: function () {
        this.rows = {
            success: {
                checkBox: jQuery('#wpsms-tw-success-response-checkbox'),
                textArea: jQuery('#wpsms-tw-success-response-textarea')
            },
            failure: {
                checkBox: jQuery('#wpsms-tw-failure-response-checkbox'),
                textArea: jQuery('#wpsms-tw-failure-response-textarea')
            }
        }
    },

    addEventListener: function () {
        for (const key in this.rows) {
            if (Object.hasOwnProperty.call(this.rows, key)) {
                const row = this.rows[key];
                row.checkBox.on('click', function () {
                    if (row.checkBox.is(":checked")) {
                        row.textArea.removeAttr('disabled')
                    } else {
                        row.textArea.attr('disabled', true)
                    }
                })
            }
        }
    },


    enableDisableFields: function () {
        for (const key in this.rows) {
            if (Object.hasOwnProperty.call(this.rows, key)) {
                const row = this.rows[key];
                if (row.checkBox.is(":checked")) {
                    row.textArea.removeAttr('disabled')
                } else {
                    row.textArea.attr('disabled', true)
                }
            }
        }
    }
}