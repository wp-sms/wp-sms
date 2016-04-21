<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#wp_subscribes_send_sms').click(function() {
			jQuery('#wp_subscribes_stats').fadeToggle();
		});
		
		jQuery('#wps_mnt_status').click(function() {
			jQuery('.wps_mnt_rules').fadeToggle();
		});
	});
</script>

<?php do_action('wp_sms_settings_page'); ?>

<div class="wrap">
	<?php include( dirname( __FILE__ ) . '/tabs.php' ); ?>
	<table class="form-table">
		<form method="post" action="options.php" name="form">
			<?php wp_nonce_field('update-options');?>
			<tr>
				<th><?php _e('Status', 'wp-sms'); ?></th>
				<td>
					<input type="checkbox" name="wp_subscribes_status" id="wp_subscribes_status" <?php echo get_option('wp_subscribes_status') ==true? 'checked="checked"':'';?>/>
					<label for="wp_subscribes_status"><?php _e('Active', 'wp-sms'); ?></label>
				</td>
			</tr>

			<tr>
				<th><?php _e('Verified subscribe with the activation code', 'wp-sms'); ?></th>
				<td>
					<?php if(get_option('wp_webservice')) { ?>
					<input type="checkbox" name="wp_subscribes_activation" id="wp_subscribes_activation" <?php echo get_option('wp_subscribes_activation') ==true? 'checked="checked"':'';?>/>
					<label for="wp_subscribes_activation"><?php _e('Active', 'wp-sms'); ?></label>
					<?php } else { ?>
					<input type="checkbox" disabled="disabled"/>
					<label for="wp_subscribes_activation"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description"><?php _e('First you should have select a web service and activate it!', 'wp-sms'); ?></p>
					<?php } ?>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Send Welcome-SMS', 'wp-sms'); ?></th>
				<td>
					<input type="checkbox" name="wp_subscribes_send_sms" id="wp_subscribes_send_sms" <?php echo get_option('wp_subscribes_send_sms') ==true? 'checked="checked"':'';?>/>
					<label for="wp_subscribes_send_sms"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description"><?php _e('Send a sms to subscriber when register.', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<?php if( get_option('wp_subscribes_send_sms') ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
			<tr valign="top"<?php echo $hidden;?> id='wp_subscribes_stats'>
				<td scope="row">
					<label for="wpsms-text-template"><?php _e('Text template', 'wp-sms'); ?>:</label>
				</th>
				
				<td>
					<textarea id="wpsms-text-template" cols="50" rows="7" name="wp_subscribes_text_send"><?php echo get_option('wp_subscribes_text_send'); ?></textarea>
					<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
					<p class="description data">
						<?php _e('Input data:', 'wp-sms'); ?>
						<?php _e('Subscribe name', 'wp-sms'); ?>: <code>%subscribe_name%</code>
						<?php _e('Subscribe mobile', 'wp-sms'); ?>: <code>%subscribe_mobile%</code>
					</p>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Calling jQuery in Wordpress', 'wp-sms'); ?></th>
				<td>
					<input type="checkbox" name="wp_call_jquery" id="wp_call_jquery" <?php echo get_option('wp_call_jquery') ==true? 'checked="checked"':'';?>/>
					<label for="wp_call_jquery"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description">(<?php _e('Enable this option with JQuery is called in the theme', 'wp-sms'); ?>)</p>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Mobile Number terms', 'wp-sms'); ?></th>
				<td>
					<input type="checkbox" name="wps_mnt_status" id="wps_mnt_status" <?php echo get_option('wps_mnt_status') ==true? 'checked="checked"':'';?>/>
					<label for="wps_mnt_status"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description">(<?php _e('Define rules for mobile number input field.', 'wp-sms'); ?>)</p>
				</td>
			</tr>
			
			<?php if( get_option('wps_mnt_status') ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
			<tr valign="top"<?php echo $hidden;?> class="wps_mnt_rules">
				<td scope="row">
					<label for="wpsms-text-template"><?php _e('Placeholder field', 'wp-sms'); ?>:</label>
				</th>
				
				<td>
					<input type="text" value="<?php echo get_option('wps_mnt_place_holder'); ?>" name="wps_mnt_place_holder">
					<p class="description"><?php _e('Define text for mobile number field.', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<tr valign="top"<?php echo $hidden;?> class="wps_mnt_rules">
				<td scope="row">
					<label for="wpsms-text-template"><?php _e('Max Mobile number', 'wp-sms'); ?>:</label>
				</th>
				
				<td>
					<input type="text" value="<?php echo get_option('wps_mnt_max'); ?>" name="wps_mnt_max" dir="ltr">
					<p class="description"><?php _e('Define maximum number mobile number.', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<tr valign="top"<?php echo $hidden;?> class="wps_mnt_rules">
				<td scope="row">
					<label for="wpsms-text-template"><?php _e('Min Mobile number', 'wp-sms'); ?>:</label>
				</th>
				
				<td>
					<input type="text" value="<?php echo get_option('wps_mnt_min'); ?>" name="wps_mnt_min" dir="ltr">
					<p class="description"><?php _e('Define minimum number mobile number.', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<tr valign="top">
				<th><?php _e('Auto subscribe new user', 'wp-sms'); ?>:</th>
				
				<td>
					<input type="checkbox" name="wps_add_user_to_newsletter" id="wps-add-user-to-newsletter" <?php echo get_option('wps_add_user_to_newsletter') ==true? 'checked="checked"':'';?>/>
					<label for="wps-add-user-to-newsletter"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description"><?php _e('Should be enable mobile field number in register form', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<tr>
				<td>
					<p class="submit">
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="page_options" value="wp_subscribes_status,wp_subscribes_activation,wp_subscribes_send_sms,wp_subscribes_text_send,wp_subscribes_send,wp_call_jquery,wps_mnt_status,wps_mnt_place_holder,wps_mnt_max,wps_mnt_min,wps_add_user_to_newsletter" />
						<input type="submit" class="button-primary" name="Submit" value="<?php _e('Update', 'wp-sms'); ?>" />
					</p>
				</td>
			</tr>
		</form>	
	</table>
</div>