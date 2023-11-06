<div id="wpsms-privacyPage" class="wrap wpsms-wrap privacy_page">
    <?php echo \WP_SMS\Helper::loadTemplate('header.php'); ?>

    <div class="wpsms-wrap__main">
        <h1><?php echo esc_html($title); ?></h1>

        <!-- results section -->
        <div class="wpsms-privacyPage__Result__Container"></div>

        <input type="hidden" name="wp_sms_nonce_privacy" value="<?php echo wp_create_nonce('wp_sms_nonce_privacy'); ?>">
        <form class="wpsms-privacyPage__Form" action="" method="post">
            <h2><?php _e('Export Data', 'wp-sms'); ?></h2>
            <div class="wpsms-privacyPage__ExportData">
                <p><?php _e('Gain access to and maintain control over the information we have on file about you, ensuring that we adhere to stringent data protection regulations and respect your privacy.', 'wp-sms'); ?></p>
                <div class="wpsms-privacyPage__options">
                    <div class="wpsms-privacyPage__options__field">
                        <label><?php _e('Enter User’s Mobile Number', 'wp-sms'); ?></label>
                        <input type="tel" name="mobile-number-export" value=""/>
                    </div>
                    <input name="export" type="submit" value="<?php _e('Export', 'wp-sms'); ?>">
                </div>
            </div>

            <h2><?php _e('Delete Data', 'wp-sms'); ?></h2>
            <div class="wpsms-privacyPage__DeleteData">
                <p><?php _e('Safeguard your privacy by requesting the removal of your personal information from our records, following GDPR guidelines.', 'wp-sms'); ?></p>
                <div class="wpsms-privacyPage__options">
                    <div class="wpsms-privacyPage__options__field">
                        <label><?php _e('Enter User’s Mobile Number', 'wp-sms'); ?></label>
                        <input type="tel" name="mobile-number-delete" value=""/>
                    </div>
                    <input name="delete" type="submit" value="<?php _e('Delete', 'wp-sms'); ?>">
                </div>
                <span class="description"><?php _e('Note: You cannot undo these actions.', 'wp-sms'); ?></span>
            </div>
        </form>

        <div class="wpsms-privacyGdpr">
            <p style="text-align: center;"><img src="<?php echo WP_SMS_URL . '/assets/images/gdpr.svg'; ?>" alt="GDPR"></p>
            <p class="text-lead">
                <?php echo __('WP SMS plugin is GDPR-compliant, enabling users to export or delete their plugin-related data as per Article 17 of GDPR. To manage personal data site-wide, use the "Export Personal Data" or "Erase Personal Data" pages.', 'wp-sms'); ?>
            </p>
        </div>

    </div>
</div>