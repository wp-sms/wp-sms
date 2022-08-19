=== WP SMS - Messaging & SMS Notification for WordPress, WooCommerce, GravityForms, etc ===
Contributors: mostafa.s1990, kashani, veronalabs, alifallahrn
Donate link: https://wp-sms-pro.com/donate
Tags: sms, wordpress, send, subscribe, message, register, notification, webservice, API, woocommerce, subscribes-sms, EDD, twilio, bulksms, clockworksms, nexmo
Requires at least: 3.0
Tested up to: 6.0
Requires PHP: 5.6
Stable tag: 5.7.9
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The Best WordPress SMS Messaging and Notification Plugin for WordPress!

== Description ==
This plugin adds the ability to send SMS to your WordPress through more than 200 SMS gateways, such as Twilio, Plivo, Clickatell, BulkSMS, Infobip, Vonage (Nexmo), Clockworksms, Messagebird, Click send and much more! [See All SMS Gateways](https://wp-sms-pro.com/gateways/)

Additionally, you can send SMS/MMS message notifications to your subscribers, users, and WooCommerce customers about their orders, new posts, products, and more.

Watch the video to learn more about the WordPress SMS Plugin!

https://www.youtube.com/watch?v=7r6jObgdQj0

Main plugin's feature

* Send SMS/MMS to either your users’ numbers or specific numbers
* Get your users’ mobile numbers when they subscribe to your newsletters
* Send SMS automatically to users and admins in different situations
* Increase the security by two-step verification
* Login with your mobile number in case that you forget your password
* And many more!

This plugin is free. You just need to have an account from one of the services in the list of gateways we support.

Don’t worry, we have tried to cover the best and the most well-known gateways for the plugin. Also, the Pro version is available too.

= Features =
* Supporting more than 250 SMS gateways! [See All SMS Gateways](https://wp-sms-pro.com/gateways/)
* Sending SMS/MMS to the mobile number(s), your subscribers and WordPress users
* Subscribing/Opt-In SMS newsletters and groups
* Unsubscribing/Opt-Out SMS through widget or URL
* Sending Activation Codes to subscribers when a new post is published and also when subscribers are completing their subscription process
* Sending Notification SMS to administrators
 * To inform new releases of WordPress
 * When a new user is registered
 * When new comments are posted
 * When users are logged into the WordPress
 * When users are registered to subscribe in forms
* Integration with Contact Form 7, WooCommerce, Easy Digital Downloads. Integration with other plugins is also possible in WP SMS Pro version.
* Supporting Widget/Gutenberg for showing SMS Newsletter Form
* Supporting WordPress Hooks
* Supporting WP-REST API
* Importing/Exporting Subscribers.
* Make short URLs by Bitly.com

= PRO PACKAGE =
In the Pro pack, most of the integrations with other plugins and some popular gateways are in the pro pack. User registration verification, WooCommerce Mobile Verification, order notifications, etc.

The list of supported gateways and integrated plugins are available in FAQ.
[Buy Pro Package](http://wp-sms-pro.com/buy/)


= Translations =
WP SMS has been translated in to many languages, for the current list and contributors, please visit the [translate page](https://translate.wordpress.org/projects/wp-plugins/wp-sms).

Translations are done by people just like you, help make WP SMS available to more people around the world and [do a translation](http://wp-sms-pro.com/localization/) today!

= Contributing and Reporting Bugs =
WP SMS is being developed on GitHub. If you’re interested in contributing to the plugin, please look at [GitHub page](https://github.com/veronalabs/wp-sms).
[Donate to this plugin](http://wp-sms-pro.com/donate)


== Installation ==

https://www.youtube.com/watch?v=uZVs8DXu_XM

Installing the WP SMS plugin is similar to installing any other WordPress plugin, the simple way which is recommended is:

1. Upload `wp-sms` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. To display the SMS newsletter form, go to Themes > Widgets, and add a WP SMS Subscribe form.
4. If you're using the wp-sms-pro as well, don't forget to enter your license key on Pro Pack > General

If you need more information, please [click here](https://wp-sms-pro.com/resources/installation/).

== Frequently Asked Questions ==
= What gateways are supported in the plugin? =
You can see the list of all supported gateways [through this link](https://wp-sms-pro.com/gateways). More gateways are supported in the Pro. The followings are some of popular gateways:

* Twilio.com
* Plivo.com
* Clickatell.com
* Bulksms.com
* Infobip.com
* Vonage.com (Nexmo)
* Clockworksms.com
* Messagebird.com
* Clicksend.com
* Octopush.com
* Sms.to
* Mitto.ch
* Smsapi.com
* Dsms.in
* Esms.vn
* Slinteractive.com.au
* Reach-interactive.com
* Msg91.com
* Msg360.in
* Isms.com.my
* Sms4marketing.it
* Magicdeal4u.com
* Alfa-cell.com
* Moceansms.com
* Global-voice.net
* Livesms.eu
* Ozioma.net
* Pswin.com
* Ra.sa
* Smsfactor.com
* Textmarketer.co.uk
* Smslive247.com
* Sendsms247.com
* Ssdindia.com
* Viensms.com
* Vsms.club
* Websms.at
* Smstrade.de
* Bulksmshyderabad.co.in
* Yamamah.com
* Cellsynt.net
* Cmtelecom.com
* Zain.im

= What are the differences between Free and Pro Pack versions? =
* User Verification Registration through SMS
* Scheduled sending SMS
* Professional support & ticketing
* More supported gateways (listed above)
* Integrations with more plugins as listed below:
 * Integration with BuddyPress: You can add mobile number fields to the profile page, send SMS to users when they’re mentioned in a post, and send SMS to users when they comment on a post.
 * Integration with WooCommerce: Order SMS notifications, verify customer mobile number during the checkout, SMS notification to customers and subscribers for new products, SMS notification for administrator when the stock is low, and also you can send SMS to customers when the order status is changed.
 * Integration with Gravity forms: The plugin can send SMS to users and Admin after the form is submitted.
 * Integration with Quform: The plugin can send SMS to users or Admin after the form is submitted.
 * Integration with Easy Digital Downloads: You can add mobile number fields to the profile page, and send SMS to users or Admin when an order is submitted with EDD.
 * Integration with WP Job Manager: You can add mobile number fields to Job forms and send SMS to employers or Admin when a job is requested with WP Job Manager.
 * Integration with Ultimate Members plugin.
 * Integration with Bitly.com for make the URLs shorter

= How to buy? =
You can buy the Pro pack version [through this link](http://wp-sms-pro.com/buy/)

= PHP v7, v8 Supports? =
Yes! WP SMS is compatible with PHP version v5.6 up to v8

= How to send SMS with PHP? =
Use the below code to send SMS through PHP:

	$to[] = '01000000000';
	$msg = "Your Message";
	$is_flash = true;
	wp_sms_send( $to, $msg, $is_flash );

= How to send MMS with PHP? =
Use the below code to send MMS through PHP:

	$to[] = '01000000000';
	$msg = "Your Message";
	$mediaUrls[] = 'https://yoursite.com/image.png';
	wp_sms_send( $to, $msg, false, false, $mediaUrls );

= How to show the SMS subscribe form? =
There are three ways to show the subscriber form in the theme

1. By adding the SMS Newsletter through the Widget into the theme
2. By adding the SMS Newsletter through the Gutenberg editor

= How to use the Actions? =
Run the following action when sending SMS with this plugin:
`wp_sms_send`

Example: Send emails after sending SMS

	function send_mail_after_sending_sms($message_info) {
		wp_mail('you@mail.com', 'Send SMS', $message_info);
	}
	add_action('wp_sms_send', 'send_mail_after_sending_sms');

Run the following action when subscribing a new user.
`wp_sms_add_subscriber`

Example: Send Welcome SMS to users when they are registered.

	function send_sms_when_subscribe_new_user($name, $mobile) {
		$to = array($mobile);
        $msg = "Hi {$name}, Thanks for subscribe.";
        wp_sms_send($to, $msg);
	}
	add_action('wp_sms_add_subscriber', 'send_sms_when_subscribe_new_user', 10, 2);

= How to use the Filters? =
You can use the following filter to modify numbers.
`wp_sms_from`

Example: Add 0 to the end of the sender number

	function wp_sms_modify_from($from) {
		$from = $from . ' 0';
		return $val;
	}
	add_filter('wp_sms_from', 'wp_sms_modify_from');

You can use the following filter to modify the receivers’ numbers.
`wp_sms_to`

Example: Add new numbers to your numbers

	function wp_sms_modify_receiver($numbers) {
		$numbers[] = '01xxxxxxxx';
		return $numbers;
	}
	add_filter('wp_sms_to', 'wp_sms_modify_receiver');

You can use the following filter to modify text messages
`wp_sms_msg`

Example: Add signatures to messages that are sent

	function wp_sms_modify_message($message) {
		$message = $message . ' /n Powerby: WP SMS';
		return $message;
	}
	add_filter('wp_sms_msg', 'wp_sms_modify_message');

= Does It Support REST API? =
Yes. It does, see [https://wp-sms-pro.com/resources-category/api-endpoints/](documentation).

= How to get the Pro Pack updates? =
If you've already the pro pack version, you have to enter your license key in the setting page to get the updates.

Anyway the plugin supports registering the license key through `wp-config.php`

	define('WP_SMS_PRO_LICENSE', 'your-license-key');

= How to unsubscribe a number by URL? =
Your subscribers can unsubscribe by URL [https://yourdomain.com/?wpsms_unsubscribe=01111111111](https://yourdomain.com/?wpsms_unsubscribe=01111111111)

= How to redirect clients to a specific page after unsubscribing by URL? =
Here is the hook that you need to use. Just replace XXXXX with desired path.

	add_action( 'wp_sms_number_unsubscribed_through_url', function($number){
    	wp_redirect( '/XXXXX' );
    	exit;
	} );

= How to customize WP SMS? =
We can customize the plugin based on your need. Just visit our [Plugin Development Services](https://veronalabs.com/plugin-development).

== Screenshots ==
1. General Settings page
2. Gateway Settings page
3. SMS Newsletter Settings page
4. Two-Factor Authentication SMS (2FA) Login Settings page
5. BuddyPress Settings page
6. WooCommerce Settings page
7. Send SMS/MMS page
8. Outbox SMS/MMS page
9. Scheduled page
10. Subscribers page
11. Groups page
12. Privacy page
13. Login With Mobile
14. Login With Mobile
15. SMS Subscribers Form
16. Gravity Forms Settings page
17. SMS Send/Receive stats widget

== Upgrade Notice ==
= v5.7.3 =
* If your theme uses these endpoints for SMS subscribers, you have to update it as bellow:
`DELETE: wpsms/v1/newsletter` to: `POST: wpsms/v1/newsletter/unsubscribe`
`PUT: wpsms/v1/newsletter` to: `POST: wpsms/v1/newsletter/verify`

= v5.6 =
* If you have installed the Pro Pack (wp-sms-pro), please make sure that's updated to v3.3.*

== Changelog ==
= v5.7.9 - 19.08.2022 =
* Bugfix: Multiple recipients for the quick reply
* Improvement: Mobile International Input Functionality
* Improvement: Mobile number validation in while of the plugin to keep the valid numbers
* Improvement: Minor & clean-up things
* Add: Support SMS gateway Ajura Technologies from Bangladesh

= v5.7.8 - 05.08.2022 =
* New: Support Quick reply in admin area for sending quick replies to number(s) or a group
* New: Ability to change items per page in outbox, inbox, scheduled, and group
* Bugfix: Sorting function issue in admin pages fixed
* Bugfix: The issue in the OurSms gateway has been fixed
* Bugfix: Wrong calling function in `wp_sms_sanitize_array()` fixed
* Improvement: The separate dial code option removed
* Improvement: Minor things

[Feature suggestions that are not listed above are welcome!](https://wp-sms-pro.com/contact)

= v5.7.7 - 17.07.2022 =
* Bugfix: The general settings page has been fixed
* Bugfix: The search issue in subscribers and other admin areas has been fixed
* Bugfix: Fixes select2 inputs and some CSS tweaks
* Improvement: Add a feature to clear SMS previews after sending

= v5.7.6 - 05.07.2022 =
* Bugfix: SMS gateway UwaziiMobile has been fixed
* New: SMS gateway Aobox.it has been added
* New: Filter `wp_sms_output_variables_message` has been added

= v5.7.5.1 - 16.06.2022 =
* Bugfix: Bulksms.com gateway issue has been fixed

= v5.7.5 - 12.06.2022 =
* Improvement: Better settings fields organization
* Improvement: Update the subscriber button label while switching the subscriber action
* New: Default group option has been added to the SMS newsletter form
* New: Short URLs supported in the scheduled SMS
* New: Better rendering the mobile number fields by new function `wp_sms_render_mobile_field()`
* Bugfix: The post content words count field in SMS post notification has been fixed
* Bugfix: WP dashboard margin issue has been fixed
* Bugfix: Duplicate send SMS when the ForceToSend option is enabled in SMS post notification

= v5.7.4 - 25.05.2022 =
* New: Gateway Hostpinnacle from Kenya
* New: Gateway Tubelight Communications from India
* New: Add the possibility to translate the strings in the settings with WPML by custom wpml-config.xml file
* Improvement: The Post SMS notification box when the force send is enabled
* Improvement: Tweak in admin styles and CSS
* Improvement: Supported DLT for GatewayHub
* Bugfix: Keep the mobile number field after updating the profile
* Bugfix: Showing display spinner when styles are not loaded

= v5.7.3.1 - 07.05.2022 =
* Bugfix: An issue in settings page has been fixed

= v5.7.3 - 05.05.2022 =
* New: Gateway Espay.id has been added
* New: Support MMS and `%post_thumbnail%` variable in send post notification
* Bugfix: Fix separating the numbers by comma and space in send sms page
* Update: Library intlTelInput updated to v17.0.16
* Update: Add possibility to Gateway.sa to choose the API type (Local or International)
* Improvement: Delete deprecated subscribers endpoint (see the update notice)
* Improvement: The newsletter REST-API endpoints have been restructured
* Improvement: Compatibility of the SMS newsletter form with the Godaddy host provider
* Improvement: Compatible the intlTelInput with RTL languages
* Improvement: Possibility to select the mobile country code instead of entering them

= v5.7.2.2 - 24.04.2022 =
* Bugfix: An issue to register the schedule event has been fixed
* Bugfix: The warning wp-editor error has been fixed
* Update: A new SMS gateway from Latvia (texti.fi) has been added.
* Update: SMS Gateway VFirst has been removed due to not stability of API

= v5.7.2.1 - 15.04.2022 =
* Bugfix: The warning error in settings page when the groups empty
* Improvement: Improvement license updater

= v5.7.2 - 12.04.2022 =
* Feature: The Add-Ons page added! [Checkout New Add-Ons!](https://wp-sms-pro.com/product-category/add-ons/)
* Feature: The SMS Stats dashboard widget has been added!
* Feature: The Inbox page added!
* Feature: Add new ability to choose the specific groups to show on SMS newsletter widget
* Improvement: Supported short URL in `generateUnSubscribeUrlByNumber()` function.
* Improvement: The register setting page has been improvement
* Improvement: Clean up admin styles, scripts, and improvements structure as well
* Improvement: Compatibility of the `request()` method with PHP v8.0
* Improvement: The Integration tab is renamed to Contact form 7 and also the basic options (WooCommerce and EDD) have been removed since they are available in the Pro pack as well
* Improvement: The mobile country code functionality has been improved.

= v5.7.1 - 16.03.2022 =
* Bugfix: The issue in media URLs REST API request even the request doesn't have the media URL
* Bugfix: Separating numbers issue has been fixed in some gateways
* Feature: New action `wp_sms_number_unsubscribed_through_url` has been added
* Feature: New method `request()` has been added
* Improvement: Minors and a couple of typos

= v5.7 - 07.03.2022 =
* Feature: New Design for Send SMS page!
* Feature: New filters `wp_sms_user_mobile_field` and `wp_sms_user_mobile_number` has been added.
* Feature: The Post SMS Notification also supports groups and custom numbers as well
* Feature: Bitly Short URL is also supported in Send SMS page
* Feature: Endpoint `/wp-json/wpsms/v1/send` now supports new parameters. [See API document](https://documenter.getpostman.com/view/3239688/UVkqsvCK#019c5b41-5916-4d2c-9661-ba933dd8ec1a)
* Improvement: The functionality of getting user's mobiles
* Improvement: Update strings and fixed some typos
* Improvement: Admin Styles updated and fixes a couple of issues in RTL languages and responsive mode as well.
* Improvement: Compatibility the "Post SMS Notification" with WP-REST, WP-CLI, and other plugins
* Bugfix: Gateways UwaziiMobile updated.
* Bugfix: Fix unsubscribing the numbers with mobile country code through URL
* Bugfix: Compatibility a bunch of gateways with old PHP versions

= v5.6.9 - 16.02.2022 =
* Improvement: The SureSms gateway now supports the Sender ID and Flash
* Improvement: Compatible the Post SMS Notification with WP-REST API
* Improvement: The Force to Post SMS Notification and default Subscribe group options added
* Improvement: Improvements widget and admin styles
* Improvement: Send flash SMS enabled for eBulkSms.com
* Improvement: Send flash SMS disabled for Africa's Talking

= v5.6.8.1 - 09.02.2022 =
* Feature: Add specific roles option for User login notification
* Bugfix: Compatibility the setting page with PHP v7.2

= v5.6.8 - 02.02.2022 =
* Update: Tested up to v5.9
* Update: The SMS Newsletter widget is improvement and redesigned and also is Block based right now! you can also load the SMS Newsletter in Gutenberg editor!
* Bugfix: An issue with the old version of PHP with the setting page.
* Improvement: Some typo in the response of requests has been fixed.
* Deprecate: Function `wp_sms_subscribes()`. load the SMS newsletter form through Widget or Gutenberg instead.
* Remove: Shortcode `[wp-sms-subscriber-form]` is removed.

= v5.6.7 - 21.01.2022 =
* Bugfix: The line break issue has been fixed
* Bugfix: The error in webSMS gateway has been fixed
* Improvement: Compatibility the setting page with the older version of PHP.

= v5.6.6 - 15.01.2022 =
* Feature: Bitly Short URL has been added in the settings page > feature
* Update: Compatibility the Setting page with QuForm Child Elements and groups fields and minor improvements.
* Update: Better naming fields for GravityForms variable fields
* Updated gateway.sa gateway
* Updated uwaziimobile gateway

= v5.6.5.2 - 10.12.2021 =
* Improvement: A notice error has been fixed in the setting page.
* Improvement: Compatibility with the older version of PHP.

= v5.6.5 - 07.12.2021 =
* Feature: Selecting the several user groups on send SMS page has been supported
* Bugfix: The post type & author notification issue has been fixed
* Enhancement: The setting pages & styles improvement, enjoy the new admin interface!
* Update: Pro settings page merged to the main settings page

= v5.6.4 - 14.11.2021 =
* Bugfix: Getting credential in ExpertTexting gateway has been fixed
* Bugfix: Notice errors in OnewaySms gateway has been fixed
* Enhancement: Minor improvements

= v5.6.3 - 22.10.2021 =
* Feature: Supported Unsubscribing/Opting-Out by URL! the subscribers can Opting-Out by [https://yourdomain.com/?wpsms_unsubscribe=01111111111](https://yourdomain.com/?wpsms_unsubscribe=01111111111)
* Enhancement: Fixed a notice error in notification class
* Enhancement: Added the document link in settings
* Update: Added the MT URL and Credit Balance URL to OneWaySMS gateway
* Update: Added Callifony (Zen.ae) gateway

= v5.6.2 - 02.10.2021 =
* NEW: MMS supported! now the plugin supports sending MMS, the Twilio & Plivo gateways are supports at the moments.
* Update: Added the argument `$mediaUrls` to `wp_sms_send()` function.
* Update: Ability to modify the admin tabs by using the `wpsms_pro_settings_tabs` and `wpsms_settings_tabs` filters.
* Update: The chosen library replaced with select2.
* Bugfix: The issue for sending the SMS while publish a new post has been fixed.
* Enhancement: For getting the correct local time, used the `current_datetime()` instead of `current_time()`.

= 5.6.1 =
* Updated Expert Texting gateway's fields
* Updated setting page and fixed some tweak misspellings
* Added BareedSMS gateway in the setting page
* Added Mobishastra gateway in the setting page
* Added Zipwhip gateway in the setting page
* Added post type option in the published new posts notification
* Fixed Encode message in WaApi Gateway
* Fixed the conflict issue with newsletter settings filter name
* Fixed an issue to unsubscribe the number in REST API
* Improvement the license settings functionality

= 5.5.1 =
* Fixed showing correct license status issue in the plugin's admin header
* Fixed ExpertTexting gateway

= 5.5 =
* New admin design
* Added multiple sending SMS to CF7 field
* Added Mitto SMS gateway
* Fixed some sanitization issues in input data.
* Fixed separating the numbers issue in the send SMS page
* Updated msegat.com and reach-interactive gateways (Please re-configure your gateway again)

= 5.4.13 =
* Updated Unifonic gateway
* Improvement of some inputs on the admin and sanitizes. (Special thanks to WPScan.com)

= 5.4.12 =
* Fixed wrong sanitize data type on CF7 functionality.
* Minor improvements

= 5.4.11 =
* Compatibility with WordPress v5.8
* Updated SMSBox.be, moved to free version
* Improvement sanitizing input

= 5.4.10 =
* Updated ExpertTexting gateway, the API call to correspond to the current API for ExpertTexting. Needed to use api_secret instead of api_password and the from= should not be an empty string.
* Improvement: Replaced all CURL with WordPress HTTP API functions
* Fixed a couple of issues on loading the files, sanitizing things and etc.

= 5.4.9.1 =
* Fixed Sanitize some input data in admin screens

= 5.4.9 =
* Implemented dynamic gateway setting fields based on the current gateway's class.
* Added global-voice.net gateway.
* Added jawalbsms.ws gateway.

= 5.4.8 =
* Added dexatel.com
* Fixed an issue in smssolutionsaustralia.com.au

= 5.4.7 =
* Fixed some tweak issues in applying country code and user registration functionality.
* Added a new option to make verify_mobile field optional
* Added WaApi gateway
* Added newsletter method in the main class to accessible from by `WPSms()->newsletter();`

= 5.4.6 =
* Fixed an issue to verify the subscriber in the SMS newsletter widget.

= 5.4.5 =
* Added a new filter `wp_sms_admin_notify_registration` for admin receivers mobile numbers in registration new user
* Added a new property `$documentUrl` in gateways' class
* Added some useful document link in the setting page
* Added functionality in the subscriber's list to update multi subscribers group
* Added group id column in the groups' table
* Added Singleton functionality to initial the plugin and added `WPSms();` function to get an instance of the plugin

= 5.4.4 =
* Added smssolutionsaustralia.com.au SMS gateway
* Added a new option in the Admin > Subscribers to change the number of items per page
* Fixed group issue on SMS subscribe form
* Updated reach-interactive API URL
* Improvement minor tweak

= 5.4.3 =
* Updated the gateways list and fixed some wrong names.
* Added Octopush.com gateway
* Added SlinterActive.com.au gateway
* Fixed sending the welcome message for new SMS subscriber.

= 5.4.2 =
* Added onewaysms.com.my gateway
* Update kavenega gateway
* Compatibility with WordPress v5.6

= 5.4.1 =
* Added Reach-Interactive gateway
* Added Msegat gateway
* Fixed encoding issue in Altiria gateway
* Removed The welcome page
* Improvement gateways' countries list.
* Improvement Minor

= 5.4 =
* Added New SMS gateways in the plugin (unifonic.com, comilio.it, malath.net.sa, altiria.net, and oxemis.com)
* Added A new option in the setting page for cleaning the numbers.
* Added Newline support for numbers in sending SMS page.
* Added Auto-submit the gateway while changing the gateway dropdown.
* Improvement Appending country code to numbers.
* Improvement Gateways and setting pages.
* Improvement CSS and admin notice with the new version of WordPress.

= 5.3.1 =
* Added: malath.net.sa gateway.
* Added: safa-sms.com gateway.
* َUpdated: some old gateways.

= 5.3 =
* Added: eazismspro.com gateway.
* Added: sms.net.gr gateway.
* Added: New option for cropping message in SMS post notification.
* Added: SMS meta box in custom post types.
* Updated: Mobile field in the registration form. It has required.

= 5.2.2 =
* Added: Eazismspro.com gateway
* Improvement: Mobile field number is required in the registration form
* Updated: Dynamic the number of the word for cropping in send post notification
* Updated: Display gravity form fields tags in settings

= 5.2.1 =
* Improvement: CF7 integration, now the dropdown field is supported.
* Updated: ms77.de gateway.

= 5.2 =
* Added: The from parameter in `wp_sms_send()`.
* Added: Sunwaysms.com gateway.
* Updated: New API for Sms Gateway Center.
* Updated: MTarget's gateway.
* Updated Sunwaysms.com gateway.
* Disabled: The check credit in send sms page.

= 5.1.9 =
* Fixed: gateways list.
* Fixed: scheduled feature class loading.
* Updated: Mobtexting gateway.
* Minor improvements.

= 5.1.8 =
* Added: cheapglobalsms.com gateway.
* Minor improvements.

= 5.1.7 =
* Added: easysendsms.com gateway.
* Added: 1s2u.com gateway.
* Minor improvements.

= 5.1.6 =
* Fixed: WordPress core update notification notice.
* Fixed: Screen options columns for only Privacy Page.
* Fixed: Outbox orderby showing records.
* Added: Oursms.net Gateway.
* Added: Eurosms.com gateway.
* Improved: Newsletter Widget/Shortcode.
* Minor improvements.

= 5.1.5 =
* Fixed: Enqueue styles prefix and suffix.
* Improved: Fix the edit group problem with space in group name.
* Updated: Database tables field.
* Updated: Experttexting gateway.
* Minor improvements.

= 5.1.4 =
* Added: System info page to get more information for debugging.
* Improved: Check credits.

= 5.1.3 =
* Minor improvements.

= 5.1.2 =
* Add: Alchemymarketinggm.com - Africa gateway.
* Update: dot4all.it gateway now available on free version.
* Update: gatewayapi.com to support Unicode.
* Improved: Response status and Credits to do not save result if is object.
* Fixed & Improvement: Gateways: 18sms, abrestan ,adspanel, asr3sms, avalpayam, bandarsms, candoosms, iransmspanel, joghataysms, mdpanel, mydnspanel, nasrpayam, payamakalmas, ponishasms, sadat24, smsde, smshooshmand, smsmaster, smsmelli, smsservice, suresms, torpedos, yashilsms, smsglobal

= 5.1.1 =
* Optimized: The main structure of the plugin and split process to increase performance and load.
* Updated: primotexto.com to allow multiple number sending.
* Fixed: loading menu pages content on different languages.
* Fixed: send SMS form style with some other plugins.
* Fixed: websms.com.cy, textplode.com, 0098sms.com, 18sms.ir, 500sms.ir, ebulksms.com gateways.

= 5.1 =
* Added: Collapse for toggle the visibility of response column on Outbox table.
* Added: A new template function for sending SMS `wp_sms_send( $to, $msg, $is_flash = false )`.
* Improved: Primotexto.com gateway.
* Fixed: Issue in Textplode.com gateway.
* Fixed: Issue in WooCommerce class for sending SMS.

= 5.0 =
* Added: The new option for disabling CSS loading the theme.
* Added: A new tab in Settings that for manage SMS Newsletter, the options removed from SMS newsletter widget.
* Added: A new shortcode for show SMS newsletter form. enjoy with this shortcode `[wp-sms-subscriber-form]`.
* Added: A new option for sending an SMS to the Author of the post when that post publish and the author have not the publish capability.
* Added: Status and Response columns in outbox page and get the full log on sending SMS actions.
* Added: Support mobile fields with International Telephone Input optional.
* Added: Zain.im Gateway.
* Added: Textmagic.com Gateway.
* Optimized: The main structure of the plugin and split process to increase performance and load.
* Improved: The SMS newsletter requests. We've used the WP REST API instead Admin Ajax.
* Improved: Now you can allow to importing the same number into two different groups.
* Improved: The styles of admin forms, We've used the ThickBox for managing forms and metabox style for send SMS form.
* Improved: Some queries to get data.
* Improved: The export subscriber issue.
* Updated: The SMS Global gateway to the latest version of API.
* Updated: Primotexto gateway.
* Updated: Message type for gateway Comilio.it.

= 4.1.2 =
* Compatible with v5.0

= 4.1.1 =
* Fixed: Issue to saving options.
* Added: Ignore duplicate subscribers if that exist to another group in the import page.
* Added: Aradpayamak.net gateway.
* Updated: The styles of admin forms.

= 4.1 =
* Added: a new checkbox in the SMS subscription form for GDPR compliance.
* Added: Privacy menu in the plugin for Import & Export the user data for GDPR compliance. read [the blog post](https://wp-sms-pro.com/gdpr-compliant-in-wp-sms/) to get more information.
* Added: SMS Sending feature to different roles in Send SMS Page.
* Added: mobiledot.net.sa and smsnation.co.rw gateways.
* Added: multi-site support in WordPress Network.
* Updated: fortytwo.com, idehpayam.com, onlinepanel.ir and mobile.net.sa gateways
* Updated: the setting page.
* Disabled `applyUnicode` hood by default
* Fixed: the issue of receiving fields from Gravityforms.

= 4.0.21 =
* Added: engy.solutions and aruba.it and hiro-sms.com gateways.
* Added: new option for sending Unicode for non-English characters (such as Persian, Arabic, Chinese or Cyrillic characters).
* Added: sender ID field. Allow typing of sender ID in Admin Send SMS page.
* Fixed: issue to send SMS through CF7 in PHP v7.0 and v7.1

= 4.0.20 =
* Added: country code to prefix numbers if this option has value on the setting page.
* Updated: setting page. Added options for [Awesome Support plugin](https://wordpress.org/plugins/awesome-support/).

= 4.0.19 =
* Added: tripadasmsbox.com, suresms.com, verimor.com.tr gateway.

= 4.0.18 =
* Added: Uwaziimobile.com and cpsms.dk Gateway.
* Updated: settings page fields.

= 4.0.17 =
* IMPORTANT: Updated the domain name of the Plugin website to wp-sms-pro.com

= 4.0.16 =
* Added: Send SMS to multi numbers in the Contact Form 7.
* Added: Several gateways. (Comilio.it, Mensatek.com, Infodomain.asia, Smsc.ua and Mobtexting.com)
* Fixed: Show time items in the outbox SMS.

= 4.0.15 =
* Updated: option fields.
* Added: sabanovin.com gateway.

= 4.0.14 =
* Updated: setting page styles.
* Disabled gateway key field if not available in the current gateway.
* Fixed: issue in `text_callback` method on the options library. Used `isset` to skip undefined error.

= 4.0.13 =
* Added: default variable for `sender_id` in the Gateway class.
* Added: textanywhere.net, abrestan.com and eshare.com Gateway.
* Updated: Pro Package options. (Added WP-Job-Manager fields).

= 4.0.12 =
* Added: Experttexting.com Gateway.
* Added: Spirius.com Gateway.
* Added: Msgwow.com Gateway.
* Updated: NuSoap library. Compatible with PHP 5.4 - 7.1
* Removed: local translations and moved to [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-sms).
* Fixed: issue in cf7 form when the fields has array data.

= 4.0.11 =
* Added: EbulkSMS Africa Gateway.
* Add option for hide account balance in send SMS page.
* Updated: UI for send SMS page.
* Updated: afilnet.com gateway
* Updated: smsgatewayhub.com gateway
* Updated: Asanak gateway
* Fixed: issue in importer library. The split() deprecated and used preg_split().

= 4.0.10 =
* WordPress 4.8 compatibility
* Updated: unisender gateway
* Added: smsozone.com gateway
* Added: Character Count in the send sms page

= 4.0.9 =
* Fixes issues in some gateways
* Supported gatewayapi.com, primotexto.com, 18sms.ir in the gateways list
* Removed: auto text direction script admin send message

= 4.0.8 =
* Fixes undefined error in pro package setting page
* Fixes and improvements newsletter widget
* Added: new feature in sms newsletter. the subscribers can submit their mobile in multi groups
* Added: several gateways (kavenegar.com, itfisms.com, pridesms.in, torpedos.top, resalaty.com)

= 4.0.7 =
* Added: websms.com.cy gateway
* Added: smsgatewayhub.com gateway
* Added: africastalking.com gateway
* Added: variable data to EDD message option
* Fixed: unisender gateway issue
* Fixed: duplicate send sms in the notification post

= 4.0.6 =
* Improvement: plugin to initial the gateways
* Updated: German translations. [Thanks Robert Skiba](skibamedia.de)
* Added: asr3sms.com gateway

= 4.0.5 =
* Fixed: path to the nusoap class on some gateways [Thanks nekofar](https://github.com/nekofar)
* Fixed: send sms time in database
* Fixes including gateways class error when the class was not in the plugin

= 4.0.4 =
* Fixes dissabled options.

= 4.0.3 =
* Supported WP REST API
* Improvements settings page and used main plugin for settings handler
* Updated: arabic translations. (Thanks Hammad)

= 4.0.2 =
* PHP 7.1 compatibility
* Added: mobile number checker in register and update profile page for avoid to duplicate mobile numbers
* Added: `post_content` to the post notification input data. (Supported until 10 words due for restrictions in some gateways)
* Changed `title_post`, `url_post`, `date_post` to `post_title`, `post_url`, `post_date` on post notification input data.
* Fixed: Spelling mistakes in language file.

= 4.0.1 =
* Fixed: default gateway issue.
* Fixed: Illegal error in cf7 sms meta box.

= 4.0 =
* Important! Please keep your gateway information before updating/installing (username, password or anything). Because in this version used a new setting page.
* Added: setting class for all options in the plugin for better settings performance.
* Added: new classes for doing any proccess.
* Added: `resalaty.com` gateway.
* Added: return request in the gateway tab on the option page for get any message of the request.
* Added: `WP_Error` in the all gateway classes.
* Added: ‌Bulk send status in the gateway tabs on the setting page.
* Added: response gateway message after sending sms on the sending page.
* Removed: newsletter tabs from option page and moved all option on the newsletter widget.
* Improvement: options page and removed all notice errors in setting page.
* Improvement: all syntax for notice errors.
* Improvement: main class.
* Fixed: load template widget in admin.
* Fixed: widget plugin name (Important! after update, re-add `SMS newsletter form` widget in your theme)
* Fixed: notice error in `cf7` editor panel, used `id()` method instead.
* Removed: function: `wp_subscribes`.

= 3.2.4 =
* Compatible with WP 4.7
* Fixes issue when enable plugin to add new cap.
* Fixes issue (Missing `$this->validateNumber` on the default gateway class)
* Fixes issue (Missing `$user->ID` in mobile field when create new user)
* Improvement: structure files, folders and cleaning codes.

= 3.2.3 =
* Language french added. thanks `yves.le.bouffant@libertysurf.fr`

= 3.2.3 =
* Added: fortytwo.com gateway.
* Added: parsgreen (api.ir) gateway.
* Compatible up to wordpress 4.6
* Fixes Undefined index error in plugin pages
* Updated: textplode gateway.

= 3.2.2 =
* Added: new gateway (springedge.com)
* Added: new gateway (textplode.com)
* Added: new gateway (textplode.com)
* Language (Brazil) updated.

= 3.2.1 =
* Added: New gateway (sonoratecnologia.com.br).
* Removed: dashicons from `WP_List_Table`.

= 3.2 =
* Added: New capabilities: `wpsms_sendsms`, `wpsms_outbox`, `wpsms_subscribers`, `wpsms_subscribe_groups` and `wpsms_setting` to user roles for manage page access.
* Added: New filters `wp_sms_from`, `wp_sms_to`, `wp_sms_msg` in the plugin.
* Added: New gateway (bulutfon.com).
* Added: New gateway (iransms.co).
* Added: New gateway (arkapayamak.ir).
* Added: New gateway (chaparpanel.ir).
* Fixed: issue when you rename `wp-content` folder. now plugin it's work if the folder name does not `wp-content`.
* Fixed: `Undefined index` errors in ths plugin when wordpress debug is enable.
* Fixed: Issue in outbox, subscribe and group page after bulk edit.
* Updated: `http` to `https` link in gateway.sa gateway.
* Updated: Language file and any string in the plugin.
* Renamed `wp_after_sms_gateway` action to `wp_sms_after_gateway`.
* Renamed `wps_add_subscriber` action to `wp_sms_add_subscriber`.
* Renamed `wps_delete_subscriber` action to `wp_sms_delete_subscriber`.
* Renamed `wps_update_subscriber` action to `wp_sms_update_subscriber`.
* Renamed `wps_add_group` action to `wp_sms_add_group`.
* Renamed `wps_delete_group` action to `wp_sms_delete_group`.
* Renamed `wps_update_group` action to `wp_sms_update_group`.
* Removed: select access option in settig page.
* Removed: `Hook` method from `WP_SMS` class and used `do_action` for gateways class.
* Removed: gateway message in `wp-admin`.
* Removed: Suggestion sms from plugin (Because of the inefficiency).

= 3.1.3 =
* Compatible with wordpress 4.5
* Gateway smsline.ir Added.

= 3.1.2 =
* Gateway gateway.sa Added.
* Gateway modiranweb.net Added.
* Fixed: empty value in cf7 option.
* Fixed: Subscribe url and credit url in dashboard glance.

= 3.1.1 =
* Language `German` updated. (Thanks Robert Skiba Medientechnik)
* Fixed: activation code for SMS newsletter.
* Fixed: Showing SMS tab in CF7 Meta box.
* Gateway `esms24.ir` Added.
* Gateway `payamakaria.ir` Added.
* Gateway `tgfsms.ir` Added.
* Gateway `pichakhost.com Added.
* Gateway `tsms.ir Added.
* Gateway `parsasms.com Added.

= 3.1.0 =
* Gateway `Bestit.co` Added.
* Gateway `Pegah-Payamak.ir` Added.
* Gateway `Loginpanel.ir` Added.
* Gateway `Adspanel.ir` Added.
* Gateway `Adspanel.ir` Added.
* Gateway `Mydnspanel.com` Added.
* Fixed: Update option on notification page.
* Language `Arabic` updated. (Thanks Hammad)

= 3.0.2 =
* Gateway `LabsMobile` updated.
* Gateway `Mtarget` updated.
* Gateway `Razpayamak` Added.
* Added: select status in edit subscribe page.
* Fixed: send to subscribes in Send SMS page.
* Fixed: send notification new post to subscribers.
* Fixed: custom text for notifications new post.

= 3.0.1 =
* Fixed: show group page and subscribe page on admin.
* Language: Swedish added. (Thanks Kramfors)

= 3.0 =
* Added: `WP_SMS_Subscriptions` class for processing subscribers (just in admin).
* Added: `Default_Gateway` class for use it if webservice not active in the plugin.
* Added: check sms credit in `SendSMS` method.
* Added: subscribers hook to plugin hooks collactions.
* Added: user to newsletter when the user register in the wordpress.
* Added: send message to form field on `Contact Form 7`.
* Added: manage subscribe group in the plugin menu.
* Added: access level for view send page sms.
* Added: show/hide group in subscribe form.
* Added: resend sms on outbox message.
* Added: custom message for sms post suggestion.
* Added: css file for sms post suggestion form.
* Added: select group in sms meta box for sending the sms to subscribers when publish new post.
* Added: note for gateways after web services list.
* Added: `smsapi.pl` polish gateway.
* Added: `wifisms.ir` iranian gateway.
* Improvement: list table in for subscriber and outbox page (use `WP_List_Table` library).
* Improvement: notifications page.
* Fixed: notification new comment conflict with woocommerce.
* Compatible with wordpress 4.3.
* Language: updated.
* Reseted all notifications option.
* Remove add-ons page.
* Remove wordpress pointer from plugin.
* Remove admin stylesheet old version.

= 2.8.1 =
* Added: Sarinapayamak.com webservice
* Added: mtarget.fr webservice
* Added: bearsms.com webservice
* Added: smss.co.il webservice
* Added: sms77.de webservice
* Added: isms.ir webservice
* Fixed: Notification sms after enable plugin
* Fixed: Integration with new ver of CF7
* Update Arabic translation.
* Added: German translation. [Thanks Robert Skiba](http://skibamedia.de/)

= 2.8 =
* Added: rules on mobile field number for subscribe form. (maximum and minimum number)
* Added: place holder on mobile filed number for subscribe form for help to user.
* Added: Chinese translator. (Thanks Jack Chen)
* Added: Addons page in plugin.
* Added: payamgah.net webservice.
* Added: sabasms.biz webservice.
* Added: chapargah.ir webservice.
* Added: farapayamak.com webservice.
* Added: yashil-sms.ir webservice.
* Improved subscribe ajax form.
* Improved subscribe form and changed the form design.
* Fixed: a problem in send post to subscribers.

= 2.7.4 =
* Fixed: Contact form 7 shortcode. currently supported.

= 2.7.3 =
* Added: smshosting.it webservice.
* Added: afilnet.com webservice.
* Added: faraed.com webservice.
* Added: spadsms.ir webservice.
* Added: niazpardaz.com (New webservice).
* Added: bandarsms.ir webservice.

= 2.7.2 =
* Added: MarkazPayamak.ir webservice.
* Added: payamak-panel.com webservice.
* Added: barmanpayamak.ir webservice.
* Added: farazpayam.com webservice.
* Added: 0098sms.com webservice.
* Added: amansoft.ir webservice.
* Change webservice in asanak.ir webservice.

= 2.7.1 =
* Added: Variables %status% and %order_name% for woocommerce new order.
* Added: smsservice.ir webservice.
* Added: asanak.ir webservice.
* Updated: idehpayam Webservice.
* Added: Mobile field number in create a new user from admin.
- Fixed notification sms when create a new user.
* Fixed: return credit in smsglobal webservice.

= 2.7 =
* Added: Numbers of Wordpress Users to send sms page.
* Added: Mobile validate number to class plugin.
* Added: Option for Disable/Enable credit account in admin menu.
* Added: afe.ir webservice.
* Added: smshooshmand.com webservice.
* Added: Description field optino for subscribe form widget.
* Included username & password field for reset button in webservice tab.
* Updated: Widget code now adhears to WordPress standards.

= 2.6.7 =
* Added: navid-soft web service.
* Remove number_format in show credit sms.

= 2.6.6 =
* Fixed: problem in include files.

= 2.6.5 =
* Added: smsroo.ir web service.
* Added: smsban.ir web service.

= 2.6.4 =
* Fixed: nusoap_client issue when include this class with other plugins.
* Remove mobile country code from tell friend section.
* Change folder and files structure plugin.

= 2.6.3 =
* Added: SMS.ir (new version) web service.
* Added: Smsmelli.com (new version) web service.
* Fixed: sms items in posted sms page.
* Fixed: subscribe items in subscribe page.
* Fixed: Mobile validation number.
* Fixed: Warning error when export subscribers.
* Changed rial unit to credit.

= 2.6.2 =
* Fixed: Notifications sms to subscribes.
* Added: Rayanbit.net web service.
* Added: Danish language.

= 2.6.1 =
* Fixed: Mobile validation in subscribe form.
* Added: Reset button for remove web service data.
* Added: Melipayaamak web service.
* Added: Postgah web service.
* Added: Smsfa web service.
* Added: Turkish language.

= 2.6 =
* Fixed: database error for exists table.
* Fixed: small bugs.
* Added: chosen javascript library to plugin.
* Added: ssmss.ir Webservice.
* Added: isun.company Webservice.
* Added: idehpayam.com Webservice.
* Added: smsarak.ir Webservice.
* Added: difaan Webservice.
* Added: Novinpayamak Webservice.
* Added: Dot4all Webservice.

= 2.5.4 =
* Added: sms-gateway.at Webservice.
* Added: Spanish language.
* Updated: for WordPress 4.0 release.

= 2.5.3 =
* Added: Smstoos Webservice.
* Added: Smsmaster Webservice.
* Fixed: Showing sms credit in adminbar. Not be displayed for the users.
* Fixed: Send sms for subscriber when publish new posts.

= 2.5.2 =
* Added: Avalpayam Webservice.
* Fixed: bugs in database queries.

= 2.5.1 =
* Added: Option to add mobile field in register form.
* Added: Welcome message for new user.
* Added: Matin SMS Webservice.
* Added: Iranspk Webservice.
* Added: Freepayamak Webservice.
* Added: IT Payamak Webservice.
* Added: Irsmsland Webservice.
* Fixed: Error `Call to a member function GetCredit()` in webservie page.
* Fixed: Bug in notification register new user.
* Updated: Arabic language.

= 2.5 =
* Fixed: Error `Call to undefined method stdClass::SendSMS()` when enable/update plugin.
* Added: Option to enable mobile field to profile page. (Setting -> Features)
* Added: Import & export in subscribe list page.
* Added: Groups link in subscribe page.
* Added: Search items in subscribe list page.
* Added: Novin sms Webservice.
* Added: Hamyaar sms Webservice.

= 2.4.2 =
* Added: SMSde Webservice.

= 2.4.1 =
* Added: Payamakalmas Webservice.
* Added: SMS (IPE) Webservice.
* Added: Popaksms Webservice.

= 2.4 =
* Added: `WP_SMS` Class and placing a parent class.
* Added: `wp_sms_send` Action when Send sms from the plugin.
* Added: `wp_sms_subscribe` Action when register a new subscriber.
* Added: Notification SMS when registering a new subscribe.
* Added: Ponisha SMS Webservice.
* Added: SMS Credit and total subscriber in At a Glance.
* Fixed: Saved sms sender with `InsertToDB` method.
* Optimized: Subscribe SMS ajax form.

= 2.3.5 =
* Updated: ippanel.com Webservice.
* Added: Sarab SMS Webservice.

= 2.3.4 =
* Updated: Opilio Webservice.
* Added: Sharif Pardazan (2345.ir) Webservice.

= 2.3.3 =
* Added: Asia Payamak Webservice.

= 2.3.2 =
* Added: Arad SMS Webservice.

= 2.3.1 =
* Added: Notification SMS when get new order from Woocommerce plugin.
* Added: Notification SMS when get new order from Easy Digital Downloads plugin.

= 2.3 =
* Added: Tabs option in setting page.
* Added: Notification SMS when registering a new username.
* Added: Notification SMS when get new comment.
* Added: Notification SMS when username login.
* Added: Text format to published new post notification.
* Added: MP Panel Webservice.
* Added: Mediana Webservice.

= 2.2.5 =
* Changed: Aadat 24 web service.
* Changed: Parand Host web service URL.

= 2.2.4 =
* Added: Adpdigital Webservice.
* Added: Joghataysms Webservice.
* Fixed: Iransmspanel webservice.
* Changed: Parand Host web service URL.
* Changed: Hi SMS web service URL.
* Changed: Nasrpayam web service URL.

= 2.2.2 =
* Added: Hi SMS Webservice.

= 2.2.1 =
* Added: Niazpardaz Webservice.
* Fixed: Oplio Webservice.

= 2.2 =
* Added: Payameroz Webservice.
* Added: Unisender Webservice.
* Fixed: small bug in cf7.

= 2.1 =
* Resolved: include tell-a-freind.php file.

= 2.0 =
* Added: Metabox sms to Contact Form 7 plugin.
* Added: SMS Message sender page.
* Added: PayamResan Webservice.
* Optimized: include files.
* Resolved: create tables when install plugin.
* Language: updated.

= 2.0.2 =
* Resolved: loading image.
* Added: Fayasms Webservice.

= 2.0.1 =
* Added: SMS Bartar Webservice.

= 2.0 =
* Added: Pagination in Subscribes Newsletter page.
* Added: Group for Subscribes.
* Optimized: jQuery Calling.
* Resolved: Subscribe widget form.
* Resolved: Small problems.

= 1.9.22 =
* Added: Nasrpayam Webservice.

= 1.9.21 =
* Added: Caffeweb Webservice.

= 1.9.20 =
* Resolved: add subscriber in from Wordpress Admin->Newsletter subscriber.
* Added: TCIsms Webservice.

= 1.9.19 =
* Added: ImenCms Webservice.

= 1.9.18 =
* Added: Textsms Webservice.

= 1.9.17 =
* Added: Smsmart Webservice.

= 1.9.16 =
* Added: PayamakNet Webservice.

= 1.9.15 =
* Added: BarzinSMS Webservice.
* Update: jQuery to 1.9.1.

= 1.9.14 =
* Resolved: opilo Webservice.

= 1.9.13 =
* Resolved: paaz Webservice.

= 1.9.12 =
* Added: JahanPayamak Webservice.

= 1.9.11 =
* Added: SMS-S Webservice.
* Added: SMSGlobal Webservice.
* Added: paaz Webservice.
* Added: CSS file in setting page.
* Resolved: Loads the plugin's translated strings problem.
* Language: updated.

= 1.9.10 =
* Added: Tablighsmsi Webservice.

= 1.9.9 =
* Added: Smscall Webservice.

= 1.9.8 =
* Added: Smsdehi Webservice.

= 1.9.7 =
* Added: Sadat24 Webservice.

= 1.9.6 =
* Added: Arabic language.
* Added: Notification SMS when messages received from Contact Form 7 plugin.
* Small changes in editing Subscribers.

= 1.9.5 =
* Added: Ariaideh Web Service.

= 1.9.4 =
* Added: Persian SMS Web Service.

= 1.9.3 =
* Added: SMS Click Web Service.

= 1.9.2 =
* Added: ParandHost Web Service.
* Troubleshooting jQuery in Send SMS page.

= 1.9.1 =
* Added: PayameAvval Web Service.

= 1.9 =
* Added: SMSFa Web Service.
* Optimize in translation functions.
* Added: edit subscribers.

= 1.8 =
* Added: your mobile number.
* Added: Enable/Disable calling jQuery in wordpress.
* Added: Notification of a new wordPress version by SMS.

= 1.7.1 =
* Fix a problem in jquery.

= 1.7 =
* Fix a problem in Get credit method.
* Fix a problem in ALTER TABLE.
* Fix a problem Active/Deactive all subscribe.

= 1.6 =
* Added: Enable/Disable username in subscribe page.
* Fix a problem in show credit.
* Fix a problem in menu link.
* Fix a problem in word counter.

= 1.5 =
* Added: Hostiran Web Service.
* Added: Iran SMS Panel Web Service.
* Remove Orangesms Service.
* Added: Activation subscribe.
* Optimize plugin.
* Update jquery to 1.7.2

= 1.4 =
* Added: Portuguese language.
* Update last credit when send sms page.

= 1.3.3 =
* Fix a problem.
* Fix a display the correct number in the list of newsletter subscribers.

= 1.3.2 =
* Fix a problem.

= 1.3.1 =
* Fix a problem.
* Fix credit unit in multi language.

= 1.3 =
* Added: register link for webservice.
* Added: Suggestion post by SMS.

= 1.2 =
* Fix a problem.

= 1.1 =
* Adding show SMS credit in the dashboard right now.
* Adding show total subscribers in the dashboard right now.
* Adding Shortcode.
* Added: Panizsms Web Service.
* Added: Orangesms Web Service.

= 1.0 =
* Start plugin
