<div id="wpsms-privacyPage" class="wrap wpsms-wrap privacy_page">
    <?php echo \WP_SMS\Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="wp-header-end"></div>
    <div class="wpsms-wrap__main">
        <h1><?php echo esc_html($title); ?></h1>

        <!-- results section -->
        <div class="wpsms-privacyPage__Result__Container"></div>

        <form class="wpsms-privacyPage__Form" action="" method="post">
            <h2><?php esc_html_e('Export Data', 'wp-sms'); ?></h2>
            <div class="wpsms-privacyPage__ExportData">
                <p><?php esc_html_e("This section allows you to export all of the SMS data associated with a specific user's mobile number. This data includes the user's name, phone number, and all of the SMS messages that they have sent and received.", 'wp-sms'); ?></p>
                <div class="wpsms-privacyPage__options">
                    <div class="wpsms-privacyPage__options__field">
                        <label><?php esc_html_e('Enter User’s Mobile Number', 'wp-sms'); ?></label>
                        <input type="tel" name="mobile-number-export" value=""/>
                    </div>
                    <input name="export" type="submit" value="<?php esc_html_e('Export', 'wp-sms'); ?>">
                </div>
            </div>

            <h2><?php esc_html_e('Delete Data', 'wp-sms'); ?></h2>
            <div class="wpsms-privacyPage__DeleteData">
                <p><?php esc_html_e("This section allows you to delete all of the SMS data associated with a specific user's mobile number. This is a permanent action, so be sure to back up your data before proceeding.", 'wp-sms'); ?></p>
                <div class="wpsms-privacyPage__options">
                    <div class="wpsms-privacyPage__options__field">
                        <label><?php esc_html_e('Enter User’s Mobile Number', 'wp-sms'); ?></label>
                        <input type="tel" name="mobile-number-delete" value=""/>
                    </div>
                    <input name="delete" type="submit" value="<?php esc_html_e('Delete', 'wp-sms'); ?>">
                </div>
                <span class="description"><?php esc_html_e('Note: You cannot undo these actions.', 'wp-sms'); ?></span>
            </div>
        </form>

        <div class="wpsms-privacyGdpr">
            <p style="text-align: center;"><img src="<?php echo esc_url(WP_SMS_URL) . '/assets/images/gdpr.svg'; ?>" alt="GDPR"></p>
            <p class="text-lead">
                <?php echo esc_html__('WP SMS plugin is GDPR-compliant, enabling users to export or delete their plugin-related data as per Article 17 of GDPR. To manage personal data site-wide, use the "Export Personal Data" or "Erase Personal Data" pages.', 'wp-sms'); ?>
            </p>
        </div>

    </div>
</div>