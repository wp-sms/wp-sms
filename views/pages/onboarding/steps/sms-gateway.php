<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Utils\PluginHelper;

$current_country         = \WP_SMS\Option::getOption('admin_mobile_number_country_prefix');
$is_pro_plugin_activated = PluginHelper::isPluginInstalled('wp-sms-pro/wp-sms-pro.php');
$has_valid_license       = LicenseHelper::isPluginLicensedAndActive();

?>

<div class="c-section__title">
    <span class="c-section__step"><?php
        /* translators: 1: current step number 2: total number of steps */
        echo esc_html(sprintf(__('Step %1$d of %2$d', 'wp-sms'), $index, $total_steps));
        ?></span>
    <h1 class="u-m-0"><?php esc_html_e('Choose Your SMS Gateway', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php esc_html_e('Connect with your audience through text messaging by selecting a gateway that fits your needs. WP SMS supports over 350 gateways worldwide, ensuring you can send messages reliably—no matter where your customers are.', 'wp-sms'); ?>
    </p>
</div>

<div class="c-gateway">
    <div class="c-search-filter u-flex u-align-center u-content-sp">
        <div class="c-filters">
            <label for="searchGateway"><?php esc_html_e('Search by Gateway Name', 'wp-sms'); ?></label>
            <div class="c-search u-flex u-align-center u-content-start">
                <button type="button"><span class="screen-reader-text"><?php esc_html_e('Search', 'wp-sms'); ?></span></button>
                <input id="searchGateway" placeholder="<?php esc_attr_e('Type to search...', 'wp-sms'); ?>" type="text"/>
            </div>
        </div>

        <div class="c-filters">
            <label for="filterCountries"><?php esc_html_e('Origin Country', 'wp-sms'); ?></label>
            <?php
            // Collect all unique countries from gateways
            $all_countries = [];
            $all_regions   = [];

            foreach ($gateways as $gateway) {
                if (!empty($gateway->fields->gateway_attributes->country)) {
                    $countries     = is_array($gateway->fields->gateway_attributes->country)
                        ? $gateway->fields->gateway_attributes->country
                        : explode(',', $gateway->fields->gateway_attributes->country);
                    $all_countries = array_merge($all_countries, array_map('trim', $countries));
                }

                if (!empty($gateway->fields->gateway_attributes->region)) {
                    foreach ($gateway->fields->gateway_attributes->region as $region) {
                        if (is_object($region) && isset($region->label)) {
                            $all_regions[] = $region->label;
                        } elseif (is_array($region) && isset($region['label'])) {
                            $all_regions[] = $region['label'];
                        } elseif (is_string($region)) {
                            $all_regions[] = $region;
                        }
                    }
                }
            }
            $all_countries = array_unique($all_countries);
            sort($all_countries);

            $all_regions = array_unique($all_regions);
            sort($all_regions);
            ?>
            <input class="chosen-origin" disabled type="hidden" value="<?php echo esc_html($current_country) ?>">
            <select id="filterCountries" name="countries">
                <option value="All"><?php esc_html_e('All origins', 'wp-sms'); ?></option>

                <?php if (!empty($all_regions)): ?>
                    <optgroup label="<?php esc_attr_e('Regions', 'wp-sms'); ?>">
                        <?php foreach ($all_regions as $region): ?>
                            <option value="<?php echo esc_attr($region); ?>"><?php echo esc_html($region); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>

                <?php if (!empty($all_countries)): ?>
                    <optgroup label="<?php esc_attr_e('Countries', 'wp-sms'); ?>">
                        <?php foreach ($all_countries as $country): ?>
                            <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <div class="c-table__wrapper">
            <table class="c-table c-table-gateway js-table-gateway js-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Gateway', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('The name of the SMS provider.', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th class="u-text-center">
                        <?php esc_html_e('Bulk SMS', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e(' Indicates if this gateway supports sending high-volume SMS to multiple recipients simultaneously.', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th class="u-text-center">
                        <?php esc_html_e('WhatsApp', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('Indicates if this gateway supports sending WhatsApp messages.', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th>
                        <?php esc_html_e('Origin Country', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('Country where the gateway is headquartered or primarily licensed.', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                    <th>
                        <?php esc_html_e('Gateway Access', 'wp-sms'); ?>
                        <span class="wpsms-tooltip" title="<?php esc_html_e('Shows whether this gateway is included in your current plan or requires All-in-One for full functionality.', 'wp-sms'); ?>">
                          <i class="wps-tooltip-icon"></i>
                        </span>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($gateways as $gateway): ?>
                    <?php
                    $countries      = [];
                    $country_list   = '';
                    $is_pro_gateway = false;
                    $badges         = [];
                    $region         = '';

                    if (isset($gateway->fields->gateway_attributes->is_deprecated) && $gateway->fields->gateway_attributes->is_deprecated) continue;

                    if (isset($gateway->fields) && !empty($gateway->fields)) {
                        if (isset($gateway->fields->gateway_attributes) && !empty($gateway->fields->gateway_attributes)) {
                            if (!empty($gateway->fields->gateway_attributes->country)) {
                                $countries    = is_array($gateway->fields->gateway_attributes->country)
                                    ? $gateway->fields->gateway_attributes->country
                                    : explode(',', $gateway->fields->gateway_attributes->country);
                                $countries    = array_map('trim', $countries);
                                $country_list = implode(', ', $countries);
                            }

                            if (!empty($gateway->fields->gateway_attributes->region)) {
                                $region = is_array($gateway->fields->gateway_attributes->region) ? implode(', ', array_map('trim', (wp_list_pluck((array)$gateway->fields->gateway_attributes->region, 'label') ?: (array)$gateway->fields->gateway_attributes->region))) : trim((string)$gateway->fields->gateway_attributes->region);
                            }

                            if (isset($gateway->fields->gateway_attributes->wp_sms_pro)) {
                                $is_pro_gateway = $gateway->fields->gateway_attributes->wp_sms_pro;
                            }
                        }
                    }

                    if (isset($gateway->fields->gateway_attributes->badge)) {
                        $badges = json_decode(json_encode($gateway->fields->gateway_attributes->badge), true);
                        $badges = array_column($badges, 'label', 'value');
                    }
                    ?>

                    <?php if ($is_pro_gateway && (!$has_valid_license || !$is_pro_plugin_activated)): ?>
                        <tr class="disabled even <?php echo !empty($badges) ? 'c-table-gateway__row--with-badge' : ''; ?>" role="row" data-countries="<?php echo esc_attr(strtolower($country_list)); ?>" data-regions="<?php echo esc_attr(strtolower($region)); ?>">
                            <td>
                                <div class="c-table-gateway__info">
                                   <span data-tooltip="<?php echo esc_attr__('All-in-One Required', 'wp-sms'); ?>" data-tooltip-font-size="12px">
                                        <span class="icon-lock"></span>
                                    </span>
                                    <span class="c-table-gateway__name">
                                        <?php if (isset($gateway->link) && !empty($gateway->link)): ?>
                                            <span>
                                               <?php echo esc_html($gateway->title->rendered); ?>
                                                <a target="_blank" href="<?php echo esc_url($gateway->link); ?>" title="<?php echo esc_html($gateway->title->rendered); ?>">
                                                    <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M8.66699 7.83288L14.1337 2.36621" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M14.6668 5.03301V1.83301H11.4668" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M7.33301 1.83301H5.99967C2.66634 1.83301 1.33301 3.16634 1.33301 6.49967V10.4997C1.33301 13.833 2.66634 15.1663 5.99967 15.1663H9.99967C13.333 15.1663 14.6663 13.833 14.6663 10.4997V9.16634" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </a>
                                            </span>
                                        <?php else: ?>
                                            <?php echo esc_html($gateway->title->rendered); ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php
                                    if (!empty($badges)):
                                        foreach ($badges as $slug => $badge):?>
                                            <span class="c-table-gateway__badge"><?php echo esc_html($badge) ?></span>
                                        <?php
                                        endforeach;
                                    endif;
                                    ?>
                                    <a target="_blank" href="<?php echo esc_url('https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'); ?>" class="c-table__availability c-table__availability--pro">
                                        <?php esc_html_e('All-in-One Required', 'wp-sms'); ?>
                                    </a>
                                </div>
                            </td>
                            <td class="u-text-center">
                                <span class="<?php echo !empty($gateway->fields->gateway_attributes->bulk_sms_support) ? esc_attr('checked') : esc_attr('unchecked'); ?>"
                                      data-sort="<?php echo !empty($gateway->fields->gateway_attributes->bulk_sms_support) ? '0' : '1'; ?>"></span>
                            </td>
                            <td class="u-text-center">
                                <span class="<?php echo !empty($gateway->fields->gateway_attributes->whatsapp_support) ? esc_attr('checked') : esc_attr('unchecked'); ?>"
                                      data-sort="<?php echo !empty($gateway->fields->gateway_attributes->whatsapp_support) ? '0' : '1'; ?>"></span>
                            </td>
                            <td class="u-text-center"><span class="text-ellipsis"><?php echo esc_html($country_list); ?></span></td>
                            <td class="u-text-center"><span class="text-ellipsis"><?php echo esc_html($region); ?></span></td>
                        </tr>
                    <?php else:
                        $current_gateway = \WP_SMS\Option::getOption('gateway_name');
                        $slug       = str_replace(['-', ' '], '', $gateway->slug);
                        
                        if (!\WP_SMS\Gateway::gatewayExists($slug)) {
                            continue;
                        }

                        $selected = ($current_gateway === $slug) ? 'checked' : '';
                        ?>
                        <tr class="gateway-row <?php echo !empty($badges) ? 'c-table-gateway__row--with-badge' : ''; ?>" data-countries="<?php echo esc_attr(strtolower($country_list)); ?>" data-regions="<?php echo esc_attr(strtolower($region)); ?>">
                            <td>
                                <div class="c-table-gateway__info">
                                    <input aria-label="<?php esc_attr_e('Gateway Name', 'wp-sms'); ?>" <?php echo esc_attr($selected); ?> value="<?php echo esc_attr($slug); ?>" id="gateway-name-<?php echo esc_attr($gateway->id); ?>" name="name" type="radio">
                                    <span class="c-table-gateway__name">
                                        <?php if (isset($gateway->link) && !empty($gateway->link)): ?>
                                            <span>
                                                <?php echo esc_html($gateway->title->rendered); ?>
                                                <a target="_blank" href="<?php echo esc_url($gateway->link); ?>" title="<?php echo esc_html($gateway->title->rendered); ?>">
                                                    <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M8.66699 7.83288L14.1337 2.36621" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M14.6668 5.03301V1.83301H11.4668" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M7.33301 1.83301H5.99967C2.66634 1.83301 1.33301 3.16634 1.33301 6.49967V10.4997C1.33301 13.833 2.66634 15.1663 5.99967 15.1663H9.99967C13.333 15.1663 14.6663 13.833 14.6663 10.4997V9.16634" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </a>
                                            </span>
                                        <?php else: ?>
                                            <?php echo esc_html($gateway->title->rendered); ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php
                                    if (!empty($badges)):
                                        foreach ($badges as $slug => $badge):?>
                                            <span class="c-table-gateway__badge"><?php echo esc_html($badge) ?></span>
                                        <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            </td>
                            <td class="u-text-center">
                                <span class="<?php echo !empty($gateway->fields->gateway_attributes->bulk_sms_support) ? esc_attr('checked') : esc_attr('unchecked'); ?>"
                                      data-sort="<?php echo !empty($gateway->fields->gateway_attributes->bulk_sms_support) ? '0' : '1'; ?>"></span>
                            </td>
                            <td class="u-text-center">
                                <span class="<?php echo !empty($gateway->fields->gateway_attributes->mms_support) ? esc_attr('checked') : esc_attr('unchecked'); ?>"
                                      data-sort="<?php echo !empty($gateway->fields->gateway_attributes->mms_support) ? '0' : '1'; ?>"></span>
                            </td>
                            <td class="u-text-center"><?php echo esc_html($country_list); ?></td>
                            <td>
                                <span class="c-table__availability c-table__availability--success"><?php esc_html_e('Available', 'wp-sms'); ?></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="c-getway__offer u-mb-38">
            <span><?php esc_html_e('Don’t have SMS gateway?', 'wp-sms'); ?></span>
            <a class="c-link" href="<?php echo esc_url('https://wp-sms-pro.com/gateways/recommended/'); ?>" target="_blank">
                <?php esc_html_e('Check out our recommended SMS gateways for optimized service.', 'wp-sms'); ?>
            </a>
        </div>

        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>