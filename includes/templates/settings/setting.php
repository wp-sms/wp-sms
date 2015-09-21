<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery(".chosen-select").chosen({disable_search_threshold: 10});
	});
</script>

<?php do_action('wp_sms_settings_page'); ?>

<div class="wrap">
	<?php include( dirname( __FILE__ ) . '/tabs.php' ); ?>
	<table class="form-table">
		<form method="post" action="options.php" name="form">
			<?php wp_nonce_field('update-options');?>
			<tr>
				<td><?php _e('Your Mobile Number', 'wp-sms'); ?>:</td>
				<td>
					<input type="text" dir="ltr" style="width: 200px;" name="wp_admin_mobile" value="<?php echo get_option('wp_admin_mobile'); ?>"/>
					<p class="description"><?php echo sprintf(__('Enter your mobile number for get sms from plugin. Your mobile should like <code>%s</code>', 'wp-sms'), $sms->validateNumber); ?></p>
				</td>
			</tr>
			
			<tr>
				<td><?php _e('Your mobile country code', 'wp-sms'); ?>:</td>
				<td>
					<input type="text" dir="ltr" style="width: 200px;" name="wp_sms_mcc" value="<?php echo get_option('wp_sms_mcc'); ?>"/>
					<p class="description"><?php _e('Enter your mobile country code.', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<?php if(get_option('wp_webservice')) { ?>
			<tr>
				<td><?php _e('Show Credit in Admin menu', 'wp-sms'); ?>:</td>
				<td>
					<input type="checkbox" name="wp_sms_cam" id="wp_sms_cam" <?php echo get_option('wp_sms_cam') ==true? 'checked="checked"':'';?>/>
					<label for="wp_sms_cam"><?php _e('Active', 'wp-sms'); ?></label>
					<p class="description"><?php _e('Show your credit account in admin menu.', 'wp-sms'); ?></p>
				</td>
			</tr>
			<?php } ?>
			
			<tr valign="top">
				<th scope="row" colspan="2"><h3><?php _e('Access Levels', 'wp-sms'); ?></h3></th>
			</tr>
			
			<tr>
				<td><?php _e('Send SMS Page', 'wp-sms'); ?>:</td>
				<td>
					<select name="wps_access_level" class="chosen-select<?php echo is_rtl() == true? " chosen-rtl":""; ?>" data-placeholder="<?php _e('Access Levels', 'wp-sms'); ?>" style="width:350px;">
						<option value=""></option>
						<option value="manage_options" <?php selected(get_option('wps_access_level'), 'manage_options'); ?>><?php _e('Administrator', 'wp-sms'); ?></option>
						<option value="read_private_pages" <?php selected(get_option('wps_access_level'), 'read_private_pages'); ?>><?php _e('Editor', 'wp-sms'); ?></option>
						<option value="delete_published_posts" <?php selected(get_option('wps_access_level'), 'delete_published_posts'); ?>><?php _e('Author', 'wp-sms'); ?></option>
						<option value="delete_posts" <?php selected(get_option('wps_access_level'), 'delete_posts'); ?>><?php _e('Contributor', 'wp-sms'); ?></option>
						<option value="read" <?php selected(get_option('wps_access_level'), 'read'); ?>><?php _e('Subscriber', 'wp-sms'); ?></option>
					</select>
					<p class="description"><?php _e('Required user level to view Send SMS Page', 'wp-sms'); ?></p>
				</td>
			</tr>
			
			<tr>
				<td>
					<p class="submit">
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="page_options" value="wp_admin_mobile,wp_sms_mcc,wp_sms_cam,wps_access_level" />
						<input type="submit" class="button-primary" name="Submit" value="<?php _e('Update', 'wp-sms'); ?>" />
					</p>
				</td>
			</tr>
		</form>	
	</table>
</div>