<?php
	function wp_sms_add_meta_links($links, $file) {
		if( $file == 'wp-sms/wp-sms.php' ) {
			$rate_url = 'http://wordpress.org/support/view/plugin-reviews/wp-sms?rate=5#postform';
			$links[] = '<a href="'. $rate_url .'" target="_blank" class="wpsms-plugin-meta-link" title="'. __('Click here to rate and review this plugin on WordPress.org', 'wp-sms') .'">'. __('Rate this plugin', 'wp-sms') .'</a>';
			
			$newsletter_url = 'http://wp-sms-plugin.com/newsletter';
			$links[] = '<a href="'. $newsletter_url .'" target="_blank" class="wpsms-plugin-meta-link" title="'. __('Click here to rate and review this plugin on WordPress.org', 'wp-sms') .'">'. __('Subscribe to our Email Newsletter', 'wp-sms') .'</a>';
			
			$links[] = '<b><a href="http://wp-sms-plugin.com/purchases" target="_blank" class="wpsms-plugin-meta-link" title="'. __('Get professional package!', 'wp-sms') .'">'. __('Get professional package!', 'wp-sms') .'</a></b>';
		}
		
		return $links;
	}
	add_filter('plugin_row_meta', 'wp_sms_add_meta_links', 10, 2);
	
	