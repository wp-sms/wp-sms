<?php

namespace WP_SMS\Controller;

use WP_SMS\Helper;

class RecipientCountsAjax extends AjaxControllerAbstract
{
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
        $type  = $this->get('type');           // roles | wc-customers | bp-members
        $value = $this->get('value');          // role key (when type === roles)
        $count = 0;

        switch ($type) {
            case 'roles':
                if ($value === null || $value === '') {
                    // translators: %s: field name
                    throw new \Exception(sprintf(esc_html__('Field %s is required.', 'wp-sms'), 'value'));
                }

                $result = Helper::getUsersMobileNumberCountsWithRoleDetails();
                $count  = isset($result['roles'][$value]['count']) ? (int)$result['roles'][$value]['count'] : 0;
                break;

            case 'wc-customers':
                $numbers = Helper::getWooCommerceCustomersNumbers();
                $count   = is_array($numbers) ? count($numbers) : 0;
                break;

            case 'bp-members':
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

        wp_send_json_success(['count' => $count]);
    }
}
