<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class="wp-sms-lock wp-sms-lock__addon">
    <h2 class="wp-sms-lock__title">
        <?php
        echo sprintf(
            /* translators: %s: add-on name */
            esc_html__('You Already Own %s!', 'wp-sms'),
            esc_html($addon_name)
        ) ?>
    </h2>

    <p class="wp-sms-lock__desc">
        <?php
        printf(
            /* translators: %s: license type (All-in-One) */
            esc_html__('Your %s licence includes this add-on, but it isn\'t installed/activated yet. Click below to finish setup and start using its features.', 'wp-sms'),
            '<b>' . esc_html__('All-in-One', 'wp-sms') . '</b>'
        );
        ?>
    </p>

    <div class="wp-sms-lock__footer">
        <div class="wp-sms-lock__actions">
            <a href="" class="wp-sms-lock__action wp-sms-lock__action--primary">
                <?php esc_html_e('Go to Add-Ons Page', 'wp-sms') ?>
            </a>
        </div>
        <div class="wp-sms-lock__footer__info">
            <?php
            printf(
                /* translators: %s: setup guide link */
                esc_html__('Need a hand? %s', 'wp-sms'),
                '<a href="">' . esc_html__('See the setup guide', 'wp-sms') . '</a>'
            );
            ?>
        </div>
    </div>
</div>
