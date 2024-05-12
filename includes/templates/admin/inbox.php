<div class="wrap wpsms-wrap">
    <?php echo \WP_SMS\Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="wpsms-wrap__main wpsms-inbox-page">
        <img class="background-img" src="<?php echo esc_url(WP_SMS_URL . '/assets/images/blurred-inbox.jpg'); ?>" alt="">
        <div class="promotion-modal">
            <h3 class="promotion-modal__title"><?php esc_html_e('View Inbox / Incoming Messages', 'wp-sms'); ?></h3>
            <div class="promotion-modal__screenshot">
                <img src="<?php echo esc_url(WP_SMS_URL . '/assets/images/wp-sms-two-way-chagemode.png'); ?>" alt="">
            </div>
            <p class="promotion-modal__desc"><?php _e('<b>Chat Mode is now live in WP SMS Two Way!</b> This powerful feature enhances your communication by allowing you'); ?></p>
            <div class="promotion-modal__features">
                <div class="promotion-modal__feature__col">
                    <div title="<?php esc_html_e('Keep a record of all incoming messages without hassle.', 'wp-sms'); ?>" class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('Store Incoming Messages', 'wp-sms'); ?></div>
                    <div title="<?php esc_html_e('Set up specific commands to trigger actions or automated responses.', 'wp-sms'); ?>" class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('Custom Commands', 'wp-sms'); ?></div>
                    <div title="<?php esc_html_e('Take immediate action, such as canceling orders, directly from the chat.', 'wp-sms'); ?>" class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('Direct Actions', 'wp-sms'); ?></div>
                </div>
                <div class="promotion-modal__feature__col">
                    <div title="<?php esc_html_e('View messages in context with the conversation display.', 'wp-sms'); ?>" class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('Maintain Conversations', 'wp-sms'); ?></div>
                    <div title="<?php esc_html_e('Integration: Perfectly sync with your WooCommerce orders for streamlined management.', 'wp-sms'); ?>" class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('WooCommerce Compatibility', 'wp-sms'); ?></div>
                    <div title="<?php esc_html_e('Ensure your subscriber lists are always up to date.', 'wp-sms'); ?>" class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php esc_html_e('Subscriber Sync', 'wp-sms'); ?></div>
                </div>
            </div>
            <div class="promotion-modal__actions">
                <a target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/product/wp-sms-two-way/?utm_source=wp-sms&utm_medium=link&utm_campaign=modal-twoway'); ?>" class="button-primary"><?php esc_html_e('Discover More About WP SMS Two Way!', 'wp-sms'); ?></a>
            </div>
        </div>
    </div>
</div>
