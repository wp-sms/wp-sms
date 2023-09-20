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
        <div class="header" style="background-image: url(<?php echo WP_SMS_URL . 'assets/images/email-background.jpg'; ?>);">
            <a href="<?php echo esc_url(WP_SMS_SITE); ?>" target="_blank" class="wp-sms-logo"><img src="<?php echo WP_SMS_URL . '/assets/images/email-logo.png'; ?>"></a>
        </div>
        <div class="content">
            <h2><?php echo esc_html($email_title); ?></h2>
            <p><?php _e('Hello,', 'wp-sms'); ?></p>
            <p><?php _e('This is the statistics for your website SMS.', 'wp-sms'); ?></p>
        </div>

        <div class="graphical-reports">
            <h4><?php echo sprintf(__('From %s to %s 2023', 'wp-sms'), $duration['startDate'], $duration['endDate']); ?></h4>
            <h5><?php _e('At a glance', 'wp-sms'); ?></h5>

            <table>
                <tr>
                    <td>
                        <img src="<?php echo WP_SMS_URL . 'assets/images/icons/sample-failed-icon.png'; ?>" class="icon"/>
                        <span class="value"><?php echo $smsData['success']; ?></span>
                        <span class="name"><?php _e('Successful SMS', 'wp-sms'); ?></span>
                    </td>
                    <td>
                        <img src="<?php echo WP_SMS_URL . 'assets/images/icons/sample-failed-icon.png'; ?>" class="icon"/>
                        <span class="value"><?php echo $smsData['failed']; ?></span>
                        <span class="name"><?php _e('Failed SMS', 'wp-sms'); ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <img src="<?php echo WP_SMS_URL . 'assets/images/icons/sample-failed-icon.png'; ?>" class="icon"/>
                        <span class="value"><?php echo $subscriptionData['activeSubscribers']; ?></span>
                        <span class="name"><?php _e('New Active Subscribers SMS', 'wp-sms'); ?></span>
                    </td>
                    <td>
                        <img src="<?php echo WP_SMS_URL . 'assets/images/icons/sample-failed-icon.png'; ?>" class="icon"/>
                        <span class="value"><?php echo $subscriptionData['deactiveSubscribers']; ?></span>
                        <span class="name"><?php _e('New Deactive Subscribers', 'wp-sms'); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <!--    SMS Delivery Report Table-->
        <div class="table-reports">
            <h3><?php _e('SMS Delivery Report', 'wp-sms'); ?></h3>

            <table>
                <thead>
                <tr>
                    <td><?php _e('Count', 'wp-sms'); ?></td>
                    <td><?php _e('Status', 'wp-sms'); ?></td>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php _e('Failed SMS', 'wp-sms'); ?></td>
                    <td><?php echo $smsData['failed']; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Successful SMS', 'wp-sms'); ?></td>
                    <td><?php echo $smsData['success']; ?></td>
                </tr>
                <tr>
                    <td class="total"><?php _e('Total', 'wp-sms'); ?></td>
                    <td colspan="2" class="total"><?php echo $smsData['total']; ?></td>
                </tr>
                </tbody>
            </table>
        </div>

        <!--    Subscription Report Table-->
        <div class="table-reports">
            <h3><?php _e('Subscription Report', 'wp-sms'); ?></h3>

            <table>
                <thead>
                <tr>
                    <td><?php _e('Group', 'wp-sms'); ?></td>
                    <td><?php _e('Deactive', 'wp-sms'); ?></td>
                    <td><?php _e('Active', 'wp-sms'); ?></td>
                </tr>
                </thead>
                <tbody>

                <?php
                // Loop through groups
                foreach ($subscriptionData['groups'] as $group) { ?>
                    <tr>
                        <td><?php echo esc_html($group['name']); ?></td>
                        <td><?php echo esc_html($group['active']); ?></td>
                        <td><?php echo esc_html($group['deactive']); ?></td>
                    </tr>
                <?php } ?>

                <tr>
                    <td class="total"><?php _e('Total', 'wp-sms'); ?></td>
                    <td colspan="2" class="total"><?php echo $subscriptionData['total']; ?></td>
                </tr>
                </tbody>
            </table>
        </div>

        <!--    Login with SMS Table-->
        <div class="table-reports">
            <h3><?php _e('Login With SMS Report', 'wp-sms'); ?></h3>

            <table>
                <thead>
                <tr>
                    <td><?php _e('Type', 'wp-sms'); ?></td>
                    <td><?php _e('Count', 'wp-sms'); ?></td>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php _e('Failed', 'wp-sms'); ?></td>
                    <td><?php echo $loginData['failed']; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Successful', 'wp-sms'); ?></td>
                    <td><?php echo $loginData['success']; ?></td>
                </tr>
                <tr>
                    <td class="total"><?php _e('Total', 'wp-sms'); ?></td>
                    <td colspan="2" class="total"><?php echo $loginData['total']; ?></td>
                </tr>
                </tbody>
            </table>
        </div>

    </div>

    <div class="pro-ad">
        <table>
            <tr>
                <td><h3><?php _e('Use Our Pro Plugin', 'wp-sms'); ?></h3></td>
                <td class="button">
                    <a target="_blank" href="<?php echo WP_SMS_SITE . '/buy'; ?>"><?php _e('Buy Now ', 'wp-sms'); ?><img src="<?php echo WP_SMS_URL . 'assets/images/icons/white-chev.png'; ?>"/></a>
                </td>
            </tr>
        </table>
        <p><?php _e('Here is some content to get the pro version of wp sms.', 'wp-sms'); ?></p>
    </div>

    <div class="footer-links">
        <p><?php _e('This email automatically has been sent from ', 'wp-sms'); ?><a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
    </div>

</div>
</body>

