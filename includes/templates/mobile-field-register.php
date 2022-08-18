<p>
    <label for="mobile"><?php _e('Mobile Number', 'wp-sms') ?><br/>
        <?php wp_sms_render_mobile_field(['class' => ['input'], 'name' => 'mobile', 'value' => \WP_SMS\Helper::sanitizeMobileNumber($mobile)]); ?>
    </label>
</p>