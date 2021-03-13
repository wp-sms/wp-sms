<?php
if (wp_sms_get_option('international_mobile')) {
    $wp_sms_input_mobile = " wp-sms-input-mobile";
} else {
    $wp_sms_input_mobile = "";
}
?>
<table class="form-table">
    <tr>
        <th><label for="mobile"><?php _e('Mobile', 'wp-sms'); ?></label></th>
        <td>
            <input type="text" class="regular-text<?php echo $wp_sms_input_mobile ?>" name="mobile" value="" id="mobile"/>
            <span class="description"><?php _e('User mobile number.', 'wp-sms'); ?></span>
        </td>
    </tr>
</table>