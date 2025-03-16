<div class="c-section__title u-border-b">
    <span class="c-section__step">
        <?php echo esc_html(sprintf(__('Step %d of 6', 'wp-sms'), $index)); ?>
    </span>
    <h1 class="u-m-0 u-text-orange">
        <?php esc_html_e('Welcome to WP SMS!', 'wp-sms'); ?>
    </h1>
    <p class="u-m-0">
        <?php esc_html_e('Set up SMS functionality for your WordPress site by following a few simple steps.', 'wp-sms'); ?>
    </p>
</div>
<div class="c-form c-form--medium u-flex u-content-center">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <p class="c-form__title">
            <?php esc_html_e('Get Notifications Where You Need Them', 'wp-sms'); ?>
        </p>
        <div class="c-form__fieldgroup u-mb-32">
            <label for="countries">
                <?php esc_html_e('Country Code', 'wp-sms'); ?>
                <span class="u-text-red">*</span>
            </label>
            <div class="wpsms-skeleton wpsms-skeleton__select wpsms-skeleton__select--step1"></div>
            <select id="countries" name="countries">
                <option value="global">
                    <?php esc_html_e('No country code (Global)', 'wp-sms'); ?>
                </option>
                <?php
                $countries            = wp_sms_countries()->getCountriesMerged();
                $current_country_code = \WP_SMS\Option::getOption('admin_mobile_number_prefix');
                $current_tel_raw     = \WP_SMS\Option::getOption('admin_mobile_number_raw');

                foreach ($countries as $code => $country) {
                    $selected = ($current_country_code === esc_attr($code)) ? 'selected' : '';
                    echo '<option ' . esc_attr($selected) . ' value="' . esc_attr($code) . '">' . esc_html($country) . '</option>';
                }
                ?>
            </select>
            <p class="c-form__description">
                <?php esc_html_e("Choose your country code from the list for accurate delivery. If your number is international and doesn't fit any specific country code, select 'Global'.", 'wp-sms'); ?>
            </p>
        </div>
        <div class="c-form__fieldgroup u-mb-38">
            <label for="tel">
                <?php esc_html_e('Your Mobile Number', 'wp-sms'); ?> <span class="u-text-red">*</span>
            </label>
            <input value="<?php echo esc_attr($current_tel_raw); ?>" name="tel" id="tel" placeholder="<?php esc_attr_e('Enter your mobile number', 'wp-sms'); ?>" type="tel" required/>
            <p class="c-form__description">
                <?php esc_html_e("Enter the phone number where you'd like to receive management alerts.", 'wp-sms'); ?>
            </p>
        </div>
        <div class="c-form__footer u-flex-end">
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>