<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo esc_html($email_title); ?></title>
    <style type="text/css">
        <?php echo file_get_contents(WP_SMS_DIR . 'assets/css/mail.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents ?>
    </style>
</head>

<body style="margin:0; padding:0;">
<div class="mail-body">

    <div class="main-section">

        <table class="header" style="background-image: url(<?php echo esc_url(WP_SMS_URL . 'assets/images/email-background.jpg'); ?>);">
            <tr>
                <td></td>
                <td style="vertical-align: bottom; width:35%;">
                    <a href="<?php echo esc_url(WP_SMS_SITE); ?>" target="_blank" class="wp-sms-logo">
                        <img src="<?php echo esc_url(WP_SMS_URL . '/assets/images/email-logo.png'); ?>">
                    </a>
                </td>
            </tr>
        </table>

        <div class="content">
            <h2><?php echo esc_html($email_title); ?></h2>

            <?php if (isset($content) && strlen($content) > 0): ?>
                <p><?php esc_html_e('Hello,', 'wp-sms'); ?></p>
                <p><?php echo wp_kses_post($content); ?></p>
                <p style="margin-top:30px;"><?php esc_html_e('Best regards,', 'wp-sms'); ?></p>
            <?php endif; ?>

            <?php if (isset($cta_title)): ?>
                <p style="text-align:center;"><a href="<?php echo esc_url($cta_link); ?>" class="button"><?php echo esc_html($cta_title); ?></a></p>
            <?php endif; ?>

        </div>
        <?php echo isset($report_data) ? wp_kses_post($report_data) : ''; ?>
    </div>

    <?php echo isset($footer_suggestion) && isset($pro_is_active) && !$pro_is_active ? wp_kses_post($footer_suggestion) : ''; ?>

    <div class="footer-links">
        <p><?php echo sprintf('This email automatically has been sent from <a href="%s">%s</a>.', esc_url($site_url), esc_html($site_name)) ?></p>
        <p style="margin-top: 5px;"><?php echo sprintf('<a href="%s">Manage Email Notifications</a>', esc_url(admin_url('admin.php?page=wp-sms-settings&tab=advanced'))) ?></p>
    </div>

</div>
</body>

