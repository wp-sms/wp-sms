<?php

use WP_Statistics\Components\View;

?>
<div class="wpsms-notification-sidebar">
    <div class="wpsms-notification-sidebar__menu">
        <div class="wpsms-notification-sidebar__header">
            <div>
                <h2 class="wpsms-notification-sidebar__title"><?php esc_html_e('Notifications', 'wp-statistics'); ?></h2>
                <span class="wpsms-notification-sidebar__close"></span>
            </div>
            <div>
                <ul class="wpsms-notification-sidebar__tabs">
                    <li class="wpsms-notification-sidebar__tab wpsms-notification-sidebar__tab--active"
                        data-tab="tab-1"><?php esc_html_e('Inbox', 'wp-statistics'); ?></li>
                    <li class="wpsms-notification-sidebar__tab"
                        data-tab="tab-2"><?php esc_html_e('Dismissed', 'wp-statistics'); ?></li>
                </ul>

                <?php if (!empty($notifications)) : ?>
                    <?php
                    $hasNotifications = false;
                    foreach ($notifications as $notification) {
                        if (!$notification->getDismiss()) {
                            $hasNotifications = true;
                            break;
                        }
                    }
                    ?>
                    <?php if ($hasNotifications) : ?>
                        <a href="#"
                           class="wpsms-notification-sidebar__dismiss-all"><?php esc_html_e('Dismiss all', 'wp-statistics'); ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="wpsms-notification-sidebar__content">
            <div class="wpsms-notification-sidebar__tab-pane wpsms-notification-sidebar__tab-pane--active" id="tab-1">
                <div class="wpsms-notification-sidebar__cards wpsms-notification-sidebar__cards--active">
                    <?php
                    $hasNotifications = false;
                    if (!empty($notifications)) :
                        foreach ($notifications as $notification) :
                            if ($notification->getDismiss()) continue;
                            $hasNotifications = true;
                            View::load("components/notification/card", ['notification' => $notification]);
                        endforeach;
                        View::load("components/notification/no-data", ['tab' => __('inbox', 'wp-statistics')]);
                    endif;
                    if (!$hasNotifications) {
                        View::load("components/notification/no-data", ['tab' => __('inbox', 'wp-statistics')]);
                    }
                    ?>
                </div>
            </div>
            <div class="wpsms-notification-sidebar__tab-pane" id="tab-2">
                <div class="wpsms-notification-sidebar__cards wpsms-notification-sidebar__cards--dismissed">
                    <?php
                    $hasDismissed = false;
                    if (!empty($notifications)) :
                        foreach ($notifications as $notification) :
                            if (!$notification->getDismiss()) continue;
                            $hasDismissed = true;
                            View::load("components/notification/card", ['notification' => $notification]);
                        endforeach;
                        View::load("components/notification/no-data", ['tab' => __('dismissed list', 'wp-statistics')]);
                    endif;
                    if (!$hasDismissed) {
                        View::load("components/notification/no-data", ['tab' => __('dismissed list', 'wp-statistics')]);
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wpsms-notification-sidebar__overlay"></div>
</div>