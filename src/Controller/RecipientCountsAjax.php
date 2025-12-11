<?php

namespace WP_SMS\Controller;

use WP_SMS\Helper;
use WP_SMS\Traits\TransientCacheTrait;

if (!defined('ABSPATH')) exit;

class RecipientCountsAjax extends AjaxControllerAbstract
{
    use TransientCacheTrait;

    /**
     * Action slug used for admin-ajax and nonce.
     * Nonce name => 'wp_sms_get_recipient_counts'
     */
    protected $action = 'wp_sms_get_recipient_counts';

    /**
     * 'type' is required. 'value' is only required when type === 'roles'.
     * We'll validate 'value' in run() when needed.
     */
    public $requiredFields = ['type'];

    protected function run()
    {
        $type  = $this->get('type');
        $value = $this->get('value');
        $count = 0;

        $cacheInput = 'recipient_counts:' . wp_json_encode([
                'type'  => $type,
                'value' => $value,
            ]);

        $cached = $this->getCachedResult($cacheInput);
        if ($cached !== false && is_array($cached) && array_key_exists('count', $cached)) {
            wp_send_json_success(['count' => (int)$cached['count']]);
        }

        switch ($type) {
            case 'roles':
            case 'users':
                if ($value === null || $value === '') {
                    // translators: %s: field name
                    throw new \Exception(sprintf(esc_html__('Field %s is required.', 'wp-sms'), 'value'));
                }

                $result = Helper::getUsersMobileNumberCountsWithRoleDetails();
                $count  = isset($result['total']['count']) ? (int)$result['total']['count'] : 0;
                break;

            case 'wc-customers':
                $numbers = Helper::getWooCommerceCustomersNumbers();
                $count   = is_array($numbers) ? count($numbers) : 0;
                break;

            case 'subscribers':
                $count = 0;
                break;

            case 'bp-users':
                if (class_exists('BuddyPress') && class_exists('\WP_SMS\Pro\Services\Integration\BuddyPress\BuddyPress')) {
                    /** @noinspection PhpFullyQualifiedNameUsageInspection */
                    $count = (int)\WP_SMS\Pro\Services\Integration\BuddyPress\BuddyPress::getTotalMobileNumbers();
                } else {
                    $count = 0;
                }
                break;

            default:
                wp_send_json_error(__('Invalid type.', 'wp-sms'), 400);
        }

        $this->setCachedResult($cacheInput, ['count' => $count], 15 * MINUTE_IN_SECONDS);

        wp_send_json_success(['count' => $count]);
    }
}
