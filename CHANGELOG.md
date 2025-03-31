v6.9.12 - 2025-03-31
- **New**: Added WhatsApp support for the Tubelight gateway.
- **Enhancement**: Upgraded to the latest version of the Tubelight API.
- **Enhancement**: Updated integration with the MatinSMS gateway.
- **Fix**: Expanded support for a wider range of CSV MIME types for the subscriber import process.
- **Fix**: Removed deprecated/inactive gateways and their associated class files.
- **Fix**: Fixed issue where the welcome message was not being sent to new subscribers.
- **Fix**: Resolved issues with the unsubscribe functionality in the subscription form.

v6.9.11 - 2025-02-25
- **New**: Added support for `%billing_postcode%` and `%payment_method%` placeholders in WooCommerce notifications, and support for variable products in the `%order_item_meta_{key-name}%` placeholder for better order item details.
- **Fix**: Resolved issue with rendering new lines in the Outbox.
- **Fix**: Corrected display of order items in the `%order_items%` variable to show each item on a separate line.
- **Fix**: Fixed 'Not found the number!' error during newsletter subscription confirmation.
- **Enhancement**: Implemented PSR-4 autoloading, replacing manual includes for improved performance and maintainability.

v6.9.10 - 2025-01-22 =
- **New**: Add **Mobile Message Gateway** (Australia).
- **New**: Add **HelloSMS Gateway** (Sweden).
- **Fix**: Resolve issues with adding and updating subscriber groups.
- **Fix**: Address the `_load_textdomain_just_in_time` notice.
- **Fix**: Correct SMS sending to registering users in the login form.
- **Enhancement**: Add capability test for displaying widgets.
- **Enhancement**: Free gateways are now prioritized above pro gateways in the list.

v6.9.9 - 2024-12-23
- **Fix**: Resolved issue with SMS login functionality.
- **Fix**: Corrected support for multiple meta variables and spaces in WooCommerce order variable notifications.
- **Dev** Added new filters for developers:
  - `wp_sms_notification_woocommerce_order_item`
  - `wp_sms_otp_rate_limit_time_interval`
  - `wp_sms_otp_rate_limit_count`

v6.9.8 - 2024-11-24
- **Enhancement**: Updated SMS Point and DirectSend gateways for improved reliability.
- **Enhancement**: Added support for sending messages with multiple templates for gateway SmsGatewayHub.
- **Enhancement**: Refactored Notifications Class; fixed SMS notifications for the latest WordPress version.
- **Enhancement**: Ensured compatibility with PHP 8.3.
- **Enhancement**: Added validation for empty SMS content to ensure compatibility.
- **Fix**: Resolved a translation loading issue to maintain compatibility with WordPress v6.7.

v6.9.7 - 2024-10-27
- **Enhancement**: Ensured full compatibility with WordPress version 6.7.
- **Enhancement**: Improved filters for order item meta to handle array meta values in WooCommerce order notifications.
- **Enhancement**: Improved privacy by hashing usernames during mobile registration and allowing updates to hashed usernames at login.
- **Enhancement**: Added support for the `redirect_to` parameter in mobile login for better redirection handling.
- **Enhancement**: Improved number parsing and validation for mobile numbers.
- **Fix**: Fixed intlTelInput initialization issue in WooCommerce checkout and addressed minor bugs.
- **Fix**: Resolved conflict with mobile number handling with creating new users in the Admin panel.

v6.9.6 - 30.09.2024
* Fix: Resolved the issue with the Subscribe group functionality where arguments were not correctly handled as arrays.
* Fix: Added support for multiple groups in the subscriber functionality.
* Enhancement: Removed unnecessary whitespace from all methods globally to ensure no extra spaces are included in SMS requests sent to gateways.

v6.9.5 - 18.09.2024
* New: Added support for new SMS gateways: SmsPoint.de, 160au, and Gunisms.
* New: Introduced the variable `%order_item_meta_{key-name}%` for WooCommerce Order Notifications, allowing retrieval of meta data from order line items.
* Fix: Fixed the issue with country selection and updated the Intl Tel Input library for better accuracy.
* Improvement: Enhanced performance of incoming message queries in the report widget for faster data retrieval.

