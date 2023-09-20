<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo esc_html($email_title); ?></title>
    <style type="text/css">
        <?php echo file_get_contents(WP_SMS_URL . 'assets/css/mail.css'); ?>
    </style>
</head>

<body style="margin:0; padding:0;">
<div class="mail-body">

    <div class="main-section">
        <div class="header" style="background-image: url(<?php echo esc_url(WP_SMS_URL . 'assets/images/email-background.jpg'); ?>);">
            <a href="<?php echo esc_url(WP_SMS_SITE); ?>" target="_blank" class="wp-sms-logo"><img src="<?php echo WP_SMS_URL . '/assets/images/email-logo.png'; ?>" alt=""></a>
        </div>
        <div class="content">
            <h2><?php echo esc_html($email_title); ?></h2>
            <p><?php _e('Hello,', 'wp-sms'); ?></p>
            <p><?php echo wp_kses_post($content); ?></p>
            <?php if (isset($cta_title)): ?>
                <p style="text-align:center;"><a href="<?php echo esc_url($cta_link); ?>" class="button"><?php echo esc_html($cta_title); ?></a></p>
            <?php endif; ?>
            <p><?php _e('Best regards,', 'wp-sms'); ?></p>
        </div>

        <?php echo $report_data ? wp_kses_post($report_data) : ''; ?>
    </div>

    <?php echo $pro_advertisement && !$pro_is_active ? wp_kses_post($pro_advertisement) : ''; ?>

    <div class="footer-links">
        <p><?php _e('This email automatically has been sent from ', 'wp-sms'); ?><a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
    </div>

</div>
</body>

