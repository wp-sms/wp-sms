<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div id="wp-sms-export-from" style="display:none;">
    <div class="wpsms-sendsms__overlay">
        <svg class="wpsms-sendsms__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
            <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"></animateTransform>
            </circle>
        </svg>
    </div>

    <!-- Show request message the client -->
    <div class="wpsms-wrap wpsms-export-popup">
        <div class="wp-sms-popup-messages js-wpSmsErrorMessage wpsms-admin-notice"></div>
    </div>

    <form class="js-wpSmsExportForm">
        <input type="hidden">
        <input class="wp-sms-export-type" type="hidden" value="subscriber">
        <table>
            <tr>
                <td>
                    <?php if (count($groups)) : ?>
                        <div class="wpsms-value wpsms-group">
                            <p class="thickbox-description"><?php esc_html_e("You can choose to export a specific group(s) by selecting them, or export all subscribers by leaving the input form blank.", 'wp-sms') ?></p>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (count($groups)) : ?>
                <tr class="subscribers_group_section">
                    <td>
                        <select aria-label="<?php esc_attr_e('Select Group', 'wp-sms'); ?>" id="wpsms_groups" name="wpsms_groups[]" multiple="true" class="js-wpsmsSelect2TickModal" data-placeholder="<?php esc_html_e('Please select the group(s).', 'wp-sms'); ?>" style="width: 100% !important;">
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo esc_attr($group->ID); ?>">
                                    <?php echo esc_html__('Group ', 'wp-sms') . esc_html($group->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td>
                    <p> <?php echo wp_kses_post(__('The data will be exported to a <code>*.csv</code> file.', 'wp-sms')); ?> </p>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" class="button-primary js-wpSmsExportButton" value="<?php esc_html_e('Export', 'wp-sms'); ?>"/>
                </td>
            </tr>
        </table>
    </form>
</div>