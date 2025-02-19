<div class="c-section__title">
    <span class="c-section__step"><?php printf(__('Step %d of 7', 'wp-sms'), $index); ?></span>
    <h1 class="u-m-0"><?php _e('Choose Your SMS Gateway', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php _e('Connect with your audience through text messages by selecting a gateway that fits your needs. WP SMS is compatible with over 200 gateways worldwide to ensure you can send SMS seamlessly.', 'wp-sms'); ?>
    </p>
</div>

<div class="c-gateway">
    <div class="c-search-filter u-flex u-align-center u-content-sp">
        <div class="c-search u-flex u-align-center u-content-start">
            <button type="submit"></button>
            <input id="searchGateway" placeholder="<?php esc_attr_e('Type to search...', 'wp-sms'); ?>" type="text"/>
        </div>
        <div class="wpsms-skeleton wpsms-skeleton__select wpsms-skeleton__select--step2"></div>
        <select name="countries">
            <option value="All"><?php _e('All countries', 'wp-sms'); ?></option>
            <?php
            $countries = ['Albania', 'Algeria', 'Andorra', 'Angola'];
            foreach ($countries as $country) {
                echo '<option value="' . esc_attr($country) . '">' . esc_html__($country, 'wp-sms') . '</option>';
            }
            ?>
        </select>
    </div>

    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <div class="c-table__wrapper">
            <div class="wpsms-skeleton wpsms-skeleton__table"></div>
            <table class="c-table c-table-gateway js-table-gateway js-table">
                <thead>
                <tr>
                    <th><?php _e('Gateway', 'wp-sms'); ?>
                        <span data-tooltip="<?php esc_attr_e('Gateway tooltip', 'wp-sms'); ?>" data-tooltip-font-size="12px">
                                <i class="wps-tooltip-icon"></i>
                            </span>
                        <i class="c-table__sort-arrow"></i>
                    </th>
                    <th class="u-text-center"><?php _e('Bulk SMS', 'wp-sms'); ?></th>
                    <th class="u-text-center"><?php _e('MMS', 'wp-sms'); ?></th>
                    <th><?php _e('Gateway Access', 'wp-sms'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($gateways as $gateway): ?>
                    <tr class="<?php echo !empty($gateway->fields->gateway_attributes->wp_sms_pro) && $gateway->fields->gateway_attributes->wp_sms_pro && !\WP_SMS\Version::pro_is_active() ? 'disabled' : ''; ?>">
                        <td>
                            <?php if (!empty($gateway->fields->gateway_attributes->wp_sms_pro) && $gateway->fields->gateway_attributes->wp_sms_pro && !\WP_SMS\Version::pro_is_active()): ?>
                                <span data-tooltip="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" data-tooltip-font-size="12px">
                                        <span class="icon-lock"></span>
                                    </span>
                                <span><?php echo esc_html($gateway->title->rendered); ?></span>
                            <?php else: ?>
                                <input value="<?php echo $gateway->slug ?>" id="gateway-name-<?php echo esc_attr($gateway->id); ?>" name="name" type="radio">
                                <label for="gateway-name-<?php echo esc_attr($gateway->id); ?>"><?php echo esc_html($gateway->title->rendered); ?></label>
                            <?php endif; ?>
                        </td>
                        <td class="u-text-center">
                            <span class="<?php echo !empty($gateway->fields->gateway_attributes->bulk_sms_support) && $gateway->fields->gateway_attributes->bulk_sms_support ? 'checked' : 'unchecked'; ?>"></span>
                        </td>
                        <td class="u-text-center">
                            <span class="<?php echo !empty($gateway->fields->gateway_attributes->mms_support) && $gateway->fields->gateway_attributes->mms_support ? 'checked' : 'unchecked'; ?>"></span>
                        </td>
                        <td class="u-flex u-align-center u-content-sp">
                            <?php if (!empty($gateway->fields->gateway_attributes->wp_sms_pro) && $gateway->fields->gateway_attributes->wp_sms_pro && !\WP_SMS\Version::pro_is_active()): ?>
                                <a title="<?php esc_attr_e('Pro Version Required', 'wp-sms'); ?>" target="_blank" href="https://wp-sms-pro.com/buy/" class="c-table__availability c-table__availability--pro"><?php _e('Pro Version Required', 'wp-sms'); ?></a>
                            <?php else: ?>
                                <span class="c-table__availability c-table__availability--success"><?php _e('Available', 'wp-sms'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="c-getway__offer u-mb-38">
            <span><?php _e('Don’t have an SMS gateway?', 'wp-sms'); ?></span>
            <a class="c-link" href="https://wp-sms-pro.com/gateways/" target="_blank" title="<?php esc_attr_e('Check out our recommended SMS gateways for optimized service.', 'wp-sms'); ?>">
                <?php _e('Check out our recommended SMS gateways for optimized service.', 'wp-sms'); ?>
            </a>
        </div>
        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>
