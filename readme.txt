=== WP SMS – Ultimate SMS & MMS Notifications, OTP, 2FA, and WooCommerce & Forms Integrations ===
Contributors: veronalabs, mostafa.s1990, kashani
Donate link: https://wp-sms-pro.com/donate
Tags: sms notifications, otp login, woocommerce sms, 2fa authentication, bulk sms
Requires at least: 4.1
Tested up to: 6.8
Requires PHP: 5.6
Stable tag: 7.0.3
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send SMS/MMS, OTP & 2FA messages, WooCommerce updates, and bulk campaigns with 300+ gateways and plugin integrations.

== Description ==
### 🚀 The most complete SMS solution for WordPress

[WP SMS](https://wp-sms-pro.com/?utm_source=wporg&utm_medium=link&utm_campaign=website) lets you send SMS/MMS notifications, one-time passwords (OTP), and two-factor authentication (2FA) messages straight from WordPress. It supports **300+ gateways** (Twilio, WhatsApp Business API, Clickatell, BulkSMS, Plivo, and more) and integrates with popular plugins like **WooCommerce, GravityForms, Elementor, and Contact Form 7**.

Use WP SMS to:
- Keep customers updated on WooCommerce orders
- Collect subscribers with SMS newsletter forms
- Secure logins with OTP & 2FA
- Alert admins about new users, logins, or updates
- Run marketing campaigns with scheduled or bulk SMS

👉 [Check out the demo](https://demo.wp-sms-pro.com/wp-login.php) | [View screenshots](#screenshots) | [See supported gateways](https://wp-sms-pro.com/gateways?utm_source=wporg&utm_medium=link&utm_campaign=gateways) | [Explore integrations](https://wp-sms-pro.com/integrations?utm_source=wporg&utm_medium=link&utm_campaign=integrations)

## ✨ Top Features
- **Send SMS/MMS:** Send messages via 300+ gateways, including Twilio, WhatsApp, and Clickatell.
- **WooCommerce & Forms Ready:** Works with WooCommerce, GravityForms, Contact Form 7, Ninja Forms, Formidable, Elementor, and more.
- **OTP & 2FA:** Add extra login security with one-time passwords and two-factor authentication.
- **Mobile Login:** Let users log in with their mobile number.
- **Admin Alerts:** Get notified when new users register, posts are published, or WordPress updates are available.
- **Newsletters & Widgets:** Build SMS newsletter forms with shortcodes, widgets, or Gutenberg blocks.
- **Two-Way SMS (All-in-One):** Receive and reply to SMS messages inside WordPress.
- **Bulk & Scheduled SMS:** Send to thousands of numbers at once, immediately or on schedule.
- **Zapier Integration:** Connect WP SMS with 5,000+ apps.
- **Messaging Button:** Let visitors reach you instantly via SMS, WhatsApp, or Telegram.
- **GDPR Compliant:** Built with privacy and compliance in mind.

## 💎 Upgrade to WP SMS All-in-One
Unlock the full power of WP SMS with **All-in-One** — the plan that gives you everything in one package.

With All-in-One you get:
- Secure login & registration with OTP & 2FA
- Scheduled & recurring SMS/MMS
- Two-way SMS inbox
- WooCommerce Pro (login, checkout verification, order updates)
- Membership integrations (Paid Memberships Pro, Simple Membership, etc.)
- Elementor Form SMS
- FluentCRM, FluentForms, FluentSupport SMS
- Booking integrations (BookingPress, WooCommerce Appointments, Booking Calendar)
- URL shortening with Bitly
- All future add-ons included

👉 [See All-in-One details & compare features](https://wp-sms-pro.com/pricing/?utm_source=wporg&utm_medium=link&utm_campaign=pricing)

## 🐞 Report Bugs & Security
- Found a bug? [Open an issue on GitHub](https://github.com/wp-sms/wp-sms/issues/new).
- Security concerns? Report them via the [Patchstack VDP program](https://patchstack.com/database/wordpress/plugin/wp-sms/vdp).

== Installation ==
1. Upload `wp-sms` to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Add the **WP SMS Subscribe** widget to your site
4. (All-in-One users) Enter your license key at **SMS → Settings → License**

📺 [Video Installation Guide](https://www.youtube.com/watch?v=uZVs8DXu_XM)

== Frequently Asked Questions ==
= Who should use WP SMS? =
Any WordPress site that wants to enhance communication with users, customers, or subscribers via SMS. Perfect for businesses, bloggers, and e-commerce stores.

= Is technical knowledge required? =
No. WP SMS is beginner-friendly and well-documented.

= Is WP SMS GDPR compliant? =
Yes. It includes tools to manage user data responsibly.

= Does WP SMS support bulk SMS? =
Yes. It can handle large volumes with asynchronous sending.

= What plugins integrate with WP SMS? =
WooCommerce, GravityForms, Contact Form 7, BuddyPress, EDD, Elementor, and more. Plus, Zapier connects it to 5,000+ apps.

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
- **New:** Introduced WP SMS All-in-One package.
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
