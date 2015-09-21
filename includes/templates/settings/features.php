<?php do_action('wp_sms_settings_page'); ?>

<div class="wrap">
	<?php include( dirname( __FILE__ ) . '/tabs.php' ); ?>
	<table class="form-table">
		<form method="post" action="options.php" name="form">
			<?php wp_nonce_field('update-options');?>
			<tr><th scope="row"><strong><?php _e('Wordpress', 'wp-sms'); ?></strong></th><td><hr></td></tr>
			<tr>
				<th><?php _e('Suggested post by SMS', 'wp-sms'); ?></th>
				<td>
					<input type="checkbox" name="wp_suggestion_status" id="wp_suggestion_status" <?php echo get_option('wp_suggestion_status') ==true? 'checked="checked"':'';?>/>
					<label for="wp_suggestion_status"><?php _e('Active', 'wp-sms'); ?></label>
				</td>
			</tr>
			
			<?php if( get_option('wp_suggestion_status') ) {?>
			<tr valign="top">
				<td scope="row">
					<label><?php _e('Text template', 'wp-sms'); ?>:</label>
				</th>
				
				<td>
					<textarea cols="50" rows="7" name="wpsms_suggestion_tt"><?php echo get_option('wpsms_suggestion_tt'); ?></textarea>
					<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
					<p class="description data">
						<?php _e('Input data:', 'wp-sms'); ?>
						<?php _e('Post title', 'wp-sms'); ?>: <code>%post_title%</code>
						<?php _e('SMS sender', 'wp-sms'); ?>: <code>%sms_sender%</code>
						<?php _e('SMS receiver', 'wp-sms'); ?>: <code>%sms_receiver%</code>
						<?php _e('Post short link', 'wp-sms'); ?>: <code>%post_shortlink%</code>
					</p>
				</td>
			</tr>
			<?php } ?>
			
			<tr>
				<th><?php _e('Add Mobile number field', 'wp-sms'); ?></th>
				<td>
					<input type="checkbox" name="wps_add_mobile_field" id="wps_add_mobile_field" <?php echo get_option('wps_add_mobile_field') ==true? 'checked="checked"':'';?>/>
					<label for="wps_add_mobile_field"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description"><?php _e('Add Mobile number to user profile and register form.', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<tr>
				<td>
					<p class="submit">
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="page_options" value="wp_suggestion_status,wpsms_suggestion_tt,wps_add_mobile_field" />
						<input type="submit" class="button-primary" name="Submit" value="<?php _e('Update', 'wp-sms'); ?>" />
					</p>
				</td>
			</tr>
		</form>	
	</table>
</div>