<div class="c-section__title">
    <span class="c-section__step"><?php echo esc_html(sprintf(__('Step %d of 7', 'wp-sms'), $index)); ?></span>
    <h1 class="u-m-0"><?php esc_html_e('SMS Add-Ons', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php esc_html_e("Send a test SMS to the administrator's phone number to confirm everything is working as it should.", 'wp-sms'); ?>
    </p>
</div>

<div class="c-form u-flex u-content-center u-align-center u-flex--column">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <div class="c-addons__items u-flex u-content-sp u-align-stretch">
            <?php
            $addons = [
                [
                    'icon' => 'booking-integration',
                    'title' => __('WP SMS Booking Integrations', 'wp-sms'),
                    'desc' => __('WP SMS Booking Integrations is a powerful add-on for WP SMS that enables you to send and receive SMS notifications for your WordPress booking system. This add-on is compatible with some of the most popular booking plugins for WordPress, including BookingPress, WooCommerce Appointments, and Booking Calendar.', 'wp-sms'),
                    'link' => 'https://wp-sms-pro.com/product/wp-sms-booking-integrations/',
                ],
                [
                    'icon' => 'fluent-integration',
                    'title' => __('WP SMS Fluent Integrations', 'wp-sms'),
                    'desc' => __('Looking to boost your WordPress SMS notifications? Meet WP SMS Fluent Integrations, the perfect add-on for WP SMS. It effortlessly connects your WordPress website to Fluent CRM, Fluent Forms, and Fluent Support for improved communication.', 'wp-sms'),
                    'link' => 'https://wp-sms-pro.com/product/wp-sms-fluent-integrations/',
                ],
                [
                    'icon' => 'elementor',
                    'title' => __('WP SMS Elementor', 'wp-sms'),
                    'desc' => __('Get ready to supercharge your Elementor forms with the WP SMS Elementor add-on! It is the easiest way to send SMS notifications to specific phone numbers when someone fills out your form.', 'wp-sms'),
                    'link' => 'https://wp-sms-pro.com/product/wp-sms-elementor-form/',
                ],
                [
                    'icon' => 'membership-integration',
                    'title' => __('WP SMS Membership Integrations', 'wp-sms'),
                    'desc' => __('Make your membership site communication even better with our WP SMS Membership Integrations add-on.', 'wp-sms'),
                    'link' => 'https://wp-sms-pro.com/product/wp-sms-membership-integrations/',
                ],
                [
                    'icon' => 'two-way',
                    'title' => __('WP SMS Two-Way', 'wp-sms'),
                    'desc' => __('Easily receive SMS messages from your subscribers or clients with the WP SMS Two-Way add-on. This feature allows you to enable incoming messages on the inbox page.', 'wp-sms'),
                    'link' => 'https://wp-sms-pro.com/product/wp-sms-two-way/',
                ],
                [
                    'icon' => 'wooCommerce',
                    'title' => __('WP SMS WooCommerce Pro', 'wp-sms'),
                    'desc' => __('Enhance your online store with a suite of SMS-based functionalities: Login, register, and reset passwords via SMS, verify mobile numbers during checkout, launch SMS marketing campaigns, manage abandoned cart and shipping notifications through SMS.', 'wp-sms'),
                    'link' => 'https://wp-sms-pro.com/product/wp-sms-woocommerce-pro/',
                ]
            ];

            foreach ($addons as $addon): ?>
                <div class="c-addon-card u-flex u-flex--column u-content-start u-align-start">
                    <span class="c-addon__icon c-addon__icon--<?php echo esc_attr($addon['icon']); ?>"></span>
                    <h2 class="c-addon-card__title"><?php echo esc_html($addon['title']); ?></h2>
                    <p class="c-addon-card__desc"><?php echo esc_html($addon['desc']); ?></p>
                    <div class="c-addon-card__footer">
                        <span class="c-addon-card__price"><?php esc_html_e('From', 'wp-sms'); ?> <strong>$14</strong> /<?php esc_html_e('Year', 'wp-sms'); ?></span>
                        <a class="c-btn c-btn--addons-card" title="<?php esc_attr_e('Details', 'wp-sms'); ?>" target="_blank" href="<?php echo esc_url($addon['link']); ?>">
                            <?php esc_html_e('Details', 'wp-sms'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>