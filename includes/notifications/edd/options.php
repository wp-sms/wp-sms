<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery('#wpsms-edd-no-stats').click(function() {
		jQuery('#wpsms-edd-no').fadeToggle();
	});
});
</script>

<tr><th scope="row"><strong><?php _e('Easy Digital Downloads', 'wp-sms'); ?></strong></th><td><hr></td></tr>

<tr>
	<th><?php _e('New order', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wpsms_edd_no_stats]" id="wpsms-edd-no-stats" <?php echo $wps_options['wpsms_edd_no_stats'] ==true? 'checked="checked"':'';?>/>
		<label for="wpsms-edd-no-stats"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to you When get new order.', 'wp-sms'); ?></p>
	</td>
</tr>

<?php if( $wps_options['wpsms_edd_no_stats'] ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
<tr valign="top"<?php echo $hidden;?> id="wpsms-edd-no">
	<td scope="row">
		<label for="wpsms-edd-no-tt"><?php _e('Text template', 'wp-sms'); ?>:</label>
	</th>
	
	<td>
		<textarea id="wpsms-edd-no-tt" cols="50" rows="7" name="wpsms[wpsms_edd_no_tt]"><?php echo $wps_options['wpsms_edd_no_tt']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
	</td>
</tr>