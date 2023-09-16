<p>WP SMS encountered an issue while sending SMS messages to the following numbers:</p>

<code><?php echo implode(', ', $to); ?></code>

<p><b>SMS Content:</b></p>

<?php echo $message; ?>

<p><b>API Response:</b></p>

<p><code><?php echo $response; ?></code></p>

<p>To address this issue, please verify the SMS configuration in the WP SMS settings page. Ensure that the credentials are correct, and you have entered the recipient numbers accurately, with or without the country code.</p>