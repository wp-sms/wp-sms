<?php

namespace WP_SMS\AddOns\Page;

use Exception;
use WP_SMS\Components\View;
use WP_SMS\Notice\NoticeManager;
use WP_SMS\Utils\AdminHelper;
use WP_SMS\Utils\MenuUtil;
use Veronalabs\LicenseClient\LicenseHub;

if (!defined('ABSPATH')) exit;

class AddOnsPage
{
    public function render()
    {
        try {
            $addOns = LicenseHub::getAddOns();

            // Add settings URLs (WP-SMS specific)
            $addOns['active'] = array_map([$this, 'addSettingsUrl'], $addOns['active']);
            $addOns['inactive'] = array_map([$this, 'addSettingsUrl'], $addOns['inactive']);

            // Add icons
            $addOns['active'] = array_map([$this, 'addIcon'], $addOns['active']);
            $addOns['inactive'] = array_map([$this, 'addIcon'], $addOns['inactive']);

            $args = [
                'title'    => esc_html__('Add-Ons', 'wp-sms'),
                'pageName' => MenuUtil::getPageSlug('add-ons'),
                'data'     => $addOns,
                'tabs'     => [
                    [
                        'link'  => MenuUtil::getAdminUrl('add-ons', ['tab' => 'add-ons']),
                        'title' => esc_html__('Add-Ons', 'wp-sms'),
                        'class' => 'current',
                    ]
                ]
            ];

            View::load('templates/header', $args);
            AdminHelper::getTemplate('layout/title', $args);
            View::load("pages/license-manager/add-ons", $args);
            AdminHelper::getTemplate(['layout/postbox.hide', 'layout/footer'], $args);
        } catch (Exception $e) {
            NoticeManager::getInstance()->registerNotice(
                'wp_sms_license_manager_exception',
                $e->getMessage(),
                false,
                false
            );
            NoticeManager::getInstance()->displayStaticNotices();
        }
    }

    private function addSettingsUrl($addOn)
    {
        $slug = $addOn['slug'] ?? '';
        $addOn['settings_url'] = $this->getSettingsUrl($slug);
        return $addOn;
    }

    private function addIcon($addOn)
    {
        $slug = $addOn['slug'] ?? '';
        $iconPath = "assets/images/add-ons/{$slug}.svg";

        if (file_exists(WP_SMS_DIR . $iconPath)) {
            $addOn['icon'] = WP_SMS_URL . $iconPath;
        } else {
            $addOn['icon'] = $addOn['thumbnail'] ?? '';
        }

        return $addOn;
    }

    private function getSettingsUrl($slug)
    {
        $urls = [
            'wp-sms-pro'                     => MenuUtil::getAdminUrl('settings'),
            'wp-sms-woocommerce-pro'         => MenuUtil::getAdminUrl('wp-sms-woo-pro-settings'),
            'wp-sms-two-way'                 => MenuUtil::getAdminUrl('settings', ['tab' => 'addon_two_way']),
            'wp-sms-elementor-form'          => MenuUtil::getAdminUrl('integrations', ['tab' => 'pro_elementor']),
            'wp-sms-membership-integrations' => MenuUtil::getAdminUrl('integrations', ['tab' => 'pro_memberships']),
            'wp-sms-booking-integrations'    => MenuUtil::getAdminUrl('integrations', ['tab' => 'pro_booking']),
            'wp-sms-fluent-integrations'     => MenuUtil::getAdminUrl('integrations', ['tab' => 'pro_fluent']),
        ];

        return $urls[$slug] ?? '';
    }

    public function view()
    {
        $this->render();
    }
}
