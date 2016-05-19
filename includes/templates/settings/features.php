<?php do_action('wp_sms_settings_page'); ?>

<div class="wrap">
	<?php include( dirname( __FILE__ ) . '/tabs.php' ); ?>
	<table class="form-table">
		<form method="post" action="options.php" name="form">
			<?php wp_nonce_field('update-options');?>
			
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