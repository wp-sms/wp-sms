<?php do_action('wp_sms_settings_page'); ?>

<div class="wrap">
	<?php include( dirname( __FILE__ ) . '/tabs.php' ); ?>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<td align="center" scope="row"><img src="<?php echo plugins_url('wp-sms/assets/images/logo-250.png'); ?>"></td>
			</tr>
			
			<tr valign="top">
				<td scope="row" align="center"><h2><?php echo sprintf(__('WP SMS V%s', 'wp-sms'), WP_SMS_VERSION); ?></h2></td>
			</tr>
			
			<tr valign="top">
				<td align="center" scope="row">
					<?php _e('Send a SMS via WordPress, Subscribe for sms newsletter and send an SMS to the subscriber newsletter.', 'wp-sms'); ?>
					<p><?php echo sprintf(__('This plugin is a free product of the %s', 'wp-sms'), '<a href="http://veronalabs.com/" target="_blank">Verona Labs</a>'); ?></p>
					<p><?php echo sprintf(__('Available in %s for contributors', 'wp-sms'), '<a href="https://github.com/veronalabs/wp-sms" target="_blank">Github</a>'); ?></p>
				</td>
			</tr>
			
			<tr valign="top">
				<td align="center" scope="row"><hr></td>
			</tr>
			
			<?php if($json->response == 'success') { ?>
			<tr valign="top">
				<td colspan="2" scope="row"><h2><?php _e('SMS Gateway', 'wp-sms'); ?></h2></td>
			</tr>
			
			<tr valign="top">
				<td colspan="2" scope="row">
					<p><?php _e('Dont have a sms gateway? we recommend to you the following gateways', 'wp-sms'); ?></p>
					<?php foreach($json->data as $item) { ?>
						<p>— <?php echo $item->country; ?>: <a href="<?php echo $item->url; ?>" target="_blank"><?php echo $item->name; ?></a></p>
					<?php } ?>
				</td>
			</tr>
			<?php } ?>
			
			<tr valign="top">
				<td colspan="2" scope="row"><h2><?php _e('Support', 'wp-sms'); ?></h2></td>
			</tr>
			
			<tr valign="top">
				<td colspan="2" scope="row">
					<p><?php _e('Do you have a problem?', 'wp-sms'); ?></p>
					<p>— <?php echo sprintf(__('Please open a new thread on the <a href="%s" target="_blank">Wordpress.org support forum</a>', 'wp-sms'), 'http://wordpress.org/support/plugin/wp-sms'); ?></p>
					<p><?php _e('You want add your gateway to this plugin?', 'wp-sms'); ?></p>
					<p>— <?php echo sprintf(__('Go to the link %s', 'wp-sms'), '<a href="http://wp-sms-plugin.com/gateways/" target="_blank">wp-sms-plugin.com/gateways</a>'); ?></p>
					<p><?php _e('Are you a translator?', 'wp-sms'); ?></p>
					<p>— <?php echo sprintf(__('Go to the link %s', 'wp-sms'), '<a href="http://wp-sms-plugin.com/translators/" target="_blank">wp-sms-plugin.com/contact</a>'); ?></p>
				</td>
			</tr>
		</tbody>
</table>
</div>