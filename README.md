[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/veronalabs/wp-sms.svg)](http://isitmaintained.com/project/veronalabs/wp-sms "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/veronalabs/wp-sms.svg)](http://isitmaintained.com/project/veronalabs/wp-sms "Percentage of issues still open")

# WP-SMS Plugin
A simple and powerful texting plugin for wordpress

You can add to wordpress, the ability of sending sms, member of sms newsletter and send to them sms.
To every changement of position in wordpress, you can send a sms through this plugin.

The usage of this plugin is completely free. You have to just have an account from a service in the gateway listes that we support them. 
Don't worry , we have tried to add the best and the most gateways to plugin. 

Very easy Send SMS by PHP code:

1. `global $sms;`
2. `$sms->to = array('01000000000');`
3. `$sms->msg = "Hello World!";`
4. `$sms->SendSMS();`

**Do you like this project? Support it by donating**
- ![Paypal](https://raw.githubusercontent.com/reek/anti-adblock-killer/gh-pages/images/paypal.png) Paypal: [Donate](http://wp-sms-pro.com/donate)
- ![btc](https://camo.githubusercontent.com/4bc31b03fc4026aa2f14e09c25c09b81e06d5e71/687474703a2f2f7777772e6d6f6e747265616c626974636f696e2e636f6d2f696d672f66617669636f6e2e69636f) Bitcoin: 1vKe5vWbr18KitxFeFnBfjBWcFGBxTd2N

# Features

* Supported +150 sms gateways. [(List all gateways)](https://github.com/veronalabs/wp-sms/tree/master/includes/gateways)
* Send SMS to number(s), subscribers and wordpress users.
* Subscribe newsletter SMS.
* Send activation code to subscribe for complete subscription.
* Notification SMS when published new post to subscribers.
* Notification SMS when the new release of WordPress.
* Notification SMS when registering a new User.
* Notification SMS when get new comment.
* Notification SMS when user logged into wordpress.
* Notification SMS when user registered to subscription form.
* Integrate with (Contact form 7, WooCommerce, Easy Digital Downloads)
* Supported WP Widget for newsletter subscribers.
* Support Wordpress Hooks.
* Support WP REST API
* Import/Export Subscribers.

# Internationalization
WP SMS has been translated in to many languages, for the current list and contributors, please visit the [translate page](https://translate.wordpress.org/projects/wp-plugins/wp-sms).

Translations are done by people just like you, help make WP SMS available to more people around the world and [do a translation](http://wp-sms-pro.com/localization/) today!


# Installation
1. Upload `wp-sms` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. To display Subscribe goto Themes -> Widgets, and adding `Subscribe to SMS` into your sidebar Or using this functions: `<?php wp_subscribes(); ?>` into theme.
or using this Shortcode `[subscribe]` in Posts pages or Widget.
4. Using this functions for send manual SMS:

* First: `global $sms;`
* `$sms->to = array('MobileNumber');`
* `$sms->msg = "YourMessage";`
* Send SMS: `$sms->SendSMS();`

# Actions
Run following action when send sms with this plugin.
```sh
wp_sms_send
```

Example: Send mail when send sms.
```sh
function send_mail_when_send_sms($message_info) {
	wp_mail('you@mail.com', 'Send SMS', $message_info);
}
add_action('wp_sms_send', 'send_mail_when_send_sms');
```

Run following action when subscribe a new user.
```sh
wp_sms_add_subscriber
```

Example: Send sms to user when register a new subscriber.
```sh
function send_sms_when_subscribe_new_user($name, $mobile) {
	global $sms;
	$sms->to = array($mobile);
	$sms->msg = "Hi {$name}, Thanks for subscribe.";
	$sms->SendSMS();
}
add_action('wp_sms_add_subscriber', 'send_sms_when_subscribe_new_user', 10, 2);
```

# Filters
You can use following filter for modify from number.
```sh
wp_sms_from
```

Example: Add 0 to the end sender number.
```sh
function wp_sms_modify_from($from) {
	$from = $from . ' 0';
	
	return $val;
}
add_filter('wp_sms_from', 'wp_sms_modify_from');
```

You can use following filter for modify receivers number.
```sh
wp_sms_to
```

Example: Add new number to get message.
```sh
function wp_sms_modify_receiver($numbers) {
	$numbers[] = '09xxxxxxxx';
	
	return $numbers;
}
add_filter('wp_sms_to', 'wp_sms_modify_receiver');
```

You can use following filter for modify text message.
```sh
wp_sms_msg
```

Example: Add signature to messages that are sent.
```sh
function wp_sms_modify_message($message) {
	$message = $message . ' /n Powerby: WP-SMS';
	
	return $message;
}
add_filter('wp_sms_msg', 'wp_sms_modify_message');
```

# Rest API Endpoints
Add new subscribe to sms newsletter
```sh
POST /wpsms/v1/subscriber/add
```

# Community Links
* [WordPress plugin page](http://wordpress.org/plugins/wp-sms/)
* [Plugin Website](http://wp-sms-pro.com/)
* [Author Website](http://veronalabs.com)

