//Show the Modal ThickBox For Each Edit link
function wp_sms_edit_group(group_id, group_name) {
    tb_show(WP_Sms_Admin_Object.tag.group, WP_Sms_Admin_Object.ajaxUrls.group + '&group_id=' + group_id + '&group_name=' + encodeURIComponent(group_name) + '&width=400&height=125');
}
// Assign the function to the window object
window.wp_sms_edit_group = wp_sms_edit_group;