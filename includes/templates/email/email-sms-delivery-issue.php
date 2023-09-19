<p><?php _e('WP SMS encountered an issue while sending SMS messages to the following numbers:', 'wp-sms'); ?></p>

<code><?php echo implode(', ', $to); ?></code>

<p><b><?php _e('SMS Content', 'wp-sms'); ?>:</b></p>

<?php echo $message; ?>

<p><b><?php _e('API Response', 'wp-sms'); ?>:</b></p>

<p><code><?php print_r($response); ?></code></p>

<p><?php _e('To address this issue, please verify the SMS configuration in the WP SMS settings page. Ensure that the credentials are correct, and you have entered the recipient numbers accurately, with or without the country code.', 'wp-sms'); ?></p>