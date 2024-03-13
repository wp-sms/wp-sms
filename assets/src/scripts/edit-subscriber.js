//Show the Modal ThickBox For Each Edit link
function wp_sms_edit_subscriber(subscriber_id) {
    if (typeof subscriber_id === 'number' && Number.isInteger(subscriber_id)) {
        tb_show(WP_Sms_Admin_Object.tag.subscribe, WP_Sms_Admin_Object.ajaxUrls.subscribe + '&subscriber_id=' + subscriber_id + '&width=400&height=310');
    }
}

// Assign the function to the window object
window.wp_sms_edit_subscriber = wp_sms_edit_subscriber;

