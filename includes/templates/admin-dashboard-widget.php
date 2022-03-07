<div class='wp-sms-widgets stats-widget'>
    <div class="controls">
        <select class='sms-direction'>
            <option value="send-messages-stats"><?= __('Sent SMS', 'wp-sms') ?></option>
            <option value="received-messages-stats"><?= __('Received SMS', 'wp-sms') ?></option>
        </select>
        <select class='time-frame'>
            <option value="last_7_days"><?= __('Last 7 days', 'wp-sms') ?></option>
            <option value="last_30_days"><?= __('Last 30 days', 'wp-sms') ?></option>
            <option value="last_12_month"><?= __('Last 12 month', 'wp-sms') ?></option>
            <option value="this_year"><?= __('This year', 'wp-sms') ?></option>
        </select>
    </div>
    <table class="totals">
        <tr></tr>
    </table>
    <div class="chart">
        <canvas class='' height='200px'></canvas>
    </div>

    <div class="two-way-promotion">
        <h2>View incoming messages activity inside WordPress Dashboard</h2>
        <p>Store Incoming Messages, Create new Commands for your customers, do actions, and more!</p>
        <p><a href="<?= WP_SMS_SITE ?>/product/wp-sms-two-way/" class="button-primary wpsms-primary-button" target="_blank">Read More WP-SMS Two Way!</a></p>
    </div>
</div>