<script type="text/javascript">
	jQuery(document).ready(function(){
		if(jQuery('#wps-send-subscribe').val() == 'yes') {
			jQuery('#wpsms-select-subscriber-group').show();
		}
		
		jQuery("#wps-send-subscribe").change(function() {
			if(this.value == 'yes') {
				jQuery('#wpsms-select-subscriber-group').show();
			} else {
				jQuery('#wpsms-select-subscriber-group').hide();
			}
			
		});
	})
</script>

<p>
	<label>
		<?php _e('Send this post to subscribers?', 'wp-sms'); ?><br/>
		<select name="wps_send_subscribe" id="wps-send-subscribe">
			<option value="0" selected><?php _e('Please select', 'wp-sms'); ?></option>
			<option value="yes"><?php _e('Yes'); ?></option>
			<option value="no"><?php _e('No'); ?></option>
		</select>
	</label>
</p>

<p id="wpsms-select-subscriber-group">
	<label>
		<?php _e('Select the group', 'wp-sms'); ?><br/>
		<select name="wps_subscribe_group">
			<option value="all"><?php echo sprintf(__('All (%s subscribers active)', 'wp-sms'), $username_active); ?></option>
			<?php foreach($get_group_result as $items): ?><option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option><?php endforeach; ?>
		</select>
	</label>
</p>