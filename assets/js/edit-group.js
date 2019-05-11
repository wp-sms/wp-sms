//Show the Modal ThickBox For Each Edit link
function wp_sms_edit_group(group_id, group_name) {
    tb_show(wp_sms_edit_group_ajax_vars.tb_show_tag, wp_sms_edit_group_ajax_vars.tb_show_url + '&group_id=' + group_id + '&group_name=' + encodeURIComponent(group_name) + '&width=400&height=125');
}