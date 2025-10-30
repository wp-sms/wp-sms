<div class="wpsms-wrap__main">
    <div class="wp-header-end"></div>

    <div class="wpsms-postbox-addon__step">
        <div class="wpsms-addon__step__info">
            <span class="wpsms-addon__step__image wpsms-addon__step__image--lock"></span>
            <h2 class="wpsms-addon__step__title"><?php esc_html_e('Unlock All-in-One', 'wp-sms'); ?></h2>
            <p class="wpsms-addon__step__desc"><?php esc_html_e('Enter your license key to unlock add-ons and enhance your experience.', 'wp-sms'); ?></p>
        </div>
        <div class="wpsms-addon__step__license">
            <div class="wpsms-addon__step__active-license">
                <!--   Add wpsms-danger or wpsms-warning class to input-->
                <input type="text" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" aria-label="License">
                <button class="wpsms-postbox-addon-button js-addon-active-license disabled" disabled aria-disabled="true"><?php esc_html_e('Activate License', 'wp-sms'); ?></button>
            </div>
        </div>
        <div class="wpsms-addon__step__faq">
            <ul>
                <li>
                    <a href="https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon" target="_blank" aria-label="Buy All-in-One Now"><?php esc_html_e('Buy All-in-One Now', 'wp-sms'); ?></a>
                </li>
                <li>
                    <a href="https://wp-sms-pro.com/resources/finding-and-entering-your-license-key/?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon"  aria-label="I bought Premium, where is my license key?"
                       target="_blank"><?php esc_html_e('I bought All-in-One, where is my license key?', 'wp-sms'); ?></a>
                </li>
                <li>
                    <a href="https://wp-sms-pro.com/contact-us/?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon" aria-label="Have questions or trouble activating your license?"
                       target="_blank"><?php esc_html_e('Have a question or trouble with your license?', 'wp-sms'); ?></a>
                </li>
            </ul>
        </div>
        <a class="wpsms-addon__step__back-to-addons" aria-label="Back to Add-ons"  href="<?php echo esc_url(admin_url('admin.php?page=wp-sms-add-ons')) ?>"><?php esc_html_e('Back to Add-Ons', 'wp-sms'); ?></a>

    </div>
</div>