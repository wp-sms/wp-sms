<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class='wp-sms-widgets stats-widget'>
    <div class="controls">
        <?php
        /**
         * Filter: wpsms_stats_direction_options
         * Allows addons to add/remove SMS direction options.
         */
        $directionOptions = apply_filters('wpsms_stats_direction_options', [
            'send-messages-stats' => __('Sent SMS', 'wp-sms'),
        ]);
        ?>
        <select class='sms-direction'>
            <?php foreach ($directionOptions as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>">
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select class='time-frame'>
            <option value="last_7_days"><?php esc_html_e('Last 7 days', 'wp-sms') ?></option>
            <option value="last_30_days"><?php esc_html_e('Last 30 days', 'wp-sms') ?></option>
            <option value="last_12_month"><?php esc_html_e('Last 12 month', 'wp-sms') ?></option>
            <option value="this_year"><?php esc_html_e('This year', 'wp-sms') ?></option>
        </select>
    </div>
    <table class="totals">
        <tr></tr>
    </table>
    <div class="chart">
        <canvas class='' height='200px'></canvas>
    </div>
</div>