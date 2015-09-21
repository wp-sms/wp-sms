<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery('#wp_subscribes_send').click(function() {
		jQuery('#wp_subscribes_stats').fadeToggle();
	});
	
	jQuery('#wpsms-nrnu-stats').click(function() {
		jQuery('#wpsms-nrnu').fadeToggle();
	});
	
	jQuery('#wpsms-gnc-stats').click(function() {
		jQuery('#wpsms-gnc').fadeToggle();
	});
	
	jQuery('#wpsms-ul-stats').click(function() {
		jQuery('#wpsms-ul').fadeToggle();
	});
});
</script>

<tr><th scope="row"><strong><?php _e('Wordpress', 'wp-sms'); ?></strong></th><td><hr></td></tr>
<tr>
	<th><?php _e('Published new posts', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wp_subscribes_send]" id="wp_subscribes_send" <?php echo $wps_options['wp_subscribes_send'] ==true? 'checked="checked"':'';?>/>
		<label for="wp_subscribes_send"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to subscribers When published new posts.', 'wp-sms'); ?></p>
	</td>
</tr>

<?php if( $wps_options['wp_subscribes_send'] ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
<tr valign="top"<?php echo $hidden;?> id='wp_subscribes_stats'>
	<td scope="row">
		<label for="wpsms-text-template"><?php _e('Text template', 'wp-sms'); ?>:</label>
	</th>
	
	<td>
		<textarea id="wpsms-text-template" cols="50" rows="7" name="wpsms[wp_sms_text_template]"><?php echo $wps_options['wp_sms_text_template']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
		<p class="description data">
			<?php _e('Input data:', 'wp-sms'); ?>
			<?php _e('Title post', 'wp-sms'); ?>: <code>%title_post%</code>
			<?php _e('URL post', 'wp-sms'); ?>: <code>%url_post%</code>
			<?php _e('Date post', 'wp-sms'); ?>: <code>%date_post%</code>
		</p>
	</td>
</tr>

<tr>
	<th><?php _e('The new release of WordPress', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wp_notification_new_wp_version]" id="wp_notification_new_wp_version" <?php echo $wps_options['wp_notification_new_wp_version'] ==true? 'checked="checked"':'';?>/>
		<label for="wp_notification_new_wp_version"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to you When the new release of WordPress.', 'wp-sms'); ?></p>
	</td>
</tr>

<tr>
	<th><?php _e('Register a new user', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wpsms_nrnu_stats]" id="wpsms-nrnu-stats" <?php echo $wps_options['wpsms_nrnu_stats'] ==true? 'checked="checked"':'';?>/>
		<label for="wpsms-nrnu-stats"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to you and user when register on wordpress.', 'wp-sms'); ?></p>
	</td>
</tr>

<?php if( $wps_options['wpsms_nrnu_stats'] ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
<tr valign="top"<?php echo $hidden;?> id="wpsms-nrnu">
	<td scope="row">
		<label for="wpsms-nrnu-tt"><?php _e('Text template', 'wp-sms'); ?>:</label>
	</th>
	
	<td>
		<p><?php _e('For user:', 'wp-sms'); ?></p>
		<textarea id="wpsms-nrnu-tt" cols="50" rows="7" name="wpsms[wpsms_nrnu_tt]"><?php echo $wps_options['wpsms_nrnu_tt']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
		<p class="description data">
			<?php _e('Input data:', 'wp-sms'); ?>
			<?php _e('Username', 'wp-sms'); ?>: <code>%user_login%</code>
			<?php _e('User email', 'wp-sms'); ?>: <code>%user_email%</code>
			<?php _e('Date register', 'wp-sms'); ?>: <code>%date_register%</code>
		</p>
		
		<p><?php _e('For admin:', 'wp-sms'); ?></p>
		<textarea id="wpsms-nrnu-tt" cols="50" rows="7" name="wpsms[wpsms_narnu_tt]"><?php echo $wps_options['wpsms_narnu_tt']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
		<p class="description data">
			<?php _e('Input data:', 'wp-sms'); ?>
			<?php _e('Username', 'wp-sms'); ?>: <code>%user_login%</code>
			<?php _e('User email', 'wp-sms'); ?>: <code>%user_email%</code>
			<?php _e('Date register', 'wp-sms'); ?>: <code>%date_register%</code>
		</p>
	</td>
</tr>

<tr>
	<th><?php _e('New comment', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wpsms_gnc_stats]" id="wpsms-gnc-stats" <?php echo $wps_options['wpsms_gnc_stats'] ==true? 'checked="checked"':'';?>/>
		<label for="wpsms-gnc-stats"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to you When get a new comment.', 'wp-sms'); ?></p>
	</td>
</tr>

<?php if( $wps_options['wpsms_gnc_stats'] ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
<tr valign="top"<?php echo $hidden;?> id="wpsms-gnc">
	<td scope="row">
		<label for="wpsms-gnc-tt"><?php _e('Text template', 'wp-sms'); ?>:</label>
	</th>
	
	<td>
		<textarea id="wpsms-gnc-tt" cols="50" rows="7" name="wpsms[wpsms_gnc_tt]"><?php echo $wps_options['wpsms_gnc_tt']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
		<p class="description data">
			<?php _e('Input data:', 'wp-sms'); ?>
			<?php _e('Comment author', 'wp-sms'); ?>: <code>%comment_author%</code>
			<?php _e('Comment author email', 'wp-sms'); ?>: <code>%comment_author_email%</code>
			<?php _e('Comment author url', 'wp-sms'); ?>: <code>%comment_author_url%</code>
			<?php _e('Comment author IP', 'wp-sms'); ?>: <code>%comment_author_IP%</code>
			<?php _e('Comment date', 'wp-sms'); ?>: <code>%comment_date%</code>
			<?php _e('Comment content', 'wp-sms'); ?>: <code>%comment_content%</code>
		</p>
	</td>
</tr>

<tr>
	<th><?php _e('User login', 'wp-sms'); ?></th>
	<td>
		<input type="checkbox" name="wpsms[wpsms_ul_stats]" id="wpsms-ul-stats" <?php echo $wps_options['wpsms_ul_stats'] ==true? 'checked="checked"':'';?>/>
		<label for="wpsms-ul-stats"><?php _e('Active', 'wp-sms'); ?></label>
		<p class="description"><?php _e('Send a sms to you When user is login.', 'wp-sms'); ?></p>
	</td>
</tr>

<?php if( $wps_options['wpsms_ul_stats'] ) { $hidden=""; } else { $hidden=" style='display: none;'"; }?>
<tr valign="top"<?php echo $hidden;?> id="wpsms-ul">
	<td scope="row">
		<label for="wpsms-ul-tt"><?php _e('Text template', 'wp-sms'); ?>:</label>
	</th>
	
	<td>
		<textarea id="wpsms-ul-tt" cols="50" rows="7" name="wpsms[wpsms_ul_tt]"><?php echo $wps_options['wpsms_ul_tt']; ?></textarea>
		<p class="description"><?php _e('Enter the contents of the sms message.', 'wp-sms'); ?></p>
		<p class="description data">
			<?php _e('Input data:', 'wp-sms'); ?>
			<?php _e('User login', 'wp-sms'); ?>: <code>%username_login%</code>
			<?php _e('Display name', 'wp-sms'); ?>: <code>%display_name%</code>
		</p>
	</td>
</tr>