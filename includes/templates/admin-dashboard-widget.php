<div class='wp-sms-widgets stats-widget'>
    <div class="controls">
        <select class='sms-direction' >
            <option value="send-messages-stats">Sent SMS</option>
            <option value="received-messages-stats">Received SMS</option>
        </select>
        <select class='time-frame' >
            <option value="last_7_days">Last 7 days</option>
            <option value="last_30_days">Last 30 days</option>
            <option value="last_12_month">Last 12 month</option>
            <option value="this_year">This year</option>
        </select>
    </div>
    <table class="totals">
        <tr></tr>
    </table>
    <div class="chart">
        <canvas class='' height='200px'></canvas>
    </div>

    <div class="two-way-promotion">
        Buy <a href="<?php echo WP_SMS_SITE ?>/product/wp-sms-two-way/">WP-SMS Two-Way</a> to unlock incoming messages and actions, and even more!
    </div>
</div>