[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/veronalabs/wp-sms.svg)](http://isitmaintained.com/project/veronalabs/wp-sms "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/veronalabs/wp-sms.svg)](http://isitmaintained.com/project/veronalabs/wp-sms "Percentage of issues still open")
[![WP compatibility](https://plugintests.com/plugins/wporg/wp-sms/wp-badge.svg)](https://plugintests.com/plugins/wporg/wp-sms/latest)
[![PHP compatibility](https://plugintests.com/plugins/wporg/wp-sms/php-badge.svg)](https://plugintests.com/plugins/wporg/wp-sms/latest)

# WP SMS Plugin
The Best WordPress SMS Messaging and Notification Plugin for WordPress!

This plugin adds the ability to send SMS to your WordPress through more than 200 SMS gateways, such as Twilio, Plivo, Clickatell, BulkSMS, Infobip, Vonage (Nexmo), Clockworksms, Messagebird, Click send and much more! [See All SMS Gateways](https://wp-sms-pro.com/gateways/)

Additionally, you can send SMS/MMS message notifications to your subscribers, users, and WooCommerce customers about their orders, new posts, products, and more.

Watch the video to learn more about the WordPress SMS Plugin!

[![Introducing WordPress SMS Plugin](https://wp-sms-pro.com/wp-content/uploads/2022/03/vlcsnap-2022-02-21-21h57m24s633.png)](https://www.youtube.com/watch?v=7r6jObgdQj0)

### Send SMS by PHP:

```php
$to[] = '01000000000';
$msg = "Hello World!";

wp_sms_send( $to, $msg );
```

### Send MMS:
```php
$mediaUrls[] = 'https://yoursite.com/image.png';

wp_sms_send( $to, $msg, false, false, $mediaUrls );
```

# Features

* Supported +180 sms gateways. [(List all gateways)](https://github.com/veronalabs/wp-sms/tree/master/includes/gateways)
* Send SMS/MMS to number(s), subscribers and WordPress users.
* Subscribe newsletter SMS.
* Send activation code to subscribe for complete subscription.
* Notification SMS when published new post to subscribers.
* Notification SMS when the new release of WordPress.
* Notification SMS when registering a new User.
* Notification SMS when get new comment.
* Notification SMS when user logged into WordPress.
* Notification SMS when user registered to subscription form.
* Integrate with (Contact form 7, WooCommerce, Easy Digital Downloads)
* Supported WP Widget for newsletter subscribers.
* Support WordPress Hooks.
* Support WP-REST API
* Import/Export Subscribers.

# Internationalization
WP SMS has been translated in to many languages, for the current list and contributors, please visit the [translate page](https://translate.wordpress.org/projects/wp-plugins/wp-sms).

Translations are done by people just like you, help make WP SMS available to more people around the world and [do a translation](http://wp-sms-pro.com/localization/) today!


# Installation
This is a development (pre-build) version which mean the assets are not built, and you need to build them yourself. for downloading the latest stable version, please visit the [WordPress Plugin Directory](https://wordpress.org/plugins/wp-sms/).

### Clone the GitHub repository
Clone the repository into your plugins directory and activate the plugin.
```bash
git@github.com:veronalabs/wp-sms.git
```

### Install dependencies
```bash
composer install
```

### Build Assets
Requirement: Before any build it`s necessary to fix these below versions then use npm
- npm v6.*
- node v14.*

Install dependencies
```bash
npm install
```

Build the blocks
```bash
npm run build
```

Build the assets
```bash
npm run sass-compile
```
```bash
npm run js-compile
```


### Unit Tests
The plugin has a suite of unit tests that can be run with PHPUnit. To run the tests, you'll need to install the development dependencies with Composer, then run the tests with [Codeception](https://codeception.com/)

```bash
composer install
```

For starting you need to create a database for testing, then copy the `.env.testing.example` file to `.env.testing` and update the database credentials.

Import the database from `tests/_data/dump.sql` in your test database.
```bash
wp db import tests/_data/dump.sql
```

Run the tests
```bash
php vendor/bin/codecept run wpunit
```

Generate new unit test
```bash
php vendor/bin/codecept generate:wpunit unit YourTestName
```

# Actions
Run the following action when sending SMS with this plugin.
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

Run the following action when subscribing a new user.
```sh
wp_sms_add_subscriber
```

Example: Send sms to user when register a new subscriber.
```sh
function send_sms_when_subscribe_new_user($name, $mobile) {
    $to = array($mobile);
    $msg = "Hi {$name}, Thanks for subscribe.";
    wp_sms_send( $to, $msg )
}
add_action('wp_sms_add_subscriber', 'send_sms_when_subscribe_new_user', 10, 2);
```

# Filters
You can use the following filter for modifying from the number.
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

You can use the following filter for modifying receivers number.
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

You can use the following filter for modifying text message.
```sh
wp_sms_msg
```

Example: Add signature to messages that are sent.
```sh
function wp_sms_modify_message($message) {
	$message = $message . ' /n Powerby: WP SMS';
	
	return $message;
}
add_filter('wp_sms_msg', 'wp_sms_modify_message');
```

# Resources
* [WordPress.org plugin URL](https://wordpress.org/plugins/wp-sms/)
* [Plugin Website](https://wp-sms-pro.com)
* [Documentation](https://wp-sms-pro.com/resources-category/api-endpoints/)
* [API Documentation](https://documenter.getpostman.com/view/3239688/UVkqsvCK#019c5b41-5916-4d2c-9661-ba933dd8ec1a)

# Donate
Would you like to support this project?
- ![Paypal](https://raw.githubusercontent.com/reek/anti-adblock-killer/gh-pages/images/paypal.png) Paypal: [Donate](https://wp-sms-pro.com/donate)
