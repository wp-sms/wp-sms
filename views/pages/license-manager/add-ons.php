<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;
use WP_SMS\Components\View;

View::load('components/page-header',[
    'link'  => "#",
    'title' => esc_html__('Add-Ons', 'wp-sms')
]);
?>
<div class="postbox-container wpsms-postbox-addon-container">
    <div class="wpsms-postbox-addon">
        <?php if (!empty($data['active_addons']) && is_array($data['active_addons'])) : ?>
            <div>
                <h2 class="wpsms-postbox-addon__title"><?php esc_html_e('Active Add-Ons', 'wp-sms'); ?></h2>
                <div class="wpsms-postbox-addon__items">
                    <?php
                    /** @var PluginDecorator $addOn */
                    foreach ($data['active_addons'] as $addOn) {
                        View::load('components/addon-box', ['addOn' => $addOn]);
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($data['inactive_addons']) && is_array($data['active_addons'])) : ?>
            <div>
                <h2 class="wpsms-postbox-addon__title"><?php esc_html_e('Inactive Add-Ons', 'wp-sms'); ?></h2>
                <div class="wpsms-postbox-addon__items">
                    <?php
                    /** @var PluginDecorator $addOn */
                    foreach ($data['inactive_addons'] as $addOn) {
                        View::load('components/addon-box', ['addOn' => $addOn]);
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>