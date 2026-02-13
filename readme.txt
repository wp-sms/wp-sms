=== WSMS (formerly WP SMS) – SMS & MMS Notifications with OTP and 2FA for WooCommerce ===
Contributors: veronalabs, mostafa.s1990, kashani
Donate link: https://wp-sms-pro.com/donate
Tags: sms notifications, otp login, woocommerce sms, 2fa authentication, bulk sms
Requires at least: 4.1
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 7.1.1
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send SMS/MMS notifications, OTP & 2FA messages, and WooCommerce updates with support for multiple gateways and plugin integrations.

== Description ==
[WSMS](https://wp-sms-pro.com/?utm_source=wporg&utm_medium=link&utm_campaign=website) lets you send SMS/MMS notifications, one-time passwords (OTP), and two-factor authentication (2FA) messages straight from WordPress. It supports a wide range of SMS gateways and integrates with popular e-commerce and form builder plugins.

**Use WSMS to:**

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

**With All-in-One you get:**

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
= v7.1.1 - 2026-01-28 =
- **New:** Added `%product_name%` variable support for WooCommerce product notifications.
- **Security:** Escape output and validate input in Outbox list table.
- **Fix:** Fixed CF7 "Send to form" field not extracting phone number from form submission.
- **Fix:** Fixed SMS.to gateway GetCredit() to handle decoded JSON response correctly.
- **Fix:** Fixed license API cache to prevent excessive requests on multilingual sites.
- **Fix:** Fixed license conditions for header and notice display separation.
- **Fix:** Fixed license status image size issue in header.
- **Fix:** Fixed accessibility issues on privacy page with proper label associations.

= v7.1 - 2025-12-16 =
- **New:** Introduced Notifications to receive important updates and promotions.
- **New:** Added support for the Ghasedak.me gateway.
- **New:** Added settings for message storage and retention in Outbox and Inbox under "Message Storage & Cleanup".
- **Enhancement:** Updated PHP requirement to version 7.2.
- **Enhancement:** Tested up to v6.9
- **Enhancement:** Improve error handling when PHP SoapClient is unavailable in gateways.
- **Fix:** Fixed privacy data deletion not removing subscribers from database due to incorrect phone number handling and query format issues.

[See changelog for all versions](https://raw.githubusercontent.com/wp-sms/wp-sms/master/CHANGELOG.md).
