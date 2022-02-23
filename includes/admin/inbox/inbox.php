<div class="wrap wpsms-wrap">
    <div class="wpsms-wrap__main wpsms-inbox-page">
        <img class="background-img" src="<?= WP_SMS_URL . '/assets/images/blurred-inbox.png' ?>" alt="">
        <div class="promotion-modal">
            <h3 class="promotion-modal__title"><?php _e('View Inbox / Incoming Messages', 'wp-sms'); ?></h3>
			<p class="promotion-modal__desc"><?= __('This feature is available through the WP-SMS Two-Way add-on which allows you to receive messages and do actions with them such as unsubscribe, cancel order, etc...'); ?></p>
            <div class="promotion-modal__features">
                <div class="promotion-modal__feature__col">
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> Fast Server Redirects</div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> Automatic Redirects</div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> Redirect Monitoring</div>
                </div>
                <div class="promotion-modal__feature__col">
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> 404 Monitoring</div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> Full Site Redirects</div>
                    <div class="promotion-modal__features__item"><span class="dashicons dashicons-saved"></span> Site Aliases</div>
                </div>
            </div>
            <div class="promotion-modal__actions">
                <a target="_blank" href="<?php echo WP_SMS_SITE; ?>/product/wp-sms-two-way/" class="button-primary">More Info about WP-SMS Two-Way</a>
                <p>
                    <a class="link" href="https://wp-sms-pro.com/features">Learn more about all features</a>
                </p>
            </div>
        </div>
    </div>
</div>
