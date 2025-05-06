<div class="wp-sms-lock wp-sms-lock__addon">
    <h2 class="wp-sms-lock__title">
        <?php echo sprintf(
            esc_html__('Unlock Powerful Messaging Features with %s', 'wp-sms'),
            esc_html($addon_name)
        ) ?>
    </h2>

    <p class="wp-sms-lock__desc">
        <?php echo sprintf(
            esc_html__('The options on this screen need the %s add-on. It extends WP SMS so you can:', 'wp-sms'),
            esc_html($addon_name)
        ) ?>
    </p>


    <?php

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
            <a href="<?php echo esc_url('https://wp-sms-pro.com/pricing/'); ?>" class="wp-sms-lock__action wp-sms-lock__action--primary">
                <?php echo esc_html__('Unlock Everything – All-in-One Plan', 'wp-sms'); ?>
            </a>

            <a href="<?php echo esc_url($addon_url); ?>" class="wp-sms-lock__action wp-sms-lock__action--default">
                <?php
                printf(
                    esc_html__('Get %s only', 'wp-sms'),
                    esc_html($addon_name)
                );
                ?>
            </a>
        </div>
        <div class="wp-sms-lock__footer__info"><?php esc_html_e('30-day money-back guarantee. Instant download & updates.', 'wp-sms') ?></div>
    </div>
</div>