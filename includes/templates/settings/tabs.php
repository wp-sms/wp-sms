<h2 class="nav-tab-wrapper">
	<a href="?page=wp-sms-settings" class="nav-tab<?php if(isset($_GET['tab']) == '') { echo " nav-tab-active";} ?>"><?php _e('General', 'wp-sms'); ?></a>
	<a href="?page=wp-sms-settings&tab=web-service" class="nav-tab<?php if($_GET['tab'] == 'web-service') { echo " nav-tab-active"; } ?>"><?php _e('SMS Gateway', 'wp-sms'); ?></a>
	<a href="?page=wp-sms-settings&tab=newsletter" class="nav-tab<?php if($_GET['tab'] == 'newsletter') { echo " nav-tab-active"; } ?>"><?php _e('Newsletter', 'wp-sms'); ?></a>
	<a href="?page=wp-sms-settings&tab=features" class="nav-tab<?php if($_GET['tab'] == 'features') { echo " nav-tab-active"; } ?>"><?php _e('Features', 'wp-sms'); ?></a>
	<a href="?page=wp-sms-settings&tab=notifications" class="nav-tab<?php if($_GET['tab'] == 'notifications') { echo " nav-tab-active"; } ?>"><?php _e('Notifications', 'wp-sms'); ?></a>
	<a href="?page=wp-sms-settings&tab=about" class="nav-tab<?php if($_GET['tab'] == 'about') { echo " nav-tab-active"; } ?>"><?php _e('About', 'wp-sms'); ?></a>
	<?php if( is_plugin_active( 'wp-sms-pro/wp-sms-pro.php' ) == false ) { ?>
	<a href="http://wp-sms-plugin.com/purchase/" target="_blank" class="nav-tab wpsms-premium-tab"><?php _e('Premium Pack', 'wp-sms'); ?></a>
	<?php } ?>
</h2>