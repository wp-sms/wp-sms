<p><?php esc_html_e('WP SMS encountered an issue while sending SMS messages to the following numbers:', 'wp-sms'); ?></p>
<code><?php echo esc_html(implode(', ', $to)); ?></code>

<h3><?php esc_html_e('SMS Content', 'wp-sms'); ?></h3>
<p><?php echo esc_html($message); ?></p>

<h3><?php esc_html_e('API Response', 'wp-sms'); ?></h3>
<code><?php print_r(esc_html($response)); ?></code>

<p><?php esc_html_e('To address this issue, please verify the SMS configuration in the WP SMS settings page. Ensure that the credentials are correct, and you have entered the recipient numbers accurately, with or without the country code.', 'wp-sms'); ?></p>
