=== WP SMS ===
Contributors: mostafa.s1990
Donate link: http://wp-sms-pro.com/donate
Tags: sms, wordpress, send, subscribe, message, register, notification, webservice, sms panel, woocommerce, subscribes-sms, EDD, twilio, bulksms, clockworksms, nexmo
Requires at least: 3.0
Tested up to: 4.9
Stable tag: 4.0.19
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A powerful texting plugin for WordPress

== Description ==
You can add to WordPress, the ability to send SMS, member of SMS newsletter and send the SMS.
To every happening in WordPress, you can send an SMS through this plugin.

The usage of this plugin is completely free. You have to just have an account from a service in the gateway list's that we support them.
Don't worry, we have tried to add the best and the most gateways to plugin.

= Send SMS with WordPress in less than 1 minute! =

https://www.youtube.com/watch?v=50Sv5t6wTrQ

= Features =

* Supported +150 sms gateways.
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

= Professional Package =
In the Professional pack added many features, most popular gateway and is integrated with another plugins.

[Buy Professional Package](http://wp-sms-pro.com/purchase/)


= Translations =
WP SMS has been translated in to many languages, for the current list and contributors, please visit the [translate page](https://translate.wordpress.org/projects/wp-plugins/wp-sms).

Translations are done by people just like you, help make WP SMS available to more people around the world and [do a translation](http://wp-sms-pro.com/localization/) today!

= Contributing and Reporting Bugs =
WP-SMS is being developed on GitHub, If you’re interested in contributing to plugin, Please look at [Github page](https://github.com/veronalabs/wp-sms)

= Support =
* [Donate to this plugin](http://mostafa-soufi.ir/donate/)

This plugin is a free product of the [Verona Labs](http://veronalabs.com/) and Thank you [jetbrains](https://www.jetbrains.com) for giving us Intellij IDEA Ultimate licenses for develop this project.

== Installation ==
1. Upload `wp-sms` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. To display Subscribe go to Themes -> Widgets, and adding `Subscribe to SMS` into your sidebar.

== Frequently Asked Questions ==
= What is the gateways supported in the plugin? =
You can see list all supported gateways [through this link](https://github.com/veronalabs/wp-sms/tree/master/includes/gateways)

= What is the difference between Free and Pro Pack version? =
In the Professional pack added many features, most popular gateway and is integrated with another plugins.

= What is the gateways supported in the Pro Pack version? =
The professional package supported following gateways:

* Twilio.com
* Plivo.com
* Clickatell.com
* Bulksms.com
* Infobip.com
* Nexmo.com
* Clockworksms.com
* Messagebird.com
* Clicksend.com
* Smsapi.com
* Dsms.in
* Esms.vn
* Isms.com.my
* Sms4marketing.it
* Magicdeal4u.com
* Mobily.ws
* Moceansms.com
* Msg91.com
* Msg360.in
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

= What is the features of pro pack version? =
* Verify user registration by sms verification code
* Integrate with BuddyPress. You can adding mobile number field to profile page, send SMS to user when mentioned in the post and sending SMS to user when posted a comment on the post.
* Integrate with WooCommerce. You can adding mobile number field to checkout page, send sms to users or subscribers when added new product to woocommerce. send SMS to admin when submitted new order in woocommerce and send sms to customers when submit the order.
when one stock product is load, the plugin can send SMS to admin for notify and you can send sms to customers when the orders have been changed.
* Integrate with Gravity form. Plugin can be send sms to user or admin after submit the form.
* Integrate with Quform. Plugin can be send sms to user or admin after submit the form.
* Integrate with Easy Digital Downloads. You can adding mobile number field to profile page. can be send sms to user or admin when submitted an order with EDD.
* Integrate with WP Job Manager. You can adding mobile number field to Job form and can be send sms to employer or admin when submitted an job with WP Job Manager.
* Premium Support.

= How to buy? =
You can buy pro pack version [through this link](http://wp-sms-pro.com/purchase/)

= PHP 7 Support? =
Yes! WP SMS is compatible with PHP 7 and 7.1

= How sending sms with PHP code? =

	global $sms;
	$sms->to = array('Mobile Number');
	$sms->msg = "Your Message";
	$sms->SendSMS();

= How using Actions? =
Run following action when send sms with this plugin.
`wp_sms_send`

Example: Send mail when send sms.

	function send_mail_when_send_sms($message_info) {
		wp_mail('you@mail.com', 'Send SMS', $message_info);
	}
	add_action('wp_sms_send', 'send_mail_when_send_sms');

Run following action when subscribe a new user.
`wp_sms_add_subscriber`

Example: Send sms to user when register a new subscriber.

	function send_sms_when_subscribe_new_user($name, $mobile) {
		global $sms;
		$sms->to = array($mobile);
		$sms->msg = "Hi {$name}, Thanks for subscribe.";
		$sms->SendSMS();
	}
	add_action('wp_sms_add_subscriber', 'send_sms_when_subscribe_new_user', 10, 2);`

= How using Hooks? =
You can use following filter for modify from number.
`wp_sms_from`

Example: Add 0 to the end sender number.

	function wp_sms_modify_from($from) {
		$from = $from . ' 0';
		return $val;
	}
	add_filter('wp_sms_from', 'wp_sms_modify_from');

You can use following filter for modify receivers number.
`wp_sms_to`

Example: Add new number to get message.

	function wp_sms_modify_receiver($numbers) {
		$numbers[] = '09xxxxxxxx';
		return $numbers;
	}
	add_filter('wp_sms_to', 'wp_sms_modify_receiver');

You can use following filter for modify text message.
`wp_sms_msg`

Example: Add signature to messages that are sent.

	function wp_sms_modify_message($message) {
		$message = $message . ' /n Powerby: WP-SMS';
		return $message;
	}
	add_filter('wp_sms_msg', 'wp_sms_modify_message');

= Support REST API? =
Yes. At the moment just we registered one endpoint in the plugin.

Add new subscribe to sms newsletter

	POST /wpsms/v1/subscriber/add

== Screenshots ==
1. Gateway configuration.
2. Features page.
3. Notifications page.
4. Integrations page.
5. Send SMS Page.
6. Outbox SMS Page.
7. Subscribers Page.
8. At a Glance.
9. SMS Newsletter widget.
10. Send post to subscribers.
11. Contact Form 7 notifications.

== Upgrade Notice ==
= 4.0.0 =
* IMPORTANT! Please keep your gateway information before updating/installing (username, password or anything). Because in this version used a new setting page.

= 3.0 =
* CHANGED In this version of the plugin has changed the structure and optimized codes.
In this version, we have made a lot of changes. We tried using the free version as a core and base. The professional version of plugin is made to a professional parcel that through the free plugin is activated.

= 2.4 =
* CHANGED `$obj` variable TO `$sms` IN YOUR SOURCE CODE.

= 2.0 =
* BACKUP YOUR DATABASE BEFORE INSTALLING!

== Changelog ==
= dev-master =
* Added country code to prefix numbers if this option has value on the setting page.
* Updated setting page. Added options for Awesome Support plugin.

= 4.0.19 =
* Added tripadasmsbox.com, suresms.com, verimor.com.tr gateway.

= 4.0.18 =
* Added Uwaziimobile.com and cpsms.dk Gateway.
* Updated settings page fields.

= 4.0.17 =
* IMPORTANT: Updated the domain name of the Plugin website to wp-sms-pro.com

= 4.0.16 =
* Added Send SMS to multi numbers in the Contact Form 7.
* Added Several gateways. (Comilio.it, Mensatek.com, Infodomain.asia, Smsc.ua and Mobtexting.com)
* Fixed Show time items in the outbox SMS.

= 4.0.15 =
* Updated option fields.
* Added sabanovin.com gateway.

= 4.0.14 =
* Updated setting page styles.
* Disabled gateway key field if not available in the current gateway.
* Fixed issue in `text_callback` method on the options library. Used `isset` to skip undefined error.

= 4.0.13 =
* Added default variable for `sender_id` in the Gateway class.
* Added textanywhere.net, abrestan.com and eshare.com Gateway.
* Updated Pro Package options. (Added WP-Job-Manager fields).

= 4.0.12 =
* Added Experttexting.com Gateway.
* Added Spirius.com Gateway.
* Added Msgwow.com Gateway.
* Updated NuSoap library. Compatible with PHP 5.4 - 7.1
* Removed local translations and moved to [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-sms).
* Fixed issue in cf7 form when the fields has array data.

= 4.0.11 =
* Added EbulkSMS Africa Gateway.
* Add option for hide account balance in send SMS page.
* Updated UI for send SMS page.
* Updated afilnet.com gateway
* Updated smsgatewayhub.com gateway
* Updated Asanak gateway
* Fixed issue in importer library. The split() deprecated and used preg_split().

= 4.0.10 =
* WordPress 4.8 compatibility
* Updated unisender gateway
* Added smsozone.com gateway
* Added Character Count in the send sms page

= 4.0.9 =
* Fixes issues in some gateways
* Supported gatewayapi.com, primotexto.com, 18sms.ir in the gateways list
* Removed auto text direction script admin send message

= 4.0.8 =
* Fixes undefined error in pro package setting page
* Fixes and improvements newsletter widget
* Added new feature in sms newsletter. the subscribers can submit their mobile in multi groups
* Added several gateways (kavenegar.com, itfisms.com, pridesms.in, torpedos.top, resalaty.com)

= 4.0.7 =
* Added websms.com.cy gateway
* Added smsgatewayhub.com gateway
* Added africastalking.com gateway
* Added variable data to EDD message option
* Fixed unisender gateway issue
* Fixed duplicate send sms in the notification post

= 4.0.6 =
* Improvement plugin to initial the gateways
* Updated German translations. [Thanks Robert Skiba](skibamedia.de)
* Added asr3sms.com gateway

= 4.0.5 =
* Fixed path to the nusoap class on some gateways [Thanks nekofar](https://github.com/nekofar)
* Fixed send sms time in database
* Fixes including gateways class error when the class was not in the plugin

= 4.0.4 =
* Fixes dissabled options.

= 4.0.3 =
* Supported WP REST API
* Improvements settings page and used main plugin for settings handler
* Updated arabic translations. (Thanks Hammad)

= 4.0.2 =
* PHP 7.1 compatibility
* Added mobile number checker in register and update profile page for avoid to duplicate mobile numbers
* Added `post_content` to the post notification input data. (Supported until 10 words due for restrictions in some gateways)
* Changed `title_post`, `url_post`, `date_post` to `post_title`, `post_url`, `post_date` on post notification input data.
* Fixed Spelling mistakes in language file.

= 4.0.1 =
* Fixed default gateway issue.
* Fixed Illegal error in cf7 sms meta box.

= 4.0.0 =
* Important! Please keep your gateway information before updating/installing (username, password or anything). Because in this version used a new setting page.
* Added setting class for all options in the plugin for better settings performance.
* Added new classes for doing any proccess.
* Added `resalaty.com` gateway.
* Added return request in the gateway tab on the option page for get any message of the request.
* Added `WP_Error` in the all gateway classes.
* Added ‌Bulk send status in the gateway tabs on the setting page.
* Added response gateway message after sending sms on the sending page.
* Removed newsletter tabs from option page and moved all option on the newsletter widget.
* Improvement options page and removed all notice errors in setting page.
* Improvement all syntax for notice errors.
* Improvement main class.
* Fixed load template widget in admin.
* Fixed widget plugin name (Important! after update, re-add `SMS newsletter form` widget in your theme)
* Fixed notice error in `cf7` editor panel, used `id()` method instead.
* Removed function: `wp_subscribes`.

= 3.2.4 =
* Compatible with WP 4.7
* Fixes issue when enable plugin to add new cap.
* Fixes issue (Missing `$this->validateNumber` on the default gateway class)
* Fixes issue (Missing `$user->ID` in mobile field when create new user)
* Improvement structure files, folders and cleaning codes.

= 3.2.3 =
* Language french added. thanks `yves.le.bouffant@libertysurf.fr`

= 3.2.3 =
* Added fortytwo.com gateway.
* Added parsgreen (api.ir) gateway.
* Compatible up to wordpress 4.6
* Fixes Undefined index error in plugin pages
* Updated textplode gateway.

= 3.2.2 =
* Added new gateway (springedge.com)
* Added new gateway (textplode.com)
* Added new gateway (textplode.com)
* Language (Brazil) updated.

= 3.2.1 =
* Added New gateway (sonoratecnologia.com.br).
* Removed dashicons from `WP_List_Table`.

= 3.2 =
* Added New capabilities: `wpsms_sendsms`, `wpsms_outbox`, `wpsms_subscribers`, `wpsms_subscribe_groups` and `wpsms_setting` to user roles for manage page access.
* Added New filters `wp_sms_from`, `wp_sms_to`, `wp_sms_msg` in the plugin.
* Added New gateway (bulutfon.com).
* Added New gateway (iransms.co).
* Added New gateway (arkapayamak.ir).
* Added New gateway (chaparpanel.ir).
* Fixed issue when you rename `wp-content` folder. now plugin it's work if the folder name does not `wp-content`.
* Fixed `Undefined index` errors in ths plugin when wordpress debug is enable.
* Fixed Issue in outbox, subscribe and group page after bulk edit.
* Updated `http` to `https` link in gateway.sa gateway.
* Updated Language file and any string in the plugin.
* Renamed `wp_after_sms_gateway` action to `wp_sms_after_gateway`.
* Renamed `wps_add_subscriber` action to `wp_sms_add_subscriber`.
* Renamed `wps_delete_subscriber` action to `wp_sms_delete_subscriber`.
* Renamed `wps_update_subscriber` action to `wp_sms_update_subscriber`.
* Renamed `wps_add_group` action to `wp_sms_add_group`.
* Renamed `wps_delete_group` action to `wp_sms_delete_group`.
* Renamed `wps_update_group` action to `wp_sms_update_group`.
* Removed select access option in settig page.
* Removed `Hook` method from `WP_SMS` class and used `do_action` for gateways class.
* Removed gateway message in `wp-admin`.
* Removed Suggestion sms from plugin (Because of the inefficiency).

= 3.1.3 =
* Compatible with wordpress 4.5
* Gateway smsline.ir Added.

= 3.1.2 =
* Gateway gateway.sa Added.
* Gateway modiranweb.net Added.
* Fixed empty value in cf7 option.
* Fixed Subscribe url and credit url in dashboard glance.

= 3.1.1 =
* Language `German` updated. (Thanks Robert Skiba Medientechnik)
* Fixed activation code for SMS newsletter.
* Fixed Showing SMS tab in CF7 Meta box.
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
* Fixed Update option on notification page.
* Language `Arabic` updated. (Thanks Hammad)

= 3.0.2 =
* Gateway `LabsMobile` updated.
* Gateway `Mtarget` updated.
* Gateway `Razpayamak` Added.
* Added select status in edit subscribe page.
* Fixed send to subscribes in Send SMS page.
* Fixed send notification new post to subscribers.
* Fixed custom text for notifications new post.

= 3.0.1 =
* Fixed show group page and subscribe page on admin.
* Language: Swedish added. (Thanks Kramfors)

= 3.0 =
* Added `WP_SMS_Subscriptions` class for processing subscribers (just in admin).
* Added `Default_Gateway` class for use it if webservice not active in the plugin.
* Added check sms credit in `SendSMS` method.
* Added subscribers hook to plugin hooks collactions.
* Added user to newsletter when the user register in the wordpress.
* Added send message to form field on `Contact Form 7`.
* Added manage subscribe group in the plugin menu.
* Added access level for view send page sms.
* Added show/hide group in subscribe form.
* Added resend sms on outbox message.
* Added custom message for sms post suggestion.
* Added css file for sms post suggestion form.
* Added select group in sms meta box for sending the sms to subscribers when publish new post.
* Added note for gateways after web services list.
* Added `smsapi.pl` polish gateway.
* Added `wifisms.ir` iranian gateway.
* Improvement list table in for subscriber and outbox page (use `WP_List_Table` library).
* Improvement notifications page.
* Fixed notification new comment conflict with woocommerce.
* Compatible with wordpress 4.3.
* Language: updated.
* Reseted all notifications option.
* Remove add-ons page.
* Remove wordpress pointer from plugin.
* Remove admin stylesheet old version.

= 2.8.1 =
* Added Sarinapayamak.com webservice
* Added mtarget.fr webservice
* Added bearsms.com webservice
* Added smss.co.il webservice
* Added sms77.de webservice
* Added isms.ir webservice
* Fixed Notification sms after enable plugin
* Fixed Integration with new ver of CF7
* Update Arabic translation.
* Added German translation. [Thanks Robert Skiba](http://skibamedia.de/)

= 2.8 =
* Added rules on mobile field number for subscribe form. (maximum and minimum number)
* Added place holder on mobile filed number for subscribe form for help to user.
* Added Chinese translator. (Thanks Jack Chen)
* Added Addons page in plugin.
* Added payamgah.net webservice.
* Added sabasms.biz webservice.
* Added chapargah.ir webservice.
* Added farapayamak.com webservice.
* Added yashil-sms.ir webservice.
* Improved subscribe ajax form.
* Improved subscribe form and changed the form design.
* Fixed a problem in send post to subscribers.

= 2.7.4 =
* Fixed Contact form 7 shortcode. currently supported.

= 2.7.3 =
* Added smshosting.it webservice.
* Added afilnet.com webservice.
* Added faraed.com webservice.
* Added spadsms.ir webservice.
* Added niazpardaz.com (New webservice).
* Added bandarsms.ir webservice.

= 2.7.2 =
* Added MarkazPayamak.ir webservice.
* Added payamak-panel.com webservice.
* Added barmanpayamak.ir webservice.
* Added farazpayam.com webservice.
* Added 0098sms.com webservice.
* Added amansoft.ir webservice.
* Change webservice in asanak.ir webservice.

= 2.7.1 =
* Added Variables %status% and %order_name% for woocommerce new order.
* Added smsservice.ir webservice.
* Added asanak.ir webservice.
* Updated idehpayam Webservice.
* Added Mobile field number in create a new user from admin.
- Fixed notification sms when create a new user.
* Fixed return credit in smsglobal webservice.

= 2.7 =
* Added Numbers of Wordpress Users to send sms page.
* Added Mobile validate number to class plugin.
* Added Option for Disable/Enable credit account in admin menu.
* Added afe.ir webservice.
* Added smshooshmand.com webservice.
* Added Description field optino for subscribe form widget.
* Included username & password field for reset button in webservice tab.
* Updated: Widget code now adhears to WordPress standards.

= 2.6.7 =
* Added navid-soft web service.
* Remove number_format in show credit sms.

= 2.6.6 =
* Fixed problem in include files.

= 2.6.5 =
* Added smsroo.ir web service.
* Added smsban.ir web service.

= 2.6.4 =
* Fixed nusoap_client issue when include this class with other plugins.
* Remove mobile country code from tell friend section.
* Change folder and files structure plugin.

= 2.6.3 =
* Added SMS.ir (new version) web service.
* Added Smsmelli.com (new version) web service.
* Fixed sms items in posted sms page.
* Fixed subscribe items in subscribe page.
* Fixed Mobile validation number.
* Fixed Warning error when export subscribers.
* Changed rial unit to credit.

= 2.6.2 =
* Fixed Notifications sms to subscribes.
* Added Rayanbit.net web service.
* Added Danish language.

= 2.6.1 =
* Fixed Mobile validation in subscribe form.
* Added Reset button for remove web service data.
* Added Melipayaamak web service.
* Added Postgah web service.
* Added Smsfa web service.
* Added Turkish language.

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
* Added Enable/Disable username in subscribe page.
* Fix a problem in show credit.
* Fix a problem in menu link.
* Fix a problem in word counter.

= 1.5 =
* Added: Hostiran Web Service.
* Added: Iran SMS Panel Web Service.
* Remove Orangesms Service.
* Added Activation subscribe.
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
