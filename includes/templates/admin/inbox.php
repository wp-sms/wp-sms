<div class="wrap wpsms-wrap">
    <?php echo \WP_SMS\Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="wpsms-wrap__main wpsms-inbox-page wpsms-inbox-page__empty">
        <img class="background-img" src="<?php echo esc_url(WP_SMS_URL . '/assets/images/blurred-inbox.jpg'); ?>" alt="">
        <div class="promotion-modal">
            <h3 class="promotion-modal__title"><?php esc_html_e('Inbox: turn SMS into live, two-way conversations', 'wp-sms'); ?></h3>
            <p class="promotion-modal__desc">
                <?php esc_html_e('Add the Two-Way Messaging engine (included with the All-in-One Suite or available on its own) and your WP Inbox becomes a real-time chat window.', 'wp-sms'); ?>
            </p>
            <div class="promotion-modal__screenshot">
                <img src="<?php echo esc_url(WP_SMS_URL . '/assets/images/wp-sms-two-way-chagemode.png'); ?>" alt="">
            </div>
            <p class="promotion-modal__features__title"><?php echo esc_html__('What you’ll get', 'wp-sms'); ?></p>
            <div class="promotion-modal__features">
                <div class="promotion-modal__features__item"><?php esc_html_e('Reply to customer texts without leaving WordPress', 'wp-sms'); ?></div>
                <div class="promotion-modal__features__item"><?php esc_html_e('Every inbound & outbound message stored in one clean thread', 'wp-sms'); ?></div>
                <div class="promotion-modal__features__item"><?php esc_html_e('One-tap commands and auto-replies', 'wp-sms'); ?></div>
                <div class="promotion-modal__features__item"><?php esc_html_e('WooCommerce order updates customers can answer', 'wp-sms'); ?></div>
            </div>

            <div class="promotion-modal__chat">
                <p class="promotion-modal__chat__title"><?php echo esc_html__('Ready to chat?', 'wp-sms'); ?></p>
                <ul>
                    <li>
                        <?php echo sprintf('<a target="_blank" href="%s">%s</a> %s',
                            esc_url(WP_SMS_SITE . '/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=modal-twoway'),
                            esc_html__('Unlock the All-in-One', 'wp-sms'),
                            esc_html__('— every premium add-on, Two-Way included', 'wp-sms')) ?>
                    </li>
                    <li>
                        <?php echo sprintf('%s <a target="_blank" href="%s">%s</a>',
                            esc_html__('Just need conversations?', 'wp-sms'),
                            esc_url(WP_SMS_SITE . '/product/wp-sms-two-way/?utm_source=wp-sms&utm_medium=link&utm_campaign=modal-twoway'),
                            esc_html__('Get the Two-Way add-on', 'wp-sms')) ?>
                    </li>
                </ul>
            </div>
            <div class="promotion-modal__footer">
                <?php echo sprintf('%s <a href="%s">%s</a> %s',
                    esc_html__('Already licensed?', 'wp-sms'),
                    esc_url(admin_url('admin.php?page=wp-sms-add-ons')),
                    esc_html__('Activate now', 'wp-sms'),
                    esc_html__('and start chatting.', 'wp-sms')) ?>
            </div>
        </div>
    </div>
</div>
