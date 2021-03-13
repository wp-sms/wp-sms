<?php
if (wp_sms_get_option('international_mobile')) {
    $wp_sms_input_mobile = " wp-sms-input-mobile";
} else {
    $wp_sms_input_mobile = "";
}
?>
<p>
    <label for="mobile"><?php _e('Your Mobile Number', 'wp-sms') ?><br/>
        <input type="text" name="mobile" id="mobile" class="input<?php echo $wp_sms_input_mobile ?>" value="<?php echo esc_attr(stripslashes($mobile)); ?>" size="25"/></label>
</p>