v6.9.4 - 21.08.2024
* Enhancement: Refactored: Subscriber request handling.
* Enhancement: Required: `wpsms_subscribers` capability for public REST API endpoints (newsletter/*).
* Enhancement: Updated: Altiria and Kavenegar gateways.
* Enhancement: Improved: Performance of the SMS widget chart on the dashboard.
* Development: Added: Action hook `wp_sms_otp_generated` after OTP generation.

v6.9.3 - 30.07.2024
* Enhancement: Enhanced backward compatibility for recipient handling.
* Enhancement: Improved query for retrieving mobile count per user role.
* Enhancement: Improved the Chat Box styles.
* Enhancement: Updated the Add-Ons page and improved license activation status.
* Fix: Resolved an issue with the check attribute in user profile fields.
* Fix: Fixed Template ID issue in Tubelight Communications gateway.
* Fix: Corrected bugs related to Forminator receiver and conditions.
* Fix: Fixed notice errors in CF7 form management.
* Fix: Fixed the display of the country flag in the subscriber modal.
* Dev: Added filter `wp_sms_sms_otp_length`.

v6.9.2 - 24.06.2024
* New: Added 160.com.au gateway
* Fix: Resolved preferred countries issue in mobile field
* Enhancement: Improved country functionality and performance
* Enhancement: Minor improvements

v6.9.1 - 31.05.2024
* New: Added variable `%unsubscribe_url%` in Subscriber Notification
* Gateway: Updated Cellsynt SMS gateway
* Improvement: Enhanced CSRF check in the unsubscribe URL for non-logged users
* Improvement: Ensured compatibility of Opt-in SMS notification with WooCommerce’s new block-based checkout
* Improvement: Improved mobile field handler compatibility with WooCommerce’s new block-based checkout
* Improvement: Fixed and improved issues and styles of the mobile field international flag field
* Improvement: Implemented minor enhancements

v6.9 - 12.05.2024
* Addition: Introduced a custom gateway feature allowing manual integration with your own SMS gateway. Detailed setup instructions available at [Custom SMS Gateway Setup Documentation](https://wp-sms-pro.com/resources/custom-sms-gateway-setup-documentation).
* Updates: Support for MTarget, SMSGatewayHub, and Octopush gateways.
* Fixes: Issue with SMS report scheduling not clearing when disabled.
* Fixes: Issue with specific WordPress user roles not receiving new post alerts.
* Fixes: Email report bug that sent emails without data.
* Improvement: Clearing of plugin schedules upon deactivation.
* Improvement: Duplicate number removal before sending SMS.
* Improvement: Mobile number validation and functionality.
* Improvement: Overall performance.

v6.8.1 - 08.04.2024
* Fixes: Admin alerts now display correctly.

v6.8 - 08.04.2024
* Updated: New admin design with easier navigation and useful links.
* Updated: Now supports DLT and manual DLT messages in Fast2Sms.
* Added: `cart_url` and `checkout_url` for WooCommerce coupons.
* Added: Validation for international phone numbers.
* Fixed: Notice Error in class SmsOtp generator.
* Improvement: Simplified datetime function and cookie settings in NuSOAP.
* Improvement: Clarified Advanced Settings text.
* Enhanced: Added security checks and CSRF for outbox and group actions.
* Enhanced: General improvements for better performance.

v6.7.1 - 20.03.2024
* Fixes: Improved visibility of settings page fields.
* Fixes: Addressed JavaScript error handling in Send SMS block.
* Fixes: Resolved errors and optimized performance with Formidable integration and entity issues.
* Improvement: Refactored Oxemis SMS gateway body payload to utilize JSON encoding.
* Improvement: Implemented minor enhancements.

v6.7 - 16.03.2024
* Addition: Integration with [Formidable](https://wordpress.org/plugins/formidable/) and [Forminator](https://wordpress.org/plugins/forminator/) now includes SMS notifications for enhanced functionality. [More info](https://wp-sms-pro.com/25942/formidable-forms-and-forminator-integration-now-available/)
* Fixes: Addressed issue with retrieving incorrect variables from admin JavaScript.
* Improvement: Made minor improvements and optimizations for better performance.

v6.6.3 - 13.03.2024
* Addition: Introduced Integration menu; now integrations are conveniently placed under this menu for enhanced accessibility.
* Improvement: Hardened plugin security and improvements by adding CSRF (nonce) for create and update subscribers and groups.
* Development: Added filter `wp_sms_chatbox_contact_link` for advanced customization options.

v6.6.2 - 12.03.2024
* Improvement: Refinements to Chatbox styles and issue resolution.
* Improvement: Unified asset loading, frontend styles, and contact administration into single CSS and JS files.
* Improvement: Hardened plugin security and improvements.
* Improvement: Optimized performance with minor improvements.

v6.6.1 - 08.03.2024
* Improvement: Replaced `move_uploaded_file` with `wp_handle_upload`.
* Improvement: Sanitized and Escaped the rest of the inputs/outputs and hardened plugin security.

v6.6 - 06.03.2024
* Addition: Messaging Button for direct visitor communication via WhatsApp, SMS, and other platforms. [More info](https://wp-sms-pro.com/25841/whats-new-in-wp-sms-6-6-better-faster-and-more-customizable/)
* Improvement: Admin area texts for clearer instructions and settings management.
* Improvement: Various performance tweaks for a smoother plugin experience.
* Development: New `wp_sms_mobile_number_numeric_check` filter for developer-specific number handling customization.

v6.5.5 - 27.02.2024
* Update: Added support for SMS Gateways OzoneSMS and SMSGatewayHub, and included supported encoding for ProSMS.
* Update: Refreshed POT files and strings for better localization.
* Improvement: Resolved Deprecated Dynamic Property Creation issue in WP_SMS\Privacy Class.
* Improvement: Enhanced number normalization logic for better accuracy.
* Improvement: Extended timeout of HTTP requests to 10 seconds for improved reliability.
* Improvement: Implemented minor enhancements for better performance and ensured backward compatibility.

v6.5.4 - 12.02.2024
* Improvement: Responsive admin table lists fixed.
* Improvement: Notifications improved and notice issues fixed.
* Improvement: Enabled early execution of queued tasks.
* Improvement: Duplicate WooCommerce numbers fixed by normalization.

v6.5.3 - 17.01.2024
* Improvement: Improved page sanitization and overall security in `WP_List_Tables`.
* Improvement: Fixed label styling in the subscriber multi-group select field.
* Improvement: Added EuroSms gateway help and improved number display in outbox.
* Improvement: Updated the POT file for better translations.
* Fixes: Display issue with the feedback button in admin area.

v6.5.2 - 10.01.2024
* Fixes: Fixed class not found error by correcting `WP_Error` namespace
* Fixes: Fixed gathering Ultimate Members registration forms data
* Improvement: Escape the mobile place holder and hardened plugin security

v6.5.1 - 28.12.2023
* Fixes: Backward compatibility while the is not array in SmsDispatcher
* Improvement: Enhance plugin security by adding a nonce to the delete subscriber function.

v6.5 - 19.12.2023
* Addition: Introducing Background Processing! You can now send SMS in bulk to thousands of numbers without disrupting the user experience, [click here](https://wp-sms-pro.com/24758/new-wp-sms-6-5-update-expanded-sms-delivery-options/) to more information.
* Improvement: Improved visibility of form labels on white backgrounds, ensuring they are clearly readable regardless of theme or background color.
* Fixes: Resolved an issue with the group assignment in the subscriber shortcode, enhancing reliability and user management.

v6.4.2 - 03.12.2023
* Addition: Contact Form 7 tags now supported for SMS notifications.
* Improvement: Updated EasySendSms gateway.
* Improvement: System performance enhancements.
* Fixes: Fixed `array_filter` bug on WooCommerce order page and improved backward compatibility.
* Fixes: Resolved SMS credit display issue on Send SMS page.

v6.4.1 - 19.11.2023
* Fixes: WooCommerce order page issues & send sms in note metabox
* Improvement: Add possibility to remove duplicate numbers

v6.4 - 13.11.2023
* Improvement: Compatibility improved with WordPress v6.4.
* Improvement: Gateways Cellsynt and Sms.to.
* Improvement: Default handler now used in the Send SMS REST API for improved efficiency.
* Improvement: Added filter `wp_sms_api_message_content` for customizable message content.
* Improvement: Ensured a non-empty recipients array before initiating SMS sending.
* Improvement: Field labels and descriptions for Subscribe Form now sanitized for increased security.
* Improvement: Privacy Page design enhanced for a more polished appearance.
* Improvement: Default values added to subscriber form shortcode attributes for increased customization.
* Improvement: Minor improvements and optimizations made for better overall performance.
* Feature: Added the ability to send SMS only to local numbers.
* Feature: Included an Opt-Out link in the footer of SMS report email for easier unsubscribing.
* Fixes: Fixed a bug related to selecting multiple roles for sending SMS.
* Fixes: Addressed notice styles and RTL issues for a more consistent visual experience.
* Fixes: Resolved the issue of sending the SMS report even when the corresponding option is disabled.

v6.3.4 - 17.10.2023
* Improvement: Backward compatibility with new custom WooCommerce order table and HPOS.
* Improvement: Search users in send SMS page.
* Improvement: Gateway altiria.net updated to the latest version.
* Improvement: Made minor improvements and optimizations for better performance.

v6.3.3 - 09.10.2023
* Fixes: Some tweak form-submitting issues in sending SMS page and improvement the repeating issue
* Fixes: Some tweak styles and RTL issues in send SMS page

v6.3.2 - 27.09.2023
* Fixes: Sending Scheduled SMS issue has been fixed.
* Improvement: Email template styles and minor improvements.

v6.3 - 23.09.2023
* Feature: Redesigned Send SMS page and added search user option!
* Feature: Weekly SMS Stats Report via mail! Now you can track total sent, successful, and failed SMS, total OTP usage, and total subscribers.
* Fixes: Resolved the URI issue on oursms.com.
* Fixes: Prevented the sending of blank SMS in CF7.
* Improvement: Made minor improvements and optimizations for better performance.

[Read more](https://wp-sms-pro.com/23630/new-update-wp-sms-plugin-v6-3/) to see more information about release.

v6.2.4.1 - 09.09.2023
* Fixes: PHP Fatal Error in WooCommerceUsePhoneFieldHandler.php

v6.2.4 - 08.09.2023
* Feature: Administrator Email notification once the send SMS faced error
* Feature: Add support for `%billing_email%` variable in WooCommerce order notification
* Fixes: Send SMS Verification Twice in Safari Autofill
* Fixes: International Mobile number issue showing flags styles and improvement on International input CSS
* Improvement: Update Oursms, EbulkSMS, and support `template_id` for gateway.sa
* Improvement: Mobile Number Backward Compatibility for Customer Sessions
* Improvement: Mobile field handler functionality and improvement
* Improvement: Minor styles, RTL issues and settings

v6.2.3 - 10.08.2023
* Improvement: Refined Woocommerce customers query, limited to 1000, and optimized MySQL performance using filters.
* Development: Introduced new filters - `wp_sms_mobile_filed_handler`, `wpsms_unsubscribe_csrf_enabled`, `wp_sms_request_arguments`, and `wp_sms_request_params`.
* Improvement: Condensed spacing for improved auto-fill compatibility.
* Improvement: Updated styles, now with RTL improvement.
* Improvement: Upgraded oxisms.com gateway to API v2.
* Improvement: Enhanced the `checkMobileNumberValidity` function.
* Fixes: Resolved placeholder issue.
* Fixes: Fixed character truncation during trimming.
* Feature: New attribute `groups` now supported in `[wp_sms_subscriber_form]` shortcode.

v6.2.2 - 26.07.2023
* Improvement: Admin header and styles updated to display licenses status more effectively.
* Improvement: Enhanced `wp_sms_send()` function to validate and handle empty strings and '0' more effectively.
* New: Added support for variable `%shipping_method%` on WooCommerce order notification, enabling more customization options.
* Fixes: Addressed the issue with automatic conversion of false to an array, which was deprecated.
* Fixes: Resolved lag in Subscriber Form editing for a smoother user experience.

v6.2.1 - 17.07.2023
* Fixes: Fixed group query and subscriber verification in multi-groups for newsletters.
* Fixes: Resolved SendApp gateway errors.
* Fixes: Fixed uncaught error on subscriber page when passing null ID.
* Improvement: Improved styles and made minor enhancements.

v6.2.0.2 - 09.07.2023
* Fixes: The opt-in WooCommerce issue

v6.2.0.1 - 08.07.2023
* Addition: Support a new webhook for incoming SMS
* Improvement: Backward compatibility
* Improvement: The notice padding

v6.2.0 - 04.07.2023
* Improvement: Updated and cleaned up various mirror and major components.
* Improvement: Enhanced the styles of the SMS Newsletter form.
* Improvement: Upgraded the SMS Notification Metabox for better functionality.
* Improvement: Implemented a system to manage and display admin notices effectively.
* Addition: Integrated a Feedback button powered by [FeedbackBird!](https://feedbackbird.io/) in the admin area to gather user feedback.
* Addition: Implemented a Nonce to enable unsubscribing a number via URL.
* Addition: Introduced new actions `wp_sms_send_request_body` and `wp_sms_api_send_sms_arguments`.
* Addition: Enabled support for subscribing to Multiple Groups in the SMS newsletter.
* Addition: Integrated new SMS gateways - Micron, SignalAds and ProSmsDk.
* Addition: Added support for a new variable `%coupon_name%` in WooCommerce coupon notifications.
* Addition: Introduced support for new variables `%order_view_url%`, `%order_cancel_url%`, and `%order_received_url%` in WooCommerce order notifications.
* Fixes: Resolved the issue of duplicate SMS sending during post updates.
* Update: Updated Gateways DirectSend, Oxemis, and SmsApi.pl to their latest versions.

v6.1.5 - 13.05.2023
* Added: Users can now filter post notifications by taxonomy/terms
* Added: Added support for the Deewan.sa gateway
* Updated: Switched from the paid gateway Cellsynt to the free version
* Fixed: Resolved an issue with some other gateways
* Fixed: Fixed an error in the SmsApi gateway
* Fixed: Corrected the country code in intlTelInput
* Fixed: Numbers are now trimmed before validity check in the import process
* Improved: Sanitized user input on the privacy page
* Improved: Updated International Telephone Input to version 18.1.1
* Improved: Removed the mobile field from the WooCommerce checkout page
* Improved: Quick reply now shows actual error and success response

v6.1.4 - 16.04.2023
* Add: Gateway [WaliChat](https://wali.chat/)
* Add: Feature to make mobile field optional or required in the settings page
* Bugfix: Upgraded Library JQuery Character and Word counter plugin to v2.5.1
* Bugfix: An Issue with sending SMS to WooCommerce customers
* Bugfix: An Issue with getting WooCommerce guest mobile number from order
* Improvement: Optimized 1s2u gateway for better performance
* Improvement: Added filter `wp_sms_notification_woocommerce_order_meta_key_{meta-key}` for greater flexibility in WooCommerce order notifications.

v6.1.3 - 01.04.2023
* Bugfix: Fixed query for getting WooCommerce customer mobile numbers.
* Bugfix: Improved search functionality to consider phone numbers with and without country codes.
* Bugfix: Corrected error in [smsapi.pl](http://smsapi.pl/) while sending unicode SMS.
* Bugfix: Updated [gateway.sa](http://gateway.sa/).
* Bugfix: Fixed issue with importer.
* Bugfix: Fixed query for getting user mobile in WooCommerce by ID.

v6.1.2 - 21.03.2023
* Bugfix: The reset configuration issue has been fixed
* Bugfix: Fix getting correct value from user object in WordPressUserNotification
* Improvement: Update BulkGate API to v2 (advanced API)
* Improvement: Avoid to admin bar if the user does not have the permission

v6.1.1 - 14.03.2023
* Improvement: Make the default the first country in the list in intl-tell-input
* Improvement: Make `group_id` option in REST API endpoint
* Improvement: Minor improvements (Special Thanks to Jarko Piironen for testing)
* Add: Add gateway BulkGate.com
* Bugfix: The error response issue in gateway Liveall.eu
* Bugfix: Getting correct the mobile value in WooCommerceUsePhoneFieldHandler

v6.1 - 12.03.2023
* Add: SMS gateway ProSMS.se
* Add: Filters `wp_sms_user_mobile_number` and `wp_sms_mobile_number_validity`
* Improvement: PHP v8.2 compatibility
* Improvement: The mobile field number functionality refactored and used handler
* Improvement: The subscribe form style issue when frontend style is disabled
* Improvement: Use the DB for keeping the temp data file data in Import/Export instead of session
* Improvement: SMS gateways OurSms, gateway.sa, 1s2u and sms.to
* Bugfix: The missed close div tag in `subscribe-form.php`

v6.0.4.1 - 02.03.2023
* Improvement: Hardened plugin security and improvement

v6.0.4 - 03.02.2023
* Bugfix: Getting the correct mobile value in the user profile
* Improvement: The importer and showing the importer results
* Improvement: Minor improvements and cleanups
* Improvement: The applyCountryCode improvement

v6.0.3 - 28.01.2023
* Bugfix: The custom fields columns to the subscribers table in fresh installation
* Bugfix: The start session issue
* Improvement: The OurSms gateway updated
* Improvement: The applyCountryCode function to remove 0 and 00 from the beginning of numbres

v6.0.2 - 23.01.2023
* Bugfix: Fix sending welcome message for subscribers
* Bugfix: Fix showing correct response in outbox
* Improvement: Backward compatibility
* Improvement: Better showing response

v6.0.1 - 21.01.2023
* New: Shortcode `[wp_sms_subscriber_form]` is back! [Documentation](https://wp-sms-pro.com/resources/add-sms-subscriber-form/)
* New: Support custom fields for subscribers! [Demo](https://demo.wp-sms-pro.com/)
* New: Implement notification handler
* New: The SmsOtp library added in the plugin
* Improvement: The Oursms gateway updated
* Improvement: All notification variables and centralization of the functionality
* Improvement: Check the numeric number in the validity helper
* Improvement: The assets and clean up the code
* Bugfix: The issue while switching the language on the admin
* Bugfix: The issue on the Directsend gateway
* Bugfix: The issue on sending the SMS to author after publishing the post
* Bugfix: The resend issue on the outbox page
* Bugfix: The SMS notification issue for scheduled posts

v5.9.1 - 18.12.2022
* New: Filter subscribers by country
* New: Add helper prepareMobileNumber
* Improvement: Labels and buttons style
* Improvement: Requests responses are now more clear
* Improvement: Outbox page style
* Bugfix: The Alchemymarketing SMS gateway issue
* Bugfix: Webhooks notices issue

v5.9 - 13.12.2022
* New: [Zapier integration!](https://wp-sms-pro.com/zapier-integration)
* New: [Support Webhooks](https://wp-sms-pro.com/resources/webhooks/) to trigger actions when sending SMS and new Subscriber
* New: Support filter by subscribers in Admin → SMS → Subscribers
* New: Implement filter subscribers by the group in Admin → SMS → Groups
* New: REST API Endpoint `wpsms/v1/webhook` to register and deregister a webhook
* New: REST API Endpoint `wpsms/v1/outbox` to get outbox SMS
* New: Method `getSubscriberByMobile($number)`
* New: First Korean SMS gateway (Directsend.co.kr and Kakao)
* New: SMS gateway SmartSmsGateway.com from UAE
* Improvement: Directory and folder plugin structure
* Improvement: The template loader and structure
* Improvement: Backward compatibility for recording the outbox if the sender id is too long
* Bugfix: The include issue in CLI mode
* Bugfix: The Sms.to SMS gateway issue
* Bugfix: The Africa stalking SMS gateway issue
* Bugfix: Send SMS to multiple Groups is now available again

v5.8.5 - 23.11.2022
* Bugfix: The import system has been debugged.
* Bugfix: Some minor bugfixes have been done.
* Update: The Alchemymarketing gateway has been updated.
* Update: The Hostiran gateway has been updated.
* Improvement: The Gulp asset builder has been replaced by Node Script.
* Improvement: A Helper has been implemented to load templates instead of using of require_once function to deliver better performance and have cleaner codes.
* Add: The getTemplateIdFromMessageBody() function has been added. Now the template IDs are fetched from the message body to let customers send SMS through the gateways which have the Template ID as a required parameter.
* Add: You can now send SMS to multiple subscribers' groups in Contact Form 7 SMS notification panel.

v5.8.4 - 05.11.2022
* Bugfix: Fix force to send issue
* Bugfix: PHP session notice in WordPress Health panel
* Improvement: You can now send SMS to all your subscribers, whether they have a group or not
* Improvement: Message responses are improved to be more clear and specific
* Add: The flag of "read_and_close" has been added to start session
* Add: WebPack has been added as the asset builder
* Add: Order meta key has been implemented

v5.8.3 - 23.10.2022
* Bugfix: Remove jQuery deprecated functions
* Bugfix: Fix 1s2u gateway issues to improve its performance
* Bugfix: Fix Uwaziimobile gateway issues to improve its performance
* Bugfix: Fix Smsto gateway issues to improve its performance
* Improvement: Now WordPress users option is added to the recipient of published new posts
* Improvement: Import system has totally changed to preform better, now you can import thousands of subscribers!
* Improvement: Improve import and export error handlers
* Improvement: Check mobile number validity while importing new subscribers
* Improvement: Minor improvements & clean-up things
* Add: WP SMS now supports Liveall gateway
* Add: WP SMS now supports SendApp SMS & SendApp Whatsapp gateways
* Add: AspSms gateway now is available in the free version of WP SMS

v5.8.2 - 18.09.2022
* Bugfix: Fixed query issue while getting subscribers

v5.8.1 - 16.09.2022
* Bugfix: Fixed the Sanitize text in contact form 7 send SMS form
* Bugfix: Fixed the Select2 dropdown issue in send sms page
* Improvement: Disabled the sending SMS notification dropdown when updating posts

v5.8.0 - 11.09.2022
* Bugfix: The "Screen Options" on the groups' page has been fixed
* Feature: Add action `wp_sms_log_after_save`
* Improvement: A new Export System has been implemented. Now you can export the outbox, the inbox, and the subscribers' data.
* Improvement: New header image for the settings' page
* Improvement: A Controller Manager is added to have more clear and easy AJAX requests
* Improvement: Minor & clean-up things
* Improvement: The quick reply front-end has been redesigned
* Improvement: Mobile Number validity check has been improved
* Add: Support incoming messages for the SmsGlobal gateway

v5.7.9 - 19.08.2022
* Bugfix: Multiple recipients for the quick reply
* Improvement: Mobile International Input Functionality
* Improvement: Mobile number validation in while of the plugin to keep the valid numbers
* Improvement: Minor & clean-up things
* Add: Support SMS gateway Ajura Technologies from Bangladesh

v5.7.8 - 05.08.2022
* New: Support Quick reply in admin area for sending quick replies to number(s) or a group
* New: Ability to change items per page in outbox, inbox, scheduled, and group
* Bugfix: Sorting function issue in admin pages fixed
* Bugfix: The issue in the OurSms gateway has been fixed
* Bugfix: Wrong calling function in `wp_sms_sanitize_array()` fixed
* Improvement: The separate dial code option removed
* Improvement: Minor things

v5.7.7 - 17.07.2022
* Bugfix: The general settings page has been fixed
* Bugfix: The search issue in subscribers and other admin areas has been fixed
* Bugfix: Fixes select2 inputs and some CSS tweaks
* Improvement: Add a feature to clear SMS previews after sending

v5.7.6 - 05.07.2022
* Bugfix: SMS gateway UwaziiMobile has been fixed
* New: SMS gateway Aobox.it has been added
* New: Filter `wp_sms_output_variables_message` has been added

v5.7.5.1 - 16.06.2022
* Bugfix: Bulksms.com gateway issue has been fixed

v5.7.5 - 12.06.2022
* Improvement: Better settings fields organization
* Improvement: Update the subscriber button label while switching the subscriber action
* New: Default group option has been added to the SMS newsletter form
* New: Short URLs supported in the scheduled SMS
* New: Better rendering the mobile number fields by new function `wp_sms_render_mobile_field()`
* Bugfix: The post content words count field in SMS post notification has been fixed
* Bugfix: WP dashboard margin issue has been fixed
* Bugfix: Duplicate send SMS when the ForceToSend option is enabled in SMS post notification

v5.7.4 - 25.05.2022
* New: Gateway Hostpinnacle from Kenya
* New: Gateway Tubelight Communications from India
* New: Add the possibility to translate the strings in the settings with WPML by custom wpml-config.xml file
* Improvement: The Post SMS notification box when the force send is enabled
* Improvement: Tweak in admin styles and CSS
* Improvement: Supported DLT for GatewayHub
* Bugfix: Keep the mobile number field after updating the profile
* Bugfix: Showing display spinner when styles are not loaded

v5.7.3.1 - 07.05.2022
* Bugfix: An issue in settings page has been fixed

v5.7.3 - 05.05.2022
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

v5.7.2.2 - 24.04.2022
* Bugfix: An issue to register the schedule event has been fixed
* Bugfix: The warning wp-editor error has been fixed
* Update: A new SMS gateway from Latvia (texti.fi) has been added.
* Update: SMS Gateway VFirst has been removed due to not stability of API

v5.7.2.1 - 15.04.2022
* Bugfix: The warning error in settings page when the groups empty
* Improvement: Improvement license updater

v5.7.2 - 12.04.2022
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

v5.7.1 - 16.03.2022
* Bugfix: The issue in media URLs REST API request even the request doesn't have the media URL
* Bugfix: Separating numbers issue has been fixed in some gateways
* Feature: New action `wp_sms_number_unsubscribed_through_url` has been added
* Feature: New method `request()` has been added
* Improvement: Minors and a couple of typos

v5.7 - 07.03.2022
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

v5.6.9 - 16.02.2022
* Improvement: The SureSms gateway now supports the Sender ID and Flash
* Improvement: Compatible the Post SMS Notification with WP-REST API
* Improvement: The Force to Post SMS Notification and default Subscribe group options added
* Improvement: Improvements widget and admin styles
* Improvement: Send flash SMS enabled for eBulkSms.com
* Improvement: Send flash SMS disabled for Africa's Talking

v5.6.8.1 - 09.02.2022
* Feature: Add specific roles option for User login notification
* Bugfix: Compatibility the setting page with PHP v7.2

v5.6.8 - 02.02.2022
* Update: Tested up to v5.9
* Update: The SMS Newsletter widget is improvement and redesigned and also is Block based right now! you can also load the SMS Newsletter in Gutenberg editor!
* Bugfix: An issue with the old version of PHP with the setting page.
* Improvement: Some typo in the response of requests has been fixed.
* Deprecate: Function `wp_sms_subscribes()`. load the SMS newsletter form through Widget or Gutenberg instead.
* Remove: Shortcode `[wp-sms-subscriber-form]` is removed.

v5.6.7 - 21.01.2022
* Bugfix: The line break issue has been fixed
* Bugfix: The error in webSMS gateway has been fixed
* Improvement: Compatibility the setting page with the older version of PHP.

v5.6.6 - 15.01.2022
* Feature: Bitly Short URL has been added in the settings page > feature
* Update: Compatibility the Setting page with QuForm Child Elements and groups fields and minor improvements.
* Update: Better naming fields for GravityForms variable fields
* Updated gateway.sa gateway
* Updated uwaziimobile gateway

v5.6.5.2 - 10.12.2021
* Improvement: A notice error has been fixed in the setting page.
* Improvement: Compatibility with the older version of PHP.

v5.6.5 - 07.12.2021
* Feature: Selecting the several user groups on send SMS page has been supported
* Bugfix: The post type & author notification issue has been fixed
* Enhancement: The setting pages & styles improvement, enjoy the new admin interface!
* Update: Pro settings page merged to the main settings page

v5.6.4 - 14.11.2021
* Bugfix: Getting credential in ExpertTexting gateway has been fixed
* Bugfix: Notice errors in OnewaySms gateway has been fixed
* Enhancement: Minor improvements

v5.6.3 - 22.10.2021
* Feature: Supported Unsubscribing/Opting-Out by URL! the subscribers can Opting-Out by [https://yourdomain.com/?wpsms_unsubscribe=01111111111](https://yourdomain.com/?wpsms_unsubscribe=01111111111)
* Enhancement: Fixed a notice error in notification class
* Enhancement: Added the document link in settings
* Update: Added the MT URL and Credit Balance URL to OneWaySMS gateway
* Update: Added Callifony (Zen.ae) gateway

v5.6.2 - 02.10.2021
* NEW: MMS supported! now the plugin supports sending MMS, the Twilio & Plivo gateways are supports at the moments.
* Update: Added the argument `$mediaUrls` to `wp_sms_send()` function.
* Update: Ability to modify the admin tabs by using the `wpsms_pro_settings_tabs` and `wpsms_settings_tabs` filters.
* Update: The chosen library replaced with select2.
* Bugfix: The issue for sending the SMS while publish a new post has been fixed.
* Enhancement: For getting the correct local time, used the `current_datetime()` instead of `current_time()`.

v5.6.1
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

v5.5.1
* Fixed showing correct license status issue in the plugin's admin header
* Fixed ExpertTexting gateway

v5.5
* New admin design
* Added multiple sending SMS to CF7 field
* Added Mitto SMS gateway
* Fixed some sanitization issues in input data.
* Fixed separating the numbers issue in the send SMS page
* Updated msegat.com and reach-interactive gateways (Please re-configure your gateway again)

v5.4.13
* Updated Unifonic gateway
* Improvement of some inputs on the admin and sanitizes. (Special thanks to WPScan.com)

v5.4.12
* Fixed wrong sanitize data type on CF7 functionality.
* Minor improvements

v5.4.11
* Compatibility with WordPress v5.8
* Updated SMSBox.be, moved to free version
* Improvement sanitizing input

v5.4.10
* Updated ExpertTexting gateway, the API call to correspond to the current API for ExpertTexting. Needed to use api_secret instead of api_password and the fromvshould not be an empty strin
* Improvement: Replaced all CURL with WordPress HTTP API functions
* Fixed a couple of issues on loading the files, sanitizing things and etc.

v5.4.9.1
* Fixed Sanitize some input data in admin screens

v5.4.9
* Implemented dynamic gateway setting fields based on the current gateway's class.
* Added global-voice.net gateway.
* Added jawalbsms.ws gateway.

v5.4.8
* Added dexatel.com
* Fixed an issue in smssolutionsaustralia.com.au

v5.4.7
* Fixed some tweak issues in applying country code and user registration functionality.
* Added a new option to make verify_mobile field optional
* Added WaApi gateway
* Added newsletter method in the main class to accessible from by `WPSms()->newsletter();`

v5.4.6
* Fixed an issue to verify the subscriber in the SMS newsletter widget.

v5.4.5
* Added a new filter `wp_sms_admin_notify_registration` for admin receivers mobile numbers in registration new user
* Added a new property `$documentUrl` in gateways' class
* Added some useful document link in the setting page
* Added functionality in the subscriber's list to update multi subscribers group
* Added group id column in the groups' table
* Added Singleton functionality to initial the plugin and added `WPSms();` function to get an instance of the plugin

v5.4.4
* Added smssolutionsaustralia.com.au SMS gateway
* Added a new option in the Admin > Subscribers to change the number of items per page
* Fixed group issue on SMS subscribe form
* Updated reach-interactive API URL
* Improvement minor tweak

v5.4.3
* Updated the gateways list and fixed some wrong names.
* Added Octopush.com gateway
* Added SlinterActive.com.au gateway
* Fixed sending the welcome message for new SMS subscriber.

v5.4.2
* Added onewaysms.com.my gateway
* Update kavenega gateway
* Compatibility with WordPress v5.6

v5.4.1
* Added Reach-Interactive gateway
* Added Msegat gateway
* Fixed encoding issue in Altiria gateway
* Removed The welcome page
* Improvement gateways' countries list.
* Improvement Minor

v5.4
* Added New SMS gateways in the plugin (unifonic.com, comilio.it, malath.net.sa, altiria.net, and oxemis.com)
* Added A new option in the setting page for cleaning the numbers.
* Added Newline support for numbers in sending SMS page.
* Added Auto-submit the gateway while changing the gateway dropdown.
* Improvement Appending country code to numbers.
* Improvement Gateways and setting pages.
* Improvement CSS and admin notice with the new version of WordPress.

v5.3.1
* Added: malath.net.sa gateway.
* Added: safa-sms.com gateway.
* َUpdated: some old gateways.

v5.3
* Added: eazismspro.com gateway.
* Added: sms.net.gr gateway.
* Added: New option for cropping message in SMS post notification.
* Added: SMS meta box in custom post types.
* Updated: Mobile field in the registration form. It has required.

v5.2.2
* Added: Eazismspro.com gateway
* Improvement: Mobile field number is required in the registration form
* Updated: Dynamic the number of the word for cropping in send post notification
* Updated: Display gravity form fields tags in settings

v5.2.1
* Improvement: CF7 integration, now the dropdown field is supported.
* Updated: ms77.de gateway.

v5.2
* Added: The from parameter in `wp_sms_send()`.
* Added: Sunwaysms.com gateway.
* Updated: New API for Sms Gateway Center.
* Updated: MTarget's gateway.
* Updated Sunwaysms.com gateway.
* Disabled: The check credit in send sms page.

v5.1.9
* Fixed: gateways list.
* Fixed: scheduled feature class loading.
* Updated: Mobtexting gateway.
* Minor improvements.

v5.1.8
* Added: cheapglobalsms.com gateway.
* Minor improvements.

v5.1.7
* Added: easysendsms.com gateway.
* Added: 1s2u.com gateway.
* Minor improvements.

v5.1.6
* Fixed: WordPress core update notification notice.
* Fixed: Screen options columns for only Privacy Page.
* Fixed: Outbox orderby showing records.
* Added: Oursms.net Gateway.
* Added: Eurosms.com gateway.
* Improved: Newsletter Widget/Shortcode.
* Minor improvements.

v5.1.5
* Fixed: Enqueue styles prefix and suffix.
* Improved: Fix the edit group problem with space in group name.
* Updated: Database tables field.
* Updated: Experttexting gateway.
* Minor improvements.

v5.1.4
* Added: System info page to get more information for debugging.
* Improved: Check credits.

v5.1.3
* Minor improvements.

v5.1.2
* Add: Alchemymarketinggm.com - Africa gateway.
* Update: dot4all.it gateway now available on free version.
* Update: gatewayapi.com to support Unicode.
* Improved: Response status and Credits to do not save result if is object.
* Fixed & Improvement: Gateways: 18sms, abrestan ,adspanel, asr3sms, avalpayam, bandarsms, candoosms, iransmspanel, joghataysms, mdpanel, mydnspanel, nasrpayam, payamakalmas, ponishasms, sadat24, smsde, smshooshmand, smsmaster, smsmelli, smsservice, suresms, torpedos, yashilsms, smsglobal

v5.1.1
* Optimized: The main structure of the plugin and split process to increase performance and load.
* Updated: primotexto.com to allow multiple number sending.
* Fixed: loading menu pages content on different languages.
* Fixed: send SMS form style with some other plugins.
* Fixed: websms.com.cy, textplode.com, 0098sms.com, 18sms.ir, 500sms.ir, ebulksms.com gateways.

v5.1
* Added: Collapse for toggle the visibility of response column on Outbox table.
* Added: A new template function for sending SMS `wp_sms_send( $to, $msg, $is_flash vfalse )
* Improved: Primotexto.com gateway.
* Fixed: Issue in Textplode.com gateway.
* Fixed: Issue in WooCommerce class for sending SMS.

v5.0
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

v4.1.2
* Compatible with v5.0

v4.1.1
* Fixed: Issue to saving options.
* Added: Ignore duplicate subscribers if that exist to another group in the import page.
* Added: Aradpayamak.net gateway.
* Updated: The styles of admin forms.

v4.1
* Added: a new checkbox in the SMS subscription form for GDPR compliance.
* Added: Privacy menu in the plugin for Import & Export the user data for GDPR compliance. read [the blog post](https://wp-sms-pro.com/gdpr-compliant-in-wp-sms/) to get more information.
* Added: SMS Sending feature to different roles in Send SMS Page.
* Added: mobiledot.net.sa and smsnation.co.rw gateways.
* Added: multi-site support in WordPress Network.
* Updated: fortytwo.com, idehpayam.com, onlinepanel.ir and mobile.net.sa gateways
* Updated: the setting page.
* Disabled `applyUnicode` hood by default
* Fixed: the issue of receiving fields from Gravityforms.

v4.0.21
* Added: engy.solutions and aruba.it and hiro-sms.com gateways.
* Added: new option for sending Unicode for non-English characters (such as Persian, Arabic, Chinese or Cyrillic characters).
* Added: sender ID field. Allow typing of sender ID in Admin Send SMS page.
* Fixed: issue to send SMS through CF7 in PHP v7.0 and v7.1

v4.0.20
* Added: country code to prefix numbers if this option has value on the setting page.
* Updated: setting page. Added options for [Awesome Support plugin](https://wordpress.org/plugins/awesome-support/).

v4.0.19
* Added: tripadasmsbox.com, suresms.com, verimor.com.tr gateway.

v4.0.18
* Added: Uwaziimobile.com and cpsms.dk Gateway.
* Updated: settings page fields.

v4.0.17
* IMPORTANT: Updated the domain name of the Plugin website to wp-sms-pro.com

v4.0.16
* Added: Send SMS to multi numbers in the Contact Form 7.
* Added: Several gateways. (Comilio.it, Mensatek.com, Infodomain.asia, Smsc.ua and Mobtexting.com)
* Fixed: Show time items in the outbox SMS.

v4.0.15
* Updated: option fields.
* Added: sabanovin.com gateway.

v4.0.14
* Updated: setting page styles.
* Disabled gateway key field if not available in the current gateway.
* Fixed: issue in `text_callback` method on the options library. Used `isset` to skip undefined error.

v4.0.13
* Added: default variable for `sender_id` in the Gateway class.
* Added: textanywhere.net, abrestan.com and eshare.com Gateway.
* Updated: Pro Package options. (Added WP-Job-Manager fields).

v4.0.12
* Added: Experttexting.com Gateway.
* Added: Spirius.com Gateway.
* Added: Msgwow.com Gateway.
* Updated: NuSoap library. Compatible with PHP 5.4 - 7.1
* Removed: local translations and moved to [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-sms).
* Fixed: issue in cf7 form when the fields has array data.

v4.0.11
* Added: EbulkSMS Africa Gateway.
* Add option for hide account balance in send SMS page.
* Updated: UI for send SMS page.
* Updated: afilnet.com gateway
* Updated: smsgatewayhub.com gateway
* Updated: Asanak gateway
* Fixed: issue in importer library. The split() deprecated and used preg_split().

v4.0.10
* WordPress 4.8 compatibility
* Updated: unisender gateway
* Added: smsozone.com gateway
* Added: Character Count in the send sms page

v4.0.9
* Fixes issues in some gateways
* Supported gatewayapi.com, primotexto.com, 18sms.ir in the gateways list
* Removed: auto text direction script admin send message

v4.0.8
* Fixes undefined error in pro package setting page
* Fixes and improvements newsletter widget
* Added: new feature in sms newsletter. the subscribers can submit their mobile in multi groups
* Added: several gateways (kavenegar.com, itfisms.com, pridesms.in, torpedos.top, resalaty.com)

v4.0.7
* Added: websms.com.cy gateway
* Added: smsgatewayhub.com gateway
* Added: africastalking.com gateway
* Added: variable data to EDD message option
* Fixed: unisender gateway issue
* Fixed: duplicate send sms in the notification post

v4.0.6
* Improvement: plugin to initial the gateways
* Updated: German translations. [Thanks Robert Skiba](skibamedia.de)
* Added: asr3sms.com gateway

v4.0.5
* Fixed: path to the nusoap class on some gateways [Thanks nekofar](https://github.com/nekofar)
* Fixed: send sms time in database
* Fixes including gateways class error when the class was not in the plugin

v4.0.4
* Fixes dissabled options.

v4.0.3
* Supported WP REST API
* Improvements settings page and used main plugin for settings handler
* Updated: arabic translations. (Thanks Hammad)

v4.0.2
* PHP 7.1 compatibility
* Added: mobile number checker in register and update profile page for avoid to duplicate mobile numbers
* Added: `post_content` to the post notification input data. (Supported until 10 words due for restrictions in some gateways)
* Changed `title_post`, `url_post`, `date_post` to `post_title`, `post_url`, `post_date` on post notification input data.
* Fixed: Spelling mistakes in language file.

v4.0.1
* Fixed: default gateway issue.
* Fixed: Illegal error in cf7 sms meta box.

v4.0
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

v3.2.4
* Compatible with WP 4.7
* Fixes issue when enable plugin to add new cap.
* Fixes issue (Missing `$this->validateNumber` on the default gateway class)
* Fixes issue (Missing `$user->ID` in mobile field when create new user)
* Improvement: structure files, folders and cleaning codes.

v3.2.3
* Language french added. thanks `yves.le.bouffant@libertysurf.fr`

v3.2.3
* Added: fortytwo.com gateway.
* Added: parsgreen (api.ir) gateway.
* Compatible up to wordpress 4.6
* Fixes Undefined index error in plugin pages
* Updated: textplode gateway.

v3.2.2
* Added: new gateway (springedge.com)
* Added: new gateway (textplode.com)
* Added: new gateway (textplode.com)
* Language (Brazil) updated.

v3.2.1
* Added: New gateway (sonoratecnologia.com.br).
* Removed: dashicons from `WP_List_Table`.

v3.2
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

v3.1.3
* Compatible with wordpress 4.5
* Gateway smsline.ir Added.

v3.1.2
* Gateway gateway.sa Added.
* Gateway modiranweb.net Added.
* Fixed: empty value in cf7 option.
* Fixed: Subscribe url and credit url in dashboard glance.

v3.1.1
* Language `German` updated. (Thanks Robert Skiba Medientechnik)
* Fixed: activation code for SMS newsletter.
* Fixed: Showing SMS tab in CF7 Meta box.
* Gateway `esms24.ir` Added.
* Gateway `payamakaria.ir` Added.
* Gateway `tgfsms.ir` Added.
* Gateway `pichakhost.com Added.
* Gateway `tsms.ir Added.
* Gateway `parsasms.com Added.

v3.1.0
* Gateway `Bestit.co` Added.
* Gateway `Pegah-Payamak.ir` Added.
* Gateway `Loginpanel.ir` Added.
* Gateway `Adspanel.ir` Added.
* Gateway `Adspanel.ir` Added.
* Gateway `Mydnspanel.com` Added.
* Fixed: Update option on notification page.
* Language `Arabic` updated. (Thanks Hammad)

v3.0.2
* Gateway `LabsMobile` updated.
* Gateway `Mtarget` updated.
* Gateway `Razpayamak` Added.
* Added: select status in edit subscribe page.
* Fixed: send to subscribes in Send SMS page.
* Fixed: send notification new post to subscribers.
* Fixed: custom text for notifications new post.

v3.0.1
* Fixed: show group page and subscribe page on admin.
* Language: Swedish added. (Thanks Kramfors)

v3.0
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

v2.8.1
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

v2.8
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

v2.7.4
* Fixed: Contact form 7 shortcode. currently supported.

v2.7.3
* Added: smshosting.it webservice.
* Added: afilnet.com webservice.
* Added: faraed.com webservice.
* Added: spadsms.ir webservice.
* Added: niazpardaz.com (New webservice).
* Added: bandarsms.ir webservice.

v2.7.2
* Added: MarkazPayamak.ir webservice.
* Added: payamak-panel.com webservice.
* Added: barmanpayamak.ir webservice.
* Added: farazpayam.com webservice.
* Added: 0098sms.com webservice.
* Added: amansoft.ir webservice.
* Change webservice in asanak.ir webservice.

v2.7.1
* Added: Variables %status% and %order_name% for woocommerce new order.
* Added: smsservice.ir webservice.
* Added: asanak.ir webservice.
* Updated: idehpayam Webservice.
* Added: Mobile field number in create a new user from admin.
- Fixed notification sms when create a new user.
* Fixed: return credit in smsglobal webservice.

v2.7
* Added: Numbers of WordPress Users to send sms page.
* Added: Mobile validate number to class plugin.
* Added: Option for Disable/Enable credit account in admin menu.
* Added: afe.ir webservice.
* Added: smshooshmand.com webservice.
* Added: Description field optino for subscribe form widget.
* Included username & password field for reset button in webservice tab.
* Updated: Widget code now adhears to WordPress standards.

v2.6.7
* Added: navid-soft web service.
* Remove number_format in show credit sms.

v2.6.6
* Fixed: problem in include files.

v2.6.5
* Added: smsroo.ir web service.
* Added: smsban.ir web service.

v2.6.4
* Fixed: nusoap_client issue when include this class with other plugins.
* Remove mobile country code from tell friend section.
* Change folder and files structure plugin.

v2.6.3
* Added: SMS.ir (new version) web service.
* Added: Smsmelli.com (new version) web service.
* Fixed: sms items in posted sms page.
* Fixed: subscribe items in subscribe page.
* Fixed: Mobile validation number.
* Fixed: Warning error when export subscribers.
* Changed rial unit to credit.

v2.6.2
* Fixed: Notifications sms to subscribes.
* Added: Rayanbit.net web service.
* Added: Danish language.

v2.6.1
* Fixed: Mobile validation in subscribe form.
* Added: Reset button for remove web service data.
* Added: Melipayaamak web service.
* Added: Postgah web service.
* Added: Smsfa web service.
* Added: Turkish language.

v2.6
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

v2.5.4
* Added: sms-gateway.at Webservice.
* Added: Spanish language.
* Updated: for WordPress 4.0 release.

v2.5.3
* Added: Smstoos Webservice.
* Added: Smsmaster Webservice.
* Fixed: Showing sms credit in adminbar. Not be displayed for the users.
* Fixed: Send sms for subscriber when publish new posts.

v2.5.2
* Added: Avalpayam Webservice.
* Fixed: bugs in database queries.

v2.5.1
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

v2.5
* Fixed: Error `Call to undefined method stdClass::SendSMS()` when enable/update plugin.
* Added: Option to enable mobile field to profile page. (Setting -> Features)
* Added: Import & export in subscribe list page.
* Added: Groups link in subscribe page.
* Added: Search items in subscribe list page.
* Added: Novin sms Webservice.
* Added: Hamyaar sms Webservice.

v2.4.2
* Added: SMSde Webservice.

v2.4.1
* Added: Payamakalmas Webservice.
* Added: SMS (IPE) Webservice.
* Added: Popaksms Webservice.

v2.4
* Added: `WP_SMS` Class and placing a parent class.
* Added: `wp_sms_send` Action when Send sms from the plugin.
* Added: `wp_sms_subscribe` Action when register a new subscriber.
* Added: Notification SMS when registering a new subscribe.
* Added: Ponisha SMS Webservice.
* Added: SMS Credit and total subscriber in At a Glance.
* Fixed: Saved sms sender with `InsertToDB` method.
* Optimized: Subscribe SMS ajax form.

v2.3.5
* Updated: ippanel.com Webservice.
* Added: Sarab SMS Webservice.

v2.3.4
* Updated: Opilio Webservice.
* Added: Sharif Pardazan (2345.ir) Webservice.

v2.3.3
* Added: Asia Payamak Webservice.

v2.3.2
* Added: Arad SMS Webservice.

v2.3.1
* Added: Notification SMS when get new order from Woocommerce plugin.
* Added: Notification SMS when get new order from Easy Digital Downloads plugin.

v2.3
* Added: Tabs option in setting page.
* Added: Notification SMS when registering a new username.
* Added: Notification SMS when get new comment.
* Added: Notification SMS when username login.
* Added: Text format to published new post notification.
* Added: MP Panel Webservice.
* Added: Mediana Webservice.

v2.2.5
* Changed: Aadat 24 web service.
* Changed: Parand Host web service URL.

v2.2.4
* Added: Adpdigital Webservice.
* Added: Joghataysms Webservice.
* Fixed: Iransmspanel webservice.
* Changed: Parand Host web service URL.
* Changed: Hi SMS web service URL.
* Changed: Nasrpayam web service URL.

v2.2.2
* Added: Hi SMS Webservice.

v2.2.1
* Added: Niazpardaz Webservice.
* Fixed: Oplio Webservice.

v2.2
* Added: Payameroz Webservice.
* Added: Unisender Webservice.
* Fixed: small bug in cf7.

v2.1
* Resolved: include tell-a-freind.php file.

v2.0
* Added: Metabox sms to Contact Form 7 plugin.
* Added: SMS Message sender page.
* Added: PayamResan Webservice.
* Optimized: include files.
* Resolved: create tables when install plugin.
* Language: updated.

v2.0.2
* Resolved: loading image.
* Added: Fayasms Webservice.

v2.0.1
* Added: SMS Bartar Webservice.

v2.0
* Added: Pagination in Subscribes Newsletter page.
* Added: Group for Subscribes.
* Optimized: jQuery Calling.
* Resolved: Subscribe widget form.
* Resolved: Small problems.

v1.9.22
* Added: Nasrpayam Webservice.

v1.9.21
* Added: Caffeweb Webservice.

v1.9.20
* Resolved: add subscriber in from WordPress Admin->Newsletter subscriber.
* Added: TCIsms Webservice.

v1.9.19
* Added: ImenCms Webservice.

v1.9.18
* Added: Textsms Webservice.

v1.9.17
* Added: Smsmart Webservice.

v1.9.16
* Added: PayamakNet Webservice.

v1.9.15
* Added: BarzinSMS Webservice.
* Update: jQuery to 1.9.1.

v1.9.14
* Resolved: opilo Webservice.

v1.9.13
* Resolved: paaz Webservice.

v1.9.12
* Added: JahanPayamak Webservice.

v1.9.11
* Added: SMS-S Webservice.
* Added: SMSGlobal Webservice.
* Added: paaz Webservice.
* Added: CSS file in setting page.
* Resolved: Loads the plugin's translated strings problem.
* Language: updated.

v1.9.10
* Added: Tablighsmsi Webservice.

v1.9.9
* Added: Smscall Webservice.

v1.9.8
* Added: Smsdehi Webservice.

v1.9.7
* Added: Sadat24 Webservice.

v1.9.6
* Added: Arabic language.
* Added: Notification SMS when messages received from Contact Form 7 plugin.
* Small changes in editing Subscribers.

v1.9.5
* Added: Ariaideh Web Service.

v1.9.4
* Added: Persian SMS Web Service.

v1.9.3
* Added: SMS Click Web Service.

v1.9.2
* Added: ParandHost Web Service.
* Troubleshooting jQuery in Send SMS page.

v1.9.1
* Added: PayameAvval Web Service.

v1.9
* Added: SMSFa Web Service.
* Optimize in translation functions.
* Added: edit subscribers.

v1.8
* Added: your mobile number.
* Added: Enable/Disable calling jQuery in wordpress.
* Added: Notification of a new wordPress version by SMS.

v1.7.1
* Fix a problem in jquery.

v1.7
* Fix a problem in Get credit method.
* Fix a problem in ALTER TABLE.
* Fix a problem Active/Deactive all subscribe.

v1.6
* Added: Enable/Disable username in subscribe page.
* Fix a problem in show credit.
* Fix a problem in menu link.
* Fix a problem in word counter.

v1.5
* Added: Hostiran Web Service.
* Added: Iran SMS Panel Web Service.
* Remove Orangesms Service.
* Added: Activation subscribe.
* Optimize plugin.
* Update jquery to 1.7.2

v1.4
* Added: Portuguese language.
* Update last credit when send sms page.

v1.3.3
* Fix a problem.
* Fix a display the correct number in the list of newsletter subscribers.

v1.3.2
* Fix a problem.

v1.3.1
* Fix a problem.
* Fix credit unit in multi language.

v1.3
* Added: register link for webservice.
* Added: Suggestion post by SMS.

v1.2
* Fix a problem.

v1.1
* Adding show SMS credit in the dashboard right now.
* Adding show total subscribers in the dashboard right now.
* Adding Shortcode.
* Added: Panizsms Web Service.
* Added: Orangesms Web Service.

v1.0
* Start plugin
