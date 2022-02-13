<?php

namespace WPSmsTwoWay\Core;

use WP_SMS\Newsletter;
use WP_SMS\Subscribers;
use WP_SMS\Version;
use WPSmsTwoWay\Messaging\MessageHandler;
use WPSmsTwoWay\Services\Logger\ExceptionLogger;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberFormat;

class Helper
{
    /**
     * @param $option
     * @param false $default
     * @return mixed
     */
    public static function getOption($option, $default = '')
    {
        return get_option('WPSmsTwoWay_' . $option, $default);
    }

    /**
     * Get customer mobile number from woocommerce order
     *
     * @param int $orderID
     * @return string mobile
     */
    public static function getCustomerMobileNumber($orderID)
    {
        $phoneField = \WP_SMS\Option::getOption('wc_mobile_field', true);
        switch ($phoneField) {
            case 'add_new_field':
                $mobile = get_post_meta($orderID, 'mobile', true);
                break;
            case 'used_current_field':
            default:
                $mobile = wc_get_order($orderID)->get_billing_phone();
        }
        return $mobile;
    }

    /**
     * Compare sender and order phone number
     *
     * @param string $senderNumber
     * @param string $orderNumber
     * @return bool
     */
    public static function compareSenderAndOrderPhones(string $senderNumber, string $orderNumber)
    {
        try {
            $senderNumber = PhoneNumber::parse($senderNumber);
            $orderNumber = PhoneNumber::parse($orderNumber, $senderNumber->getRegionCode());
            return $senderNumber->isEqualTo($orderNumber);
        } catch (\Throwable $e) {
            WPSmsTwoWay()->getPlugin()->get(ExceptionLogger::class)->error($e);
            return false;
        }
    }

    public static function getSubscribersOptions(): array
    {
        $groups              = Newsletter::getGroups();
        $subscribe_groups[0] = __('All', 'wp-sms-two-way');

        if ($groups) {
            foreach ($groups as $group) {
                $subscribe_groups[$group->ID] = $group->name;
            }
        }
        return $subscribe_groups;
    }

    public static function getCountriesList(): array
    {
        $file      = WP_SMS_DIR . 'assets/countries.json';
        $file      = file_get_contents($file);
        $countries = json_decode($file, true);
        $result    = [];
        foreach ($countries as $key => $country) {
            $result[$country['code']] = $country['name'];
        }
        return $result;
    }

    public static function arrayToList($in, $inputId = '')
    {
        $html = '<strong class="wpsmstwoway-list-content" data-input-id="' . $inputId . '">';
        foreach ($in as $key => $value) {
            $html .= "<span class='wpsmstwoway-list-header'>" . ucfirst($key) . "</span><ul>";
            foreach ($value as $v) {
                $html .= "<li>{$v}</li>";
            }
            $html .= "</ul>";
        }

        $html .= '</strong>';

        return $html;
    }

    /**
     * @param $classes
     * @param string $inputId
     * @return string
     */
    public static function getDescriptionShortCode($classes, $inputId = '')
    {
        return (new MessageHandler())->getRenderedDescription($classes, $inputId);
    }

    /**
     * @return bool
     */
    public static function isProInstalled()
    {
        return class_exists('WP_SMS\Version') && Version::pro_is_active();
    }

    /**
     * @return mixed
     */
    public static function getMobileNumberFromCurrentUserMeta()
    {
        $currentUser = get_currentuserinfo();
        if ($currentUser) {
            return get_user_meta($currentUser->ID, 'mobile', true);
        }
    }

    /**
     * @return mixed
     */
    public static function getMobileFieldName()
    {
        $phone_field = self::getOption('general_phone_field');
        $field_name  = '';
        switch ($phone_field) {
            case  'add_new_field':
                $field_name = 'mobile';
                break;
            case  'used_current_field':
                $field_name = 'billing_phone';
                break;
        }
        return $field_name;
    }

    /**
     * @return array
     */
    public static function getCustomersNumbers()
    {
        $mobile_field = self::getMobileFieldName();
        if (!$mobile_field) {
            return [];
        }

        $args = array(
            'meta_query' => array(
                array(
                    'key'     => $mobile_field,
                    'compare' => '>',
                ),
                array(
                    'key'     => 'billing_first_name',
                    'compare' => '>',
                ),
            ),
            'fields'     => 'all'
        );

        $customers = get_users($args);

        $numbers = array();
        foreach ($customers as $customer) {
            $numbers[] = $customer->{$mobile_field};
        }

        return $numbers;
    }

    /**
     * @param string $type
     * @param string $group
     * @return mixed
     */
    public static function getAllRecipients($type, $group)
    {
        global $wpdb;
        $recipients = [];
        if ($type == 'subscriber') {
            if ($group) {
                $recipients = $wpdb->get_col("SELECT mobile FROM {$wpdb->prefix}sms_subscribes WHERE status = 1 AND group_ID = '" . $group . "'");
            } else {
                $recipients = $wpdb->get_col("SELECT mobile FROM {$wpdb->prefix}sms_subscribes WHERE status = 1");
            }
        } elseif ($type == 'users') {
            $customers_numbers = Helper::getCustomersNumbers();
            if (!$customers_numbers) {
                return;
            }
            $recipients = $customers_numbers;
        }
        return $recipients;
    }

    /**
     * Delete a column from an array
     *
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function deleteArrayCol(&$array, $key)
    {
        return array_walk($array, function (&$v) use ($key) {
            unset($v[$key]);
        });
    }

    /**
     *  Truncate and add ellipsis to a given string
     *
     * @param string $input
     * @param integer $length
     * @return string
     */
    public static function ellipsis(string $input, int $length = 10)
    {
        return strlen($input) > $length ? substr($input, 0, $length)."..." : $input;
    }

    /**
     * Get all Fully Qualified Class Names existing in a directory
     *
     * @see https://stackoverflow.com/a/27440555
     * @param string $path
     * @return array
     */
    public static function findAllClassesInDir(string $path): array
    {
        $fqcns = [];

        if (!is_dir($path)) {
            return $fqcns;
        }

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            $content = file_get_contents($phpFile->getRealPath());
            $tokens = token_get_all($content);
            $namespace = '';
            for ($index = 0; isset($tokens[$index]); $index++) {
                if (!isset($tokens[$index][0])) {
                    continue;
                }
                if (T_NAMESPACE === $tokens[$index][0]) {
                    $index += 2; // Skip namespace keyword and whitespace
                    while (isset($tokens[$index]) && is_array($tokens[$index])) {
                        $namespace .= $tokens[$index++][1];
                    }
                }
                if (T_CLASS === $tokens[$index][0] && T_WHITESPACE === $tokens[$index + 1][0] && T_STRING === $tokens[$index + 2][0]) {
                    $index += 2; // Skip class keyword and whitespace
                    $fqcns[] = $namespace.'\\'.$tokens[$index][1];
                    break;
                }
            }
        }
        return $fqcns;
    }
}
