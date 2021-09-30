jQuery(document).ready(function ($) {

    ultimateMember.init();

    // Set Chosen
    $('.js-wpsms-select2').select2();

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

let ultimateMember = {

    getFields: function () {
        this.fields = {
            mobielNumberField: {
                element: jQuery('#wps_pp_settings\\[um_field\\]'),
                active: false,
            },
            syncOldMembersField: {
                element: jQuery('#wps_pp_settings\\[um_sync_previous_members\\]'),
                active: true,
            },
            fieldSelector: {
                element: jQuery('#wps_pp_settings\\[um_sync_field_name\\]'),
                active: true,
            }
        }

    },

    alreadyEnabled: function () {
        if (this.fields.mobielNumberField.element.is(':checked')) {
            this.fields.syncOldMembersField.active = false;
            this.fields.syncOldMembersField.element.closest('tr').hide()
            return true;
        }
    },

    hideOrShowfields: function () {

        const condition = this.fields.mobielNumberField.element.is(':checked');

        if (condition) {
            for (const field in this.fields) {
                console.log(field);
                if (this.fields[field].active) this.fields[field].element.closest('tr').show();
            }
        } else {
            for (const field in this.fields) {
                if (this.fields[field].active) this.fields[field].element.closest('tr').hide();
            }
        }
    },

    addEventListener: function () {
        this.fields.mobielNumberField.element.change(function () {
            this.hideOrShowfields();
        }.bind(this));
    },

    init: function () {

        this.getFields();
        this.alreadyEnabled();
        this.hideOrShowfields();
        this.addEventListener();
    }

}