<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class='wp-sms-widgets stats-widget'>
    <div class="stats-widget__controls">
        <?php
        /**
         * Filter: wpsms_stats_direction_options
         * Allows addons to add/remove SMS direction options.
         */
        $directionOptions = apply_filters('wpsms_stats_direction_options', [
            'send-messages-stats' => __('Sent SMS', 'wp-sms'),
        ]);
        $hasMultipleDirections = count($directionOptions) > 1;
        ?>
        <select class='sms-direction stats-widget__direction<?php echo !$hasMultipleDirections ? ' stats-widget__direction--hidden' : ''; ?>'>
            <?php foreach ($directionOptions as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>">
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="stats-widget__timeframe" role="tablist" aria-label="<?php esc_attr_e('Time frame', 'wp-sms'); ?>">
            <button type="button" role="tab" class="stats-widget__pill stats-widget__pill--active" data-value="last_7_days" aria-selected="true" tabindex="0"><?php esc_html_e('7D', 'wp-sms'); ?></button>
            <button type="button" role="tab" class="stats-widget__pill" data-value="last_30_days" aria-selected="false" tabindex="-1"><?php esc_html_e('30D', 'wp-sms'); ?></button>
            <button type="button" role="tab" class="stats-widget__pill" data-value="last_12_month" aria-selected="false" tabindex="-1"><?php esc_html_e('12M', 'wp-sms'); ?></button>
            <button type="button" role="tab" class="stats-widget__pill" data-value="this_year" aria-selected="false" tabindex="-1"><?php esc_html_e('YTD', 'wp-sms'); ?></button>
        </div>
    </div>
    <div class="stats-widget__stats">
        <span class="stats-widget__stat" data-stat="total">
            <span class="stats-widget__stat-value" data-value>0</span>
            <span class="stats-widget__stat-label" data-label><?php esc_html_e('Total', 'wp-sms'); ?></span>
        </span>
        <span class="stats-widget__stat" data-stat="successful">
            <span class="stats-widget__dot stats-widget__dot--success"></span>
            <span class="stats-widget__stat-value stats-widget__stat-value--success" data-value>0</span>
            <span class="stats-widget__stat-label" data-label><?php esc_html_e('Successful', 'wp-sms'); ?></span>
        </span>
        <span class="stats-widget__stat" data-stat="failure">
            <span class="stats-widget__dot stats-widget__dot--failed"></span>
            <span class="stats-widget__stat-value stats-widget__stat-value--failed" data-value>0</span>
            <span class="stats-widget__stat-label" data-label><?php esc_html_e('Failed', 'wp-sms'); ?></span>
        </span>
        <span class="stats-widget__stat" data-stat="success_rate">
            <span class="stats-widget__stat-value" data-value>0%</span>
        </span>
    </div>
    <div class="stats-widget__chart">
        <canvas height='200'></canvas>
    </div>
    <div class="two-way-promotion" style="display:none;">
    </div>
</div>
