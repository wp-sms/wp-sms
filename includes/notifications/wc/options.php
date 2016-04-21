<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery('#wpsms-wc-no-stats').click(function() {
		jQuery('#wpsms-wc-no').fadeToggle();
	});
});
</script>

<tr><th scope="row"><strong><?php _e('WooCommerce', 'wp-sms'); ?></strong></th><td><hr></td></tr>

<tr>
	<th><?php _e('New order', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wpsms_wc_no_stats]" id="wpsms-wc-no-stats" <?php echo $wps_options['wpsms_wc_no_stats'] ==true? 'checked="checked"':'';?>/>
		<label for="wpsms-wc-no-stats"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to you When get new order.', 'wp-sms'); ?></p>
	</td>
</tr>

<?php if( $wps_options['wpsms_wc_no_stats'] ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
<tr valign="top"<?php echo $hidden;?> id="wpsms-wc-no">
	<td scope="row">
		<label for="wpsms-wc-no-tt"><?php _e('Text template', 'wp-sms'); ?>:</label>
	</th>
	
	<td>
		<textarea id="wpsms-wc-no-tt" cols="50" rows="7" name="wpsms[wpsms_wc_no_tt]"><?php echo $wps_options['wpsms_wc_no_tt']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
		<p class="description data">
			<?php _e('Input data:', 'wp-sms'); ?>
			<?php _e('Order ID', 'wp-sms'); ?>: <code>%order_id%</code>
			<?php _e('Order Status', 'wp-sms'); ?>: <code>%status%</code>
			<?php _e('Order Name', 'wp-sms'); ?>: <code>%order_name%</code>
		</p>
	</td>
</tr>