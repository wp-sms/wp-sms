<div class="c-section__title u-border-b">
    <span class="c-section__step"><?php printf(__('Step %d of 7', 'wp-sms'), $index); ?></span>
    <h1 class="u-m-0 u-text-orange"><?php _e('Welcome to WP SMS!', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php _e('Set up SMS functionality for your WordPress site by following a few simple steps.', 'wp-sms'); ?>
    </p>
</div>
<div class="c-form c-form--medium u-flex u-content-center">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <p class="c-form__title"><?php _e('Get Notifications Where You Need Them', 'wp-sms'); ?></p>
        <div class="c-form__fieldgroup u-mb-32">
            <label for="countries">
                <?php _e('Country Code', 'wp-sms'); ?>
                <span data-tooltip="<?php esc_attr_e('tooltip data', 'wp-sms'); ?>" data-tooltip-font-size="12px">
                    <i class="wps-tooltip-icon"></i>
                </span>
                <span class="u-text-red">*</span>
            </label>
            <div class="wpsms-skeleton wpsms-skeleton__select wpsms-skeleton__select--step1"></div>
            <select id="countries" name="countries">
                <option value="Global"><?php _e('No country code (Global)', 'wp-sms'); ?></option>
                <?php
                $countries = ['Albania', 'Algeria', 'Andorra', 'Angola'];
                foreach ($countries as $country) {
                    echo '<option value="' . esc_attr($country) . '">' . esc_html__($country, 'wp-sms') . '</option>';
                }
                ?>
            </select>
            <p class="c-form__description">
                <?php _e("Choose your country code from the list for accurate delivery. If your number is international and doesn't fit any specific country code, select 'Global'.", 'wp-sms'); ?>
            </p>
        </div>
        <div class="c-form__fieldgroup u-mb-38">
            <label for="tel"><?php _e('Your Mobile Number', 'wp-sms'); ?> <span class="u-text-red">*</span></label>
            <input name="tel" id="tel" placeholder="<?php esc_attr_e('Enter your mobile number', 'wp-sms'); ?>" type="tel" required/>
            <p class="c-form__description">
                <?php _e("Enter the phone number where you'd like to receive management alerts.", 'wp-sms'); ?>
            </p>
        </div>
        <div class="c-form__footer u-flex-end">
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>
