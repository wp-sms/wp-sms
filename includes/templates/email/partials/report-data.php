<!--    Graphical Report    -->
<div class="graphical-reports">
    <h4><?php echo sprintf(__('From %s to %s 2023', 'wp-sms'), $duration['startDate'], $duration['endDate']); ?></h4>
    <h5><?php _e('At a glance', 'wp-sms'); ?></h5>

    <table>
        <tr>
            <td>
                <img src="<?php echo WP_SMS_URL . 'assets/images/icons/success-sms.png'; ?>" class="icon"/>
                <span class="value"><?php echo $sms_data['success']; ?></span>
                <span class="name"><?php _e('Successful SMS', 'wp-sms'); ?></span>
            </td>
            <td>
                <img src="<?php echo WP_SMS_URL . 'assets/images/icons/failed-sms.png'; ?>" class="icon"/>
                <span class="value"><?php echo $sms_data['failed']; ?></span>
                <span class="name"><?php _e('Failed SMS', 'wp-sms'); ?></span>
            </td>
        </tr>
        <tr>
            <td>
                <img src="<?php echo WP_SMS_URL . 'assets/images/icons/active-user.png'; ?>" class="icon"/>
                <span class="value"><?php echo $subscription_data['activeSubscribers']; ?></span>
                <span class="name"><?php _e('New Active Subscribers SMS', 'wp-sms'); ?></span>
            </td>
            <td>
                <img src="<?php echo WP_SMS_URL . 'assets/images/icons/added-user.png'; ?>" class="icon"/>
                <span class="value"><?php echo $subscription_data['deactiveSubscribers']; ?></span>
                <span class="name"><?php _e('New Deactive Subscribers', 'wp-sms'); ?></span>
            </td>
        </tr>
    </table>
</div>

<div class="table-reports">

    <!--    SMS Delivery Report Table   -->
    <h3><?php _e('SMS Delivery Report', 'wp-sms'); ?></h3>
    <table>
        <thead>
        <tr>
            <td style="text-align: left; border-radius: 10px 0 0 10px;"><?php _e('Count', 'wp-sms'); ?></td>
            <td style="text-align: right; border-radius: 0 10px 10px 0;"><?php _e('Status', 'wp-sms'); ?></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td style="text-align: left"><?php _e('Failed SMS', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo $sms_data['failed']; ?></td>
        </tr>
        <tr>
            <td style="text-align: left"><?php _e('Successful SMS', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo $sms_data['success']; ?></td>
        </tr>
        <tr>
            <td class="total" style="text-align: left"><?php _e('Total', 'wp-sms'); ?></td>
            <td colspan="2" class="total" style="text-align: right"><?php echo $sms_data['total']; ?></td>
        </tr>
        </tbody>
    </table>

    <!--    Subscription Report Table   -->
    <h3><?php _e('Subscription Report', 'wp-sms'); ?></h3>
    <table>
        <thead>
        <tr>
            <td style="text-align: left; border-radius: 10px 0 0 10px;"><?php _e('Group', 'wp-sms'); ?></td>
            <td><?php _e('Deactive', 'wp-sms'); ?></td>
            <td style="text-align: right; border-radius: 0 10px 10px 0;"><?php _e('Active', 'wp-sms'); ?></td>
        </tr>
        </thead>
        <tbody>

        <?php
        // Loop through groups
        foreach ($subscription_data['groups'] as $group) { ?>
            <tr>
                <td style="text-align: left"><?php echo esc_html($group['name']); ?></td>
                <td><?php echo esc_html($group['active']); ?></td>
                <td style="text-align: right"><?php echo esc_html($group['deactive']); ?></td>
            </tr>
        <?php } ?>

        <tr>
            <td style="text-align: left" class="total"><?php _e('Total', 'wp-sms'); ?></td>
            <td colspan="2" class="total" style="text-align: right"><?php echo $subscription_data['total']; ?></td>
        </tr>
        </tbody>
    </table>

    <!--    Login with SMS Table    -->
    <h3><?php _e('Login With SMS Report', 'wp-sms'); ?></h3>
    <table>
        <thead>
        <tr>
            <td style="text-align: left; border-radius: 10px 0 0 10px;"><?php _e('Type', 'wp-sms'); ?></td>
            <td style="text-align: right; border-radius: 0 10px 10px 0;"><?php _e('Count', 'wp-sms'); ?></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td style="text-align: left"><?php _e('Failed', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo $login_data['failed']; ?></td>
        </tr>
        <tr>
            <td style="text-align: left"><?php _e('Successful', 'wp-sms'); ?></td>
            <td style="text-align: right"><?php echo $login_data['success']; ?></td>
        </tr>
        <tr>
            <td style="text-align: left" class="total"><?php _e('Total', 'wp-sms'); ?></td>
            <td colspan="2" class="total" style="text-align: right"><?php echo $login_data['total']; ?></td>
        </tr>
        </tbody>
    </table>

</div>