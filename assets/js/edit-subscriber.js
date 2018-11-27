//Show the Modal ThickBox For Each Edit link
function wp_sms_edit_subscriber(subscriber_id) {
    tb_show(wp_sms_edit_subscribe_ajax_vars.tb_show_tag, wp_sms_edit_subscribe_ajax_vars.tb_show_url + '&subscriber_id=' + subscriber_id + '&width=400&height=310');
}