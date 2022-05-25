<div class="wrap wpsms-wrap">
    <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
    <div class="wpsms-wrap__main wpsms-inbox-page">
        <img class="background-img" src="<?php echo WP_SMS_URL . '/assets/images/blurred-inbox.png'; ?>" alt="">
        <div class="promotion-modal">
            <h3 class="promotion-modal__title"><?php _e('View Inbox / Incoming Messages', 'wp-sms'); ?></h3>
            <p class="promotion-modal__desc"><?php _e('This feature is available through the <b>WP SMS Two Way</b> add-on which allows you to receive messages and do actions with them such as unsubscribe, cancel order, etc.'); ?></p>
            <div class="promotion-modal__features">
                <div class="promotion-modal__feature__col">
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php _e('Store Receive Messages', 'wp-sms'); ?></div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php _e('Create Specific Commands', 'wp-sms'); ?></div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php _e('Do Actions', 'wp-sms'); ?></div>
                </div>
                <div class="promotion-modal__feature__col">
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php _e('Search Messages', 'wp-sms'); ?></div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php _e('WooCommerce Compatibility', 'wp-sms'); ?></div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> <?php _e('Subscriber Compatibility', 'wp-sms'); ?></div>
                </div>
            </div>
            <div class="promotion-modal__actions">
                <a target="_blank" href="<?php echo WP_SMS_SITE; ?>/product/wp-sms-two-way/" class="button-primary"><?php _e('More Info about WP SMS Two Way!', 'wp-sms'); ?></a>
            </div>
        </div>
    </div>
</div>
