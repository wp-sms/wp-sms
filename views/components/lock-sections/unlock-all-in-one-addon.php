<div class="wp-sms-lock wp-sms-lock__addon">
    <h2 class="wp-sms-lock__title">
        <?php echo sprintf(
            esc_html__('Unlock Powerful Messaging Features with %s', 'wp-sms'),
            esc_html($addon_name)
        ) ?>
    </h2>

    <p class="wp-sms-lock__desc">
        <?php echo sprintf(
            esc_html__('The options on this screen need the %s premium add-on. It extends WP SMS so you can:', 'wp-sms'),
            esc_html($addon_name)
        ) ?>
    </p>


    <?php
    $features = [
        ['title' => __('Recover abandoned carts automatically', 'wp-sms'), 'description' => __(' send a timed reminder (and optional coupon) the moment a cart
is left behind.', 'wp-sms')],
        ['title' => __('Launch targeted SMS campaigns', 'wp-sms'), 'description' => __('segment shoppers by coupons used, products bought, or order status and message them in seconds.', 'wp-sms')],
        ['title' => __('Verify mobile numbers at checkout', 'wp-sms'), 'description' => __('stop typos and fraud with instant one-time-code verification.', 'wp-sms')],
        ['title' => __('Send real-time order & shipping updates', 'wp-sms'), 'description' => __('keep buyers in the loop from “order placed” to “out for delivery.”', 'wp-sms')],
        ['title' => __('One-tap password resets via SMS', 'wp-sms'), 'description' => __('fewer “I can’t log in” tickets, happier customers', 'wp-sms')],
    ];

    // Check if features exist
    if (!empty($features)): ?>
        <div class="wp-sms-lock__features">
                 <?php
                foreach ($features as $feature) {
                    ?>
                    <div class="wp-sms-lock__feature">
                        <div><b><?php echo esc_html($feature['title']); ?></b> – <?php echo esc_html($feature['description']); ?></div>
                    </div>
                    <?php
                }
                ?>
         </div>
    <?php endif ?>


    <div class="wp-sms-lock__footer wp-sms-lock__footer--dashed">
        <p class="wp-sms-lock__footer__desc">
            <?php echo sprintf(
                esc_html__('Get %s on its own, or save time with the All-in-One bundle that unlocks every premium
add-on we make.', 'wp-sms'),
                esc_html($addon_name)
            ) ?>
        </p>

        <div class="wp-sms-lock__actions">
            <a href="" class="wp-sms-lock__action wp-sms-lock__action--primary">
                <?php esc_html_e('Unlock Everything – All-in-One Plan', 'wp-sms') ?>
            </a>
            <a href="" class="wp-sms-lock__action wp-sms-lock__action--default">
                <?php echo sprintf(
                    esc_html__('Get %s only', 'wp-sms'),
                    esc_html($addon_name)
                ) ?>
            </a>
        </div>
        <div class="wp-sms-lock__footer__info"><?php esc_html_e('30-day money-back guarantee. Instant download & updates.', 'wp-sms') ?></div>
    </div>
</div>