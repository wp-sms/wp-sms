jQuery(document).ready(function ($) {

    ultimateMember.init();

    // Set Chosen
    $(".chosen-select").chosen({width: "25em"});

    // Auto submit the gateways form, after changing chosen value
    $("#wpsms_settings\\[gateway_name\\]").on('change', function () {
        $( 'input[name="submit"]' ).click();
    });

    // Check about page
    if ($('.wp-sms-welcome').length) {
        $('.nav-tab-wrapper a').click(function () {
            var tab_id = $(this).attr('data-tab');

            if (tab_id == 'link') {
                return true;
            }

            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $('.tab-content').removeClass('current');

            $("[data-tab=" + tab_id + "]").addClass('nav-tab-active');
            $("[data-content=" + tab_id + "]").addClass('current');

            return false;
        });
    }

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
    
    getFields : function(){
        this.mobielNumberField = jQuery('#wps_pp_settings\\[um_field\\]');
        this.syncOldMembersField = jQuery('#wps_pp_settings\\[um_sync_previous_members\\]');
    },

    isUmOptionEnables : function(){
        if( this.mobielNumberField.is(':checked') ){
            this.syncOldMembersField.closest('tr').hide();
            return true;
        }
    },

    hideOrShow : function(){
        if( this.mobielNumberField.is(':checked')){
            this.syncOldMembersField.closest('tr').show();
        }else{
            this.syncOldMembersField.closest('tr').hide();
        }
    },

    addEventListener : function(){
        this.mobielNumberField.change(function(){
            this.hideOrShow();
        }.bind(this));
    },

    init : function(){

        this.getFields();
        //Case1. ultimate member sync optionis already enabled
        if(this.isUmOptionEnables())
            return;
        //Case2. ultimate member sync option is not already enabled
        this.hideOrShow();
        this.addEventListener();
    }

}