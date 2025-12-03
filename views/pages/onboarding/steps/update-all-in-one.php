<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class="c-section__title">
    <span class="c-section__step"><?php
        /* translators: 1: current step number 2: total number of steps */
        echo esc_html(sprintf(__('Step %1$d of %2$d', 'wp-sms'), $index, $total_steps));
        ?></span>
    <h1 class="u-m-0"><?php esc_html_e('Level Up Your WP SMS Experience', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php esc_html_e("You already enjoy WP SMS free—now unlock its full potential with WP SMS All‑in‑One. Get every premium add‑on, expanded gateway support, advanced WooCommerce notifications, membership integrations, and much more in a single plan.", 'wp-sms'); ?>
    </p>
</div>
<div class="c-form u-flex u-content-center u-align-center u-flex--column">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <div class="c-form__fieldgroup">
            <div class="c-proplan">
                <div class="c-proplan__header u-flex u-content-center u-align-center u-flex--column">
                    <div>
                        <span class="c-proplan__icon"></span>
                        <h3 class="c-proplan__title"><?php esc_html_e('All-in-One Plan', 'wp-sms'); ?></h3>
                    </div>

                    <p class="c-proplan__price u-text-center">
                        <?php
                        /* translators: %s: price amount */
                        printf(__('From <strong>$%s</strong> per Year', 'wp-sms'), '59'); ?>
                    </p>
                    <div>
                        <a class="c-btn c-btn--ghost c-btn--proplan" title="<?php esc_attr_e('Buy now', 'wp-sms'); ?>" href="<?php echo esc_url('https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" target="_blank">
                            <?php esc_html_e('Buy now', 'wp-sms'); ?>
                        </a>
                        <p class="c-proplan__desc"><?php esc_html_e('14-day money-back', 'wp-sms'); ?><br><?php esc_html_e('guarantee on all plans.', 'wp-sms'); ?></p>
                    </div>
                </div>
                <div class="c-proplan__features u-flex u-content-sp u-align-stretch">
                    <p class="c-proplan__features__title"><?php esc_html_e('What You’ll Unlock:', 'wp-sms'); ?></p>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg fill="none" height="16" width="16" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.333 2.167h13.334V.5H1.333v1.667ZM14.667 5.5H1.333V3.833h13.334V5.5ZM.5 7.167h5.833v1.666h3.334V7.167H15.5v7.5c0 .46-.373.833-.833.833H1.333a.833.833 0 0 1-.833-.833v-7.5Zm10.833 1.666V10.5H4.667V8.833h-2.5v5h11.666v-5h-2.5Z" fill="#242121"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('300+ Supported Gateways', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Reach customers globally with reliable SMS delivery.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg width="18" height="15" viewBox="0 0 18 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.49984 15C1.0396 15 0.666504 14.6269 0.666504 14.1667V0.833333C0.666504 0.3731 1.0396 0 1.49984 0H7.67834L9.345 1.66667H15.6665C16.1268 1.66667 16.4998 2.03977 16.4998 2.5V5H14.8332V3.33333H8.65467L6.988 1.66667H2.33317V11.665L3.58317 6.66667H17.7498L15.8243 14.3687C15.7316 14.7397 15.3983 15 15.0158 15H1.49984ZM15.6152 8.33333H4.88446L3.63446 13.3333H14.3652L15.6152 8.33333Z" fill="black"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('Advanced WooCommerce', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Cart abandonment recovery, detailed order alerts, and instant SMS from your Edit Order screen.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg fill="none" height="16" viewBox="0 0 18 16" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M9.34525 1.96665H16.5001C16.9603 1.96665 17.3334 2.33975 17.3334 2.79999V14.4667C17.3334 14.9269 16.9603 15.3 16.5001 15.3H1.50008C1.03985 15.3 0.666748 14.9269 0.666748 14.4667V1.13332C0.666748 0.673088 1.03985 0.299988 1.50008 0.299988H7.67858L9.34525 1.96665ZM2.33341 1.96665V13.6333H15.6667V3.63332H8.65491L6.98824 1.96665H2.33341ZM6.15936 9.3074C6.10786 9.09049 6.08061 8.86415 6.08061 8.63149C6.08061 8.39882 6.10786 8.17257 6.15935 7.95565L5.3334 7.47882L6.16632 6.03611L6.99286 6.51332C7.32028 6.20357 7.7195 5.96895 8.16291 5.83697V4.88332H9.82875V5.83697C10.2722 5.96894 10.6714 6.20357 10.9988 6.51324L11.8254 6.03603L12.6584 7.47874L11.8323 7.95557C11.8838 8.17249 11.9111 8.39882 11.9111 8.63149C11.9111 8.86415 11.8838 9.0904 11.8323 9.30732L12.6584 9.78424L11.8255 11.2269L10.9989 10.7497C10.6715 11.0594 10.2722 11.294 9.82883 11.426V12.3797H8.163V11.4261C7.71958 11.2941 7.32036 11.0595 6.99292 10.7497L6.16636 11.227L5.3334 9.78432L6.15936 9.3074ZM8.99583 9.8809C9.68583 9.8809 10.2452 9.32149 10.2452 8.63149C10.2452 7.94149 9.68583 7.38207 8.99583 7.38207C8.30583 7.38207 7.7465 7.94149 7.7465 8.63149C7.7465 9.32149 8.30583 9.8809 8.99583 9.8809Z"
                                fill="#242121"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('Two‑Way Messaging', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Receive replies directly in WordPress and let customers manage orders via text commands.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg fill="none" height="19" viewBox="0 0 16 19" width="16" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 0.433289L14.8474 1.95494C15.2287 2.03967 15.5 2.37785 15.5 2.76843V11.0907C15.5 12.7625 14.6645 14.3236 13.2735 15.251L8 18.7666L2.7265 15.251C1.33551 14.3236 0.5 12.7625 0.5 11.0907V2.76843C0.5 2.37785 0.771275 2.03967 1.15256 1.95494L8 0.433289ZM8 2.14061L2.16667 3.43691V11.0907C2.16667 12.2052 2.72367 13.246 3.651 13.8642L8 16.7635L12.349 13.8642C13.2763 13.246 13.8333 12.2052 13.8333 11.0907V3.43691L8 2.14061ZM11.7103 6.45148L12.8888 7.62999L7.5855 12.9333L4.04999 9.39779L5.22851 8.21921L7.58492 10.5757L11.7103 6.45148Z" fill="#242121"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('Membership & Booking Integrations', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Automate sign‑up confirmations, cancellations, and booking reminders.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg fill="none" height="17" viewBox="0 0 18 17" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.50008 0.233337V1.9H11.5001V0.233337H13.1667V1.9H16.5001C16.9603 1.9 17.3334 2.2731 17.3334 2.73334V16.0667C17.3334 16.5269 16.9603 16.9 16.5001 16.9H1.50008C1.03985 16.9 0.666748 16.5269 0.666748 16.0667V2.73334C0.666748 2.2731 1.03985 1.9 1.50008 1.9H4.83341V0.233337H6.50008ZM15.6667 6.06667H2.33341V15.2333H15.6667V6.06667ZM11.5297 7.84667L12.7082 9.02525L8.58341 13.15L5.63714 10.2038L6.81565 9.02525L8.58341 10.793L11.5297 7.84667Z" fill="#242121"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('Scheduled & Repeating SMS', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Pre‑plan messages or run recurring campaigns to stay top of mind.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg fill="none" height="17" viewBox="0 0 18 17" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.37887 14.0333L0.666748 16.95V1.53335C0.666748 1.07311 1.03985 0.700012 1.50008 0.700012H16.5001C16.9603 0.700012 17.3334 1.07311 17.3334 1.53335V13.2C17.3334 13.6603 16.9603 14.0333 16.5001 14.0333H4.37887ZM3.80243 12.3667H15.6667V2.36668H2.33341V13.5209L3.80243 12.3667ZM8.41083 8.3011L11.9463 4.76558L13.1248 5.94409L8.41083 10.6581L5.16992 7.41726L6.34843 6.23872L8.41083 8.3011Z" fill="#242121"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('2FA & Mobile Login', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Secure your site with SMS‑based two‑factor authentication and phone‑number logins.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                    <div class="c-feature-card u-flex u-content-start u-align-start">
                        <svg fill="none" height="18" viewBox="0 0 18 18" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M9.00008 0.666626C13.6024 0.666626 17.3334 4.39758 17.3334 8.99996C17.3334 13.6023 13.6024 17.3333 9.00008 17.3333C4.39771 17.3333 0.666748 13.6023 0.666748 8.99996C0.666748 4.39758 4.39771 0.666626 9.00008 0.666626ZM9.00008 13.1666C8.46258 13.1666 7.94891 13.0649 7.47716 12.8795L5.61345 14.7435C6.60597 15.33 7.76375 15.6666 9.00008 15.6666C10.2364 15.6666 11.3942 15.33 12.3867 14.7435L10.523 12.8795C10.0512 13.0649 9.53758 13.1666 9.00008 13.1666ZM2.33341 8.99996C2.33341 10.2363 2.66996 11.394 3.25646 12.3866L5.12051 10.5229C4.93519 10.0511 4.83341 9.53746 4.83341 8.99996C4.83341 8.46246 4.93519 7.94879 5.12051 7.47704L3.25646 5.61333C2.66996 6.60585 2.33341 7.76363 2.33341 8.99996ZM14.7437 5.61333L12.8797 7.47704C13.065 7.94879 13.1667 8.46246 13.1667 8.99996C13.1667 9.53746 13.065 10.0511 12.8797 10.5229L14.7437 12.3866C15.3302 11.394 15.6667 10.2363 15.6667 8.99996C15.6667 7.76363 15.3302 6.60585 14.7437 5.61333ZM9.00008 6.49996C7.61933 6.49996 6.50008 7.61921 6.50008 8.99996C6.50008 10.3807 7.61933 11.5 9.00008 11.5C10.3808 11.5 11.5001 10.3807 11.5001 8.99996C11.5001 7.61921 10.3808 6.49996 9.00008 6.49996ZM9.00008 2.33329C7.76375 2.33329 6.60597 2.66984 5.61345 3.25633L7.47716 5.12038C7.94891 4.93507 8.46258 4.83329 9.00008 4.83329C9.53758 4.83329 10.0512 4.93507 10.523 5.12038L12.3867 3.25633C11.3942 2.66984 10.2364 2.33329 9.00008 2.33329Z"
                                fill="#242121"/>
                        </svg>
                        <div class="c-feature-card__content">
                            <h2 class="c-feature-card__title"><?php esc_html_e('1 Year Updates & Support', 'wp-sms'); ?></h2>
                            <p class="c-feature-card__desc u-m-0"><?php esc_html_e('Get dedicated help and new features as soon as they launch.', 'wp-sms'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php esc_html_e('Maybe Later', 'wp-sms'); ?>"/>
        </div>
    </form>
</div>