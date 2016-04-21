<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<tr><th scope="row"><strong><?php _e('Contact Form 7', 'wp-sms'); ?></strong></th><td><hr></td></tr>
<tr>
	<th><?php _e('SMS meta box', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wpsms_add_wpcf7]" id="wpsms-add-wpcf7" <?php echo $wps_options['wpsms_add_wpcf7'] ==true? 'checked="checked"':'';?>/>
		<label for="wpsms-add-wpcf7"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Added Wordpress SMS meta box to Contact form 7 plugin when enable this option.', 'wp-sms'); ?></p>
	</td>
</tr>