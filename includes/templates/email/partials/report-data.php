<!--    Graphical Report    -->
<div class="graphical-reports">
    <h4>
        <?php 
            // translators: %1$s: Start date, %2$s: End date
            echo sprintf(esc_html__('From %1$s to %2$s', 'wp-sms'), esc_html($duration['startDate']), esc_html($duration['endDate']));
        ?>
    </h4>
    <h5><?php esc_html_e('At a glance', 'wp-sms'); ?></h5>

    <table>
        <tr>
            <td>
                <img src="<?php echo esc_url(WP_SMS_URL . 'assets/images/icons/success-sms.png'); ?>" class="icon"/>
                <span class="value"><?php echo esc_html($sms_data['success']); ?></span>
                <span class="name"><?php esc_html_e('Successful SMS', 'wp-sms'); ?></span>
            </td>
            <td>
                <img src="<?php echo esc_url(WP_SMS_URL . 'assets/images/icons/failed-sms.png'); ?>" class="icon"/>
                <span class="value"><?php echo esc_html($sms_data['failed']); ?></span>
                <span class="name"><?php esc_html_e('Failed SMS', 'wp-sms'); ?></span>
            </td>
        </tr>
        <tr>
            <td>
                <img src="<?php echo esc_url(WP_SMS_URL . 'assets/images/icons/active-user.png'); ?>" class="icon"/>
                <span class="value"><?php echo esc_html($subscription_data['activeSubscribers']); ?></span>
                <span class="name"><?php esc_html_e('New Active Subscribers SMS', 'wp-sms'); ?></span>
            </td>
            <td>
                <img src="<?php echo esc_url(WP_SMS_URL . 'assets/images/icons/added-user.png'); ?>" class="icon"/>
                <span class="value"><?php echo esc_html($subscription_data['deactiveSubscribers']); ?></span>
                <span class="name"><?php esc_html_e('New Deactive Subscribers', 'wp-sms'); ?></span>
            </td>
        </tr>
    </table>
</div>

<div class="table-reports">

    <!--    SMS Delivery Report Table   -->
    <h3><?php esc_html_e('SMS Delivery Report', 'wp-sms'); ?></h3>
    <table>
        <thead>
        <tr>
            <td style="text-align: left; border-radius: 10px 0 0 10px;"><?php esc_html_e('Count', 'wp-sms'); ?></td>
            <td style="text-align: right; border-radius: 0 10px 10px 0;"><?php esc_html_e('Status', 'wp-sms'); ?></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td style="text-align: left"><?php esc_html_e('Failed SMS', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo esc_html($sms_data['failed']); ?></td>
        </tr>
        <tr>
            <td style="text-align: left"><?php esc_html_e('Successful SMS', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo esc_html($sms_data['success']); ?></td>
        </tr>
        <tr>
            <td class="total" style="text-align: left"><?php esc_html_e('Total', 'wp-sms'); ?></td>
            <td colspan="2" class="total" style="text-align: right"><?php echo esc_html($sms_data['total']); ?></td>
        </tr>
        </tbody>
    </table>

    <!--    Subscription Report Table   -->
    <h3><?php esc_html_e('Subscription Report', 'wp-sms'); ?></h3>
    <table>
        <thead>
        <tr>
            <td style="text-align: left; border-radius: 10px 0 0 10px;"><?php esc_html_e('Group', 'wp-sms'); ?></td>
            <td><?php esc_html_e('Deactive', 'wp-sms'); ?></td>
            <td style="text-align: right; border-radius: 0 10px 10px 0;"><?php esc_html_e('Active', 'wp-sms'); ?></td>
        </tr>
        </thead>
        <tbody>

        <?php
        // Loop through groups
        foreach ($subscription_data['groups'] as $group) { ?>
            <tr>
                <td style="text-align: left"><?php echo esc_html($group['name']); ?></td>
                <td><?php echo esc_html($group['deactive']); ?></td>
                <td style="text-align: right"><?php echo esc_html($group['active']); ?></td>
            </tr>
        <?php } ?>

        <tr>
            <td style="text-align: left" class="total"><?php esc_html_e('Total', 'wp-sms'); ?></td>
            <td colspan="2" class="total" style="text-align: right"><?php echo esc_html($subscription_data['total']); ?></td>
        </tr>
        </tbody>
    </table>

    <!--    Login with SMS Table    -->
    <h3><?php esc_html_e('Login With SMS Report', 'wp-sms'); ?></h3>
    <table>
        <thead>
        <tr>
            <td style="text-align: left; border-radius: 10px 0 0 10px;"><?php esc_html_e('Type', 'wp-sms'); ?></td>
            <td style="text-align: right; border-radius: 0 10px 10px 0;"><?php esc_html_e('Count', 'wp-sms'); ?></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td style="text-align: left"><?php esc_html_e('Failed', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo esc_html($login_data['failed']); ?></td>
        </tr>
        <tr>
            <td style="text-align: left"><?php esc_html_e('Successful', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo esc_html($login_data['success']); ?></td>
        </tr>
        <tr>
            <td style="text-align: left" class="total"><?php esc_html_e('Total', 'wp-sms'); ?></td>
            <td colspan="2" class="total" style="text-align: right"><?php echo esc_html($login_data['total']); ?></td>
        </tr>
        </tbody>
    </table>

</div>