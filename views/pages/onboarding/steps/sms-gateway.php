<div class="c-section__title">
    <span class="c-section__step"><?php printf(esc_html__('Step %d of 6', 'wp-sms'), $index); ?></span>
    <h1 class="u-m-0"><?php esc_html_e('Choose Your SMS Gateway', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php esc_html_e('Connect with your audience through text messages by selecting a gateway that fits your needs. WP SMS is compatible with over 200 gateways worldwide to ensure you can send SMS seamlessly.', 'wp-sms'); ?>
    </p>
</div>

<div class="c-gateway">
    <div class="c-search-filter u-flex u-align-center u-content-sp">
        <div class="c-search u-flex u-align-center u-content-start">
            <button type="submit"></button>
            <input id="searchGateway" placeholder="<?php esc_attr_e('Type to search...', 'wp-sms'); ?>" type="text"/>
        </div>

        <?php
        // Collect all unique countries from gateways
        $all_countries = [];
        foreach ($gateways as $gateway) {
            if (!empty($gateway->fields->gateway_attributes->country)) {
                $countries = is_array($gateway->fields->gateway_attributes->country)
                    ? $gateway->fields->gateway_attributes->country
                    : explode(',', $gateway->fields->gateway_attributes->country);

                $all_countries = array_merge($all_countries, array_map('trim', $countries));
            }
        }
        $all_countries = array_unique($all_countries);
        sort($all_countries);
        ?>

        <select id="filterCountries" name="countries">
            <option value="All"><?php esc_html_e('All countries', 'wp-sms'); ?></option>
            <option value="global"><?php esc_html_e('Global', 'wp-sms'); ?></option>
            <?php foreach ($all_countries as $country): ?>
                <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <div class="c-table__wrapper">
            <table class="c-table c-table-gateway js-table-gateway js-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Gateway', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('Gateway tooltip', 'wp-sms'); ?>" >
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th class="u-text-center">
                        <?php esc_html_e('Bulk SMS', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('Bulk SMS tooltip', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th class="u-text-center">
                        <?php esc_html_e('MMS', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('MMS tooltip', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th>
                        <?php esc_html_e('Gateway Access', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('Gateway Access tooltip', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th class="c-table-country--filter"><?php esc_html_e('Countries', 'wp-sms'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($gateways as $gateway): ?>
                    <?php
                    $countries = !empty($gateway->fields->gateway_attributes->country)
                        ? (is_array($gateway->fields->gateway_attributes->country)
                            ? $gateway->fields->gateway_attributes->country
                            : explode(',', $gateway->fields->gateway_attributes->country))
                        : [];

                    $countries    = array_map('trim', $countries);
                    $country_list = implode(', ', $countries);
                    ?>
                    <tr class="gateway-row" data-countries="<?php echo esc_attr(strtolower($country_list)); ?>">
                        <td>
                            <?php
                            $current_gateway = \WP_SMS\Option::getOption('gateway_name');
                            $selected        = ($current_gateway === esc_attr($gateway->slug)) ? 'checked' : '';
                            ?>
                            <input <?php echo esc_attr($selected); ?> value="<?php echo esc_attr($gateway->slug); ?>" id="gateway-name-<?php echo esc_attr($gateway->id); ?>" name="name" type="radio">
                            <label for="gateway-name-<?php echo esc_attr($gateway->id); ?>"><?php echo esc_html($gateway->title->rendered); ?></label>
                        </td>
                        <td class="u-text-center">
                            <span class="<?php echo !empty($gateway->fields->gateway_attributes->bulk_sms_support) ? 'checked' : 'unchecked'; ?>"></span>
                        </td>
                        <td class="u-text-center">
                            <span class="<?php echo !empty($gateway->fields->gateway_attributes->mms_support) ? 'checked' : 'unchecked'; ?>"></span>
                        </td>
                        <td class="u-text-center">
                            <span class="c-table__availability c-table__availability--success"><?php esc_html_e('Available', 'wp-sms'); ?></span>
                        </td>
                        <td class="c-table-country--filter"><?php echo esc_html($country_list); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="c-getway__offer u-mb-38">
            <span><?php esc_html_e('Don’t have an SMS gateway?', 'wp-sms'); ?></span>
            <a class="c-link" href="<?php echo esc_url('https://wp-sms-pro.com/gateways/'); ?>" target="_blank">
                <?php esc_html_e('Check out our recommended SMS gateways for optimized service.', 'wp-sms'); ?>
            </a>
        </div>

        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>