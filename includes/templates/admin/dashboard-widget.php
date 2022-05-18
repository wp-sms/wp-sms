<div class='wp-sms-widgets stats-widget'>
    <div class="controls">
        <select class='sms-direction'>
            <option value="send-messages-stats"><?php _e('Sent SMS', 'wp-sms') ?></option>
            <option value="received-messages-stats"><?php _e('Received SMS', 'wp-sms') ?></option>
        </select>
        <select class='time-frame'>
            <option value="last_7_days"><?php _e('Last 7 days', 'wp-sms') ?></option>
            <option value="last_30_days"><?php _e('Last 30 days', 'wp-sms') ?></option>
            <option value="last_12_month"><?php _e('Last 12 month', 'wp-sms') ?></option>
            <option value="this_year"><?php _e('This year', 'wp-sms') ?></option>
        </select>
    </div>
    <table class="totals">
        <tr></tr>
    </table>
    <div class="chart">
        <canvas class='' height='200px'></canvas>
    </div>

    <div class="two-way-promotion">
        <h2><?php _e('View incoming messages activity inside WordPress Dashboard 🤩', 'wp-sms'); ?></h2>
        <p><?php _e('Store Incoming Messages, Create new Commands for your customers, do actions, and more!', 'wp-sms'); ?></p>
        <p><a href="<?php echo WP_SMS_SITE ?>/product/wp-sms-two-way/" class="button-primary" target="_blank"><?php _e('Read More WP SMS Two Way!', 'wp-sms'); ?></a></p>
    </div>
</div>