=== WSMS (formerly WP SMS) – SMS & MMS Notifications with OTP and 2FA for WooCommerce ===
Contributors: veronalabs, mostafa.s1990, kashani
Donate link: https://wp-sms-pro.com/donate
Tags: sms notifications, otp login, woocommerce sms, 2fa authentication, bulk sms
Requires at least: 4.1
Tested up to: 6.8
Requires PHP: 5.6
Stable tag: 7.0.10
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send SMS/MMS notifications, OTP & 2FA messages, and WooCommerce updates with support for multiple gateways and plugin integrations.

== Description ==
[WSMS](https://wp-sms-pro.com/?utm_source=wporg&utm_medium=link&utm_campaign=website) lets you send SMS/MMS notifications, one-time passwords (OTP), and two-factor authentication (2FA) messages straight from WordPress. It supports a wide range of SMS gateways and integrates with popular e-commerce and form builder plugins.

Use WSMS to:
- Keep customers updated on WooCommerce orders
- Collect subscribers with SMS newsletter forms
- Secure logins with OTP & 2FA
- Alert admins about new users, logins, or updates
- Run marketing campaigns with scheduled or bulk SMS

👉 [Check out the demo](https://demo.wp-sms-pro.com/wp-login.php) | [View screenshots](#screenshots) | [See supported gateways](https://wp-sms-pro.com/gateways?utm_source=wporg&utm_medium=link&utm_campaign=gateways) | [Explore integrations](https://wp-sms-pro.com/integrations?utm_source=wporg&utm_medium=link&utm_campaign=integrations)

## ✨ Key Features
- **Send SMS/MMS:** Send messages through your choice of supported SMS gateways.
- **E-Commerce & Form Integration:** Seamlessly integrates with popular e-commerce platforms and form builders.
- **OTP & 2FA:** Add extra login security with one-time passwords and two-factor authentication.
- **Mobile Login:** Let users log in with their mobile number.
- **Admin Alerts:** Get notified when new users register, posts are published, or WordPress updates are available.
- **Newsletters & Widgets:** Build SMS newsletter forms with shortcodes, widgets, or Gutenberg blocks.
- **Two-Way SMS (All-in-One):** Receive and reply to SMS messages inside WordPress.
- **Bulk & Scheduled SMS:** Send to multiple recipients at once, immediately or on schedule.
- **Third-Party Integration:** Connect with external services and automation platforms.
- **Messaging Button:** Let visitors reach you instantly via messaging channels.
- **GDPR Compliant:** Built with privacy and compliance in mind.

## 💎 Upgrade to WSMS All-in-One
Unlock additional features with **All-in-One** — the plan that gives you access to all premium add-ons in one package.

With All-in-One you get:
- Secure login & registration with OTP & 2FA
- Scheduled & recurring SMS/MMS
- Two-way SMS inbox
- Enhanced e-commerce features (login, checkout verification, order updates)
- Membership platform integrations
- Advanced form builder SMS capabilities
- Marketing automation integrations
- Booking system compatibility
- URL shortening service integration
- All future add-ons included

👉 [See All-in-One details & compare features](https://wp-sms-pro.com/pricing/?utm_source=wporg&utm_medium=link&utm_campaign=pricing)

## 🐞 Report Bugs & Security
- Found a bug? [Open an issue on GitHub](https://github.com/wp-sms/wp-sms/issues/new).
- Security concerns? Report them via the [Patchstack VDP program](https://patchstack.com/database/wordpress/plugin/wp-sms/vdp).

## 📝 Trademark Notice
WooCommerce, GravityForms, Elementor, Contact Form 7, Twilio, WhatsApp, Clickatell, BulkSMS, Plivo, Zapier, Bitly, and other product names mentioned are trademarks of their respective owners. WSMS is not affiliated with, endorsed by, or sponsored by these companies.

== Installation ==
1. Upload `wp-sms` to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Add the **WSMS Subscribe** widget to your site
4. (All-in-One users) Enter your license key at **SMS → Settings → License**

📺 [Video Installation Guide](https://www.youtube.com/watch?v=uZVs8DXu_XM)

== Source Code and Build Instructions ==

**Note:** The plugin works out of the box - no build steps required for regular users. This section is for
developers who want to modify or contribute to the source code.

= Source Locations =

All source code for minified JavaScript and CSS is included in the plugin:

* JavaScript source: `assets/src/scripts/` → compiled to `assets/js/`
* Gutenberg blocks: `assets/src/blocks/` → compiled to `assets/blocks/`
* SCSS source: `assets/src/scss/` → compiled to `assets/css/`

= Third-Party Libraries =

[Chart.js](https://github.com/chartjs/Chart.js), [DataTables](https://github.com/DataTables/DataTables), [flatpickr](https://github.com/flatpickr/flatpickr), [intlTelInput](https://github.com/jackocnr/intl-tel-input), [jquery.repeater](https://github.com/DubFriend/jquery.repeater), [jQuery Word and Character Counter](https://github.com/qwertypants/jQuery-Word-and-Character-Counter-Plugin), [Select2](https://github.com/select2/select2), [Tooltipster](https://github.com/calebjacob/tooltipster)

= Repository =

Full source code: [github.com/wp-sms/wp-sms](https://github.com/wp-sms/wp-sms)

== Frequently Asked Questions ==
= Who should use WSMS? =
Any WordPress site that wants to enhance communication with users, customers, or subscribers via SMS. Perfect for businesses, bloggers, and e-commerce stores.

= Is technical knowledge required? =
No. WSMS is beginner-friendly and well-documented.

= Is WSMS GDPR compliant? =
Yes. It includes tools to manage user data responsibly.

= Does WSMS support bulk SMS? =
Yes. It can handle large volumes with asynchronous sending.

= What plugins integrate with WSMS? =
WSMS integrates with popular e-commerce platforms, form builders, membership systems, and marketing automation tools. See the full list of supported integrations on our website.

= How many SMS can I send? =
Unlimited — your SMS gateway plan determines limits.

= Can I send SMS under my company name? =
Yes, if supported by your SMS gateway.

= What's included in All-in-One? =
All premium features + all add-ons in one package.
👉 [Compare free vs All-in-One](https://wp-sms-pro.com/pricing/?utm_source=wporg&utm_medium=link&utm_campaign=pricing)

== Screenshots ==
1. Send SMS Page
2. Send SMS Page: Receiver
3. Send SMS Page: Options
4. Outbox
5. Inbox
6. Subscribers Management Page
7. Login With SMS
8. SMS Subscriber Widget
9. SMS Stats Dashboard Widget
10. Email Notification: SMS Failed Delivery
11. Settings
12. Settings: Gateway Configuration
13. Settings: Advanced
14. Settings: OTP & 2FA
15. Settings: WooCommerce
16. Email Notification: SMS Stats
17. Send SMS form (Gutenberg block)
18. Message Button

== Upgrade Notice ==
= 7.0 =
- New Onboarding, Add-on Manager, and All-in-One package.

== Changelog ==
= v7.0.10 - 2025-12-01 =
- **Enhancement:** Refactored plugin architecture for better add-on extensibility.
- **Enhancement:** Added new filters and hooks for developers.
- **Fix:** Minor bug fixes and improvements.

= v7.0.9 - 2025-11-26 =
- **Enhancement:** Minor improvements.

= v7.0.8 - 2025-11-23 =
- **Enhancement:** Repositioned the "All-in-One Required" label to the top of the PRO gateway onboarding table for better visibility.
- **Enhancement:** Added more plugin details to the Site Health Info section for easier diagnostics.
- **Enhancement:** Removed deprecated `utf8_decode()` usage in `nusoap.class.php`.
- **Enhancement:** Updated libraries and cleaned up assets.
- **Fix:** Fixed incorrect changelog URL links in add-ons.
- **Fix:** Fixed connection status display and moved the Connection Status section below the Gateway Guide for improved UI
- **Fix:** Fixed SCSS Compilation Error in mail.css

= v7.0.4 - 2025-11-02 =
- **New:** Added support for the SMS.es gateway.
- **New:** Display an admin notice when the gateway version changes, required fields are missing, or the gateway is not configured.
- **Enhancement:** Added support for Service-Line SMS.ir template-based messaging.
- **Enhancement:** Refactored the MeliPayamak gateway for better stability and reliability.
- **Enhancement:** Improved Kavenegar gateway to support template-based SMS messages with variable placeholders.
- **Enhancement:** Refactored the FARAZSMS gateway for improved reliability.
- **Fix:** Disabled caching to prevent duplicate responses for identical messages.
- **Fix:** Ensured PHP 8.1+ compatibility by avoiding "Automatic conversion of false to array" warnings.
- **Fix:** Delayed the anonymous data opt-in notice to appear 7 days after plugin activation.
- **Fix:** Masked sensitive variables (`code`, `otp`, `post_password`, `coupon_code`) in logs when `WP_DEBUG` is disabled.

= v7.0.3 - 2025-09-17 =
- **Enhancement:** Improved Send SMS page performance by loading recipients via AJAX instead of on initial render.
- **Enhancement:** Prevented sending emails to users who registered with only a phone number.

= v7.0.2 - 2025-08-18 =
- **New:** License keys can now be set via `wp-config.php` using constants like `WP_SMS_LICENSE` and are automatically validated on init.
- **New:** Added plugin information to the Site Health Info section for easier diagnostics.
- **New:** Added the Threema gateway to Pro gateways
- **Fix:** Fixed variable rendering in message content.
- **Fix:** Fixed showing migration failed notice on not valid licenses.
- **Fix:** Resolved issue where screen options were disappearing on non-plugin-related admin pages.
- **Fix:** Properly replace special tags (e.g., `%_site_title%`) in message content of CF7.
- **Fix:** Corrected handling of multiple phone numbers in Contact Form 7 integration so SMS is sent to all recipients, not just the first one.
- **Fix:** SMS registration now handles duplicate usernames by adding a numeric suffix, allowing re-registration with the same phone number.
- **Fix:** Only send SMS notifications for published posts matching selected taxonomy term IDs.
- **Enhancement:** Added user capability checks to AJAX actions in the license manager to restrict access to authorized roles only.
- **Enhancement:** Removed deprecated SMS gateways: smss, bearsms, mobtexting, waapi, livesms, ozioma, smsgateway, zipwhip, whatsappapi, asr3sms, smsdone, micron, sms_s, tcisms, aradpayamak, dot4all.

= v7.0 - 2025-07-09 =
- **New:** Introduced an Onboarding Process to simplify gateway integration.
- **New:** Launched a new Add-on Manager for easier add-on installation and updates.
- **New:** Introduced WSMS All-in-One package.
- **Enhancement:** Removed the FeedbackBird button and its related functionality.
- **Enhancement:** Integrated NumberParser for better phone number validation.
- **Enhancement:** Improved newsletter unsubscription handling based on different user inputs.
- **Enhancement:** Added support for a wider range of CSV MIME types during import.
- **Enhancement:** Refactored the MeliPayamak gateway for improved reliability.
- **Enhancement:** Improved overall UX across the plugin.
- **Fix:** Fixed disappearing billing fields in WooCommerce (Legacy and HPOS modes).
- **Fix:** Fixed scheduled post notification issues.
- **Fix:** Removed deprecated gateways.
- **Fix:** Resolved fatal error when passing invalid meta in notification content.
- **Fix:** Fixed message logging issues on multisite installations.

[See changelog for all versions](https://raw.githubusercontent.com/wp-sms/wp-sms/master/CHANGELOG.md).