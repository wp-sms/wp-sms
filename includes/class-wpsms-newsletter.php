<?php

namespace WP_SMS;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Newsletter
{
    public $date;
    protected $db;
    protected $tb_prefix;

    public function __construct()
    {
        global $wpdb;

        $this->date      = WP_SMS_CURRENT_DATE;
        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        add_action('wp_loaded', array($this, 'unSubscriberNumberByUrlAction'), 20);
    }

    /**
     * @return mixed|void|null
     */
    public static function getUnSubscriberQueryString()
    {
        return apply_filters('wpsms_unsubscribe_query_string', 'wpsms_unsubscribe');
    }

    /**
     * @param $number
     *
     * @return string
     */
    public static function generateUnSubscribeUrlByNumber($number)
    {
        $unSubscribeUrl = add_query_arg([
            self::getUnSubscriberQueryString() => $number,
            'csrf'                             => wp_hash('wp_sms_unsubscribe')
        ], get_bloginfo('url'));

        return wp_sms_shorturl($unSubscribeUrl);
    }

    /**
     * Unsubscribe a number by query string action
     *
     */
    public function unSubscriberNumberByUrlAction()
    {
        $unSubscriberQueryString = self::getUnSubscriberQueryString();

        if (!isset($_REQUEST[$unSubscriberQueryString]) || !wp_unslash($_REQUEST[$unSubscriberQueryString])) {
            return;
        }

        // Check CSRF
        if (apply_filters('wpsms_unsubscribe_csrf_enabled', true)) {

            if (!isset($_REQUEST['csrf']) || wp_hash('wp_sms_unsubscribe') != $_REQUEST['csrf']) {
                wp_die(esc_html__('Access denied.', 'wp-sms'), esc_html__('SMS newsletter', 'wp-sms'), [
                    'link_text' => esc_html__('Home page', 'wp-sms'),
                    'link_url'  => esc_url(get_bloginfo('url')),
                    'response'  => 200,
                ]);
            }
        }

        $number  = wp_unslash(trim($_REQUEST[$unSubscriberQueryString]));
        $numbers = [$number, "+{$number}"];

        foreach ($numbers as $number) {
            $response = self::deleteSubscriberByNumber($number);

            do_action('wp_sms_number_unsubscribed_through_url', $number);

            if ($response['result'] == 'success') {
                wp_die(esc_html($response['message']), esc_html__('SMS newsletter', 'wp-sms'), [
                    'link_text' => esc_html__('Home page', 'wp-sms'),
                    'link_url'  => esc_url(get_bloginfo('url')),
                    'response'  => 200,
                ]);
            }
        }

        wp_die(esc_html($response['message']), esc_html__('SMS newsletter', 'wp-sms'), [
            'link_text' => esc_html__('Home page', 'wp-sms'),
            'link_url'  => esc_url(get_bloginfo('url')),
            'response'  => 200,
        ]);
    }

    /**
     * Add Subscriber
     *
     * @param $name
     * @param $mobile
     * @param string $group_id
     * @param string $status
     * @param null $key
     * @param array $custom_fields
     *
     * @return array
     */
    public static function addSubscriber($name, $mobile, $group_id = '', $status = '1', $key = null, $custom_fields = array())
    {
        global $wpdb;

        // Check mobile validity
        $validate = Helper::checkMobileNumberValidity($mobile, false, true, $group_id);

        if (is_wp_error($validate)) {
            return array('result' => 'error', 'message' => $validate->get_error_message());
        }

        $result = $wpdb->insert(
            $wpdb->prefix . "sms_subscribes",
            array(
                'date'          => WP_SMS_CURRENT_DATE,
                'name'          => $name,
                'mobile'        => $mobile,
                'status'        => $status,
                'activate_key'  => $key,
                'custom_fields' => serialize($custom_fields),
                'group_ID'      => $group_id,
            )
        );

        if ($result) {
            /**
             * Run hook after adding subscribe.
             *
             * @param string $name name.
             * @param string $mobile mobile.
             * @param string $status mobile.
             * @param string $wpdb - >insert_id Subscriber ID
             *
             * @since 3.0
             *
             */
            do_action('wp_sms_add_subscriber', $name, $mobile, $status, $wpdb->insert_id);

            return array('result' => 'success', 'message' => esc_html__('Subscriber successfully added.', 'wp-sms'), 'id' => $wpdb->insert_id);
        } else {
            return array('result' => 'error', 'message' => esc_html__('Failed to add subscriber, please deactivate and then activate WP SMS and then try again.', 'wp-sms'));
        }
    }


    /**
     * Get Subscriber
     *
     * @param $id
     *
     * @return array|object|void|null
     */
    public static function getSubscriber($id)
    {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE ID = %s", $id)
        );

        if ($result) {
            return $result;
        }
    }

    public static function getSubscriberByMobile($number)
    {
        global $wpdb;

        $metaValue = Helper::prepareMobileNumberQuery($number);

        // Prepare each value in $metaValue
        foreach ($metaValue as &$value) {
            $value = $wpdb->prepare('%s', $value);
        }

        $placeholders = implode(', ', $metaValue);
        $sql          = "SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE mobile IN ({$placeholders})";


        $result = $wpdb->get_row($sql);

        if ($result) {
            return $result;
        }
    }

    /**
     * Delete inactive subscribes with this number
     */
    public static function deleteInactiveSubscribersByMobile($mobile)
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE mobile = %s AND status = '0'", $mobile)
        );

        if ($results) {
            foreach ($results as $row) {
                $result = Newsletter::deleteSubscriberByNumber($mobile, $row->group_ID);
                // Check result
                if ($result['result'] == 'error') {
                    return new WP_Error('clear inactive subscribes', $result['message']);
                }
            }
        }
    }

    /**
     * Delete subscriber by number
     *
     * @param $mobile
     * @param null $group_id
     *
     * @return array
     */
    public static function deleteSubscriberByNumber($mobile, $group_id = null)
    {
        global $wpdb;

        if (empty($mobile)) {
            return ['result' => 'error', 'message' => esc_html__('Mobile number is required!', 'wp-sms')];
        }

        // Process group_id
        $group_ids = self::processGroupId($group_id);
        if (is_wp_error($group_ids)) {
            return ['result' => 'error', 'message' => $group_ids->get_error_message()];
        }

        $where   = ['mobile' => $mobile];
        $success = false;

        foreach ($group_ids as $group_id) {
            if (!empty($group_id)) {
                $where['group_id'] = $group_id;
            }

            $result = $wpdb->delete("{$wpdb->prefix}sms_subscribes", $where);

            if ($result !== false) {
                $success = true; // At least one deletion was successful
            }
        }
        // Handle deletion result
        if (!$success) {
            return ['result' => 'error', 'message' => esc_html__('The mobile number does not exist in the specified group(s)!', 'wp-sms')];
        }
        /**
         * Run hook after deleting subscriber.
         *
         * @param bool $result Whether the deletion was successful.
         * @since 3.0
         */
        do_action('wp_sms_delete_subscriber', $success);

        return ['result' => 'success', 'message' => esc_html__('Successfully canceled the subscription!', 'wp-sms')];
    }


    /**
     * Update Subscriber
     *
     * @param $id
     * @param $name
     * @param $mobile
     * @param string $group_id
     * @param string $status
     *
     * @return array
     */
    public static function updateSubscriber($id, $name, $mobile, $group_id = '', $status = '1')
    {
        global $wpdb;

        if (empty($id) or empty($name) or empty($mobile)) {
            return array('result' => 'error', 'message' => esc_html__('The fields must be valued.', 'wp-sms'));
        }

        // Check mobile validity
        $validate = Helper::checkMobileNumberValidity($mobile, false, true, $group_id, $id);

        if (is_wp_error($validate)) {
            return array('result' => 'error', 'message' => $validate->get_error_message());
        }

        $result = $wpdb->update(
            $wpdb->prefix . "sms_subscribes",
            array(
                'name'     => $name,
                'mobile'   => Helper::sanitizeMobileNumber($mobile),
                'group_ID' => $group_id,
                'status'   => $status,
            ),
            array(
                'ID' => $id
            )
        );

        if ($result) {

            /**
             * Run hook after updating subscribe.
             *
             * @param string $result result query.
             *
             * @since 3.0
             *
             */
            do_action('wp_sms_update_subscriber', $result);

            return array('result' => 'success', 'message' => esc_html__('Subscriber successfully updated.', 'wp-sms'));
        } else {
            return array('result' => 'error', 'message' => esc_html__('No change has been occurred.', 'wp-sms'));
        }
    }

    /**
     * Get Group by group ID
     *
     * @param $group_id
     *
     * @return object|null
     */
    public static function getGroup($group_id)
    {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes_group` WHERE `ID` = %d", $group_id)
        );

        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Get Groups
     *
     * @param array|null $groupIds
     *
     * @return array|object|null
     */
    public static function getGroups($groupIds = null)
    {
        global $wpdb;
        $where = '';

        if ($groupIds && is_array($groupIds)) {
            $placeholders       = implode(', ', array_fill(0, count($groupIds), '%d'));
            $prepared_group_ids = $wpdb->prepare($placeholders, $groupIds);
            $where              .= "`ID` IN ({$prepared_group_ids}) ";
        }

        $where = $where ? "WHERE {$where}" : '';
        $sql   = "SELECT * FROM `{$wpdb->prefix}sms_subscribes_group`" . $where;

        return $wpdb->get_results($sql);
    }

    /**
     * Delete Group
     *
     * @param Not param
     *
     * @return false|int|void
     */
    public static function deleteGroup($id)
    {
        global $wpdb;

        if (empty($id)) {
            return;
        }

        $result = $wpdb->delete(
            $wpdb->prefix . "sms_subscribes_group",
            array(
                'ID' => $id,
            )
        );

        if ($result) {

            /**
             * Run hook after deleting group.
             *
             * @param string $result result query.
             *
             * @since 3.0
             *
             */
            do_action('wp_sms_delete_group', $result);

            return $result;
        }
    }

    /**
     * Add Group
     *
     * @param Not param
     *
     * @return array
     */
    public static function addGroup($name)
    {
        global $wpdb;
        if (empty($name)) {
            return array(
                'result'  => 'error',
                'message' => esc_html__('Name is empty!', 'wp-sms')
            );
        }

        $table   = $wpdb->prefix . 'sms_subscribes_group';
        $prepare = $wpdb->prepare("SELECT COUNT(ID) FROM {$table} WHERE `name` = %s", $name);
        $count   = $wpdb->get_var($prepare);
        if ($count) {
            return array(
                'result'  => 'error',
                // translators: %s: Group name
                'message' => sprintf(esc_html__('Group Name "%s" exists!', 'wp-sms'), $name)
            );
        } else {
            $result   = $wpdb->insert(
                $wpdb->prefix . "sms_subscribes_group",
                array(
                    'name' => $name,
                )
            );
            $group_id = $wpdb->get_results(
                $wpdb->prepare("SELECT ID FROM {$table} WHERE `name` = %s", $name)
            );

            if ($result) {

                /**
                 * Run hook after adding group.
                 *
                 * @param string $result result query.
                 *
                 * @since 3.0
                 *
                 */
                do_action('wp_sms_add_group', $result);

                return array(
                    'result'  => 'success',
                    'message' => esc_html__('Group successfully added.', 'wp-sms'),
                    'data'    => array(
                        'group_ID' => $group_id[0]->ID
                    )
                );
            }
        }
    }

    /**
     * Update Group
     *
     * @param $id
     * @param $name
     *
     * @return array|void
     * @internal param param $Not
     */
    public static function updateGroup($id, $name)
    {
        global $wpdb;

        if (empty($id) or empty($name)) {
            return;
        }

        $table = $wpdb->prefix . 'sms_subscribes_group';
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(ID) FROM {$table} WHERE `name` = %s", $name)
        );

        if ($count) {
            return array(
                'result'  => 'error',
                // translators: %s: Group name
                'message' => sprintf(esc_html__('Group Name "%s" exists!', 'wp-sms'), $name)
            );
        } else {
            $result = $wpdb->update(
                $wpdb->prefix . "sms_subscribes_group",
                array(
                    'name' => $name,
                ),
                array(
                    'ID' => $id
                )
            );

            if ($result) {

                /**
                 * Run hook after updating group.
                 *
                 * @param string $result result query.
                 *
                 * @since 3.0
                 *
                 */
                do_action('wp_sms_update_group', $result);

                return array('result' => 'success', 'message' => esc_html__('Group successfully updated.', 'wp-sms'));
            } else {
                return array(
                    'result'  => 'error',
                    // translators: %s: Group name
                    'message' => sprintf(esc_html__('Group Name "%s" exists!', 'wp-sms'), $name)
                );
            }
        }
    }

    /**
     * @param bool|array $group_ids
     * @param bool $only_active
     * @param array $columns
     *
     * @return array
     */
    public static function getSubscribers($group_ids = null, $only_active = false, $columns = array())
    {
        global $wpdb;
        $where = '';

        if ($group_ids) {
            $placeholders       = implode(', ', array_fill(0, count($group_ids), '%d'));
            $prepared_group_ids = $wpdb->prepare($placeholders, $group_ids);
            $where              .= "`group_ID` IN ({$prepared_group_ids}) ";
        }

        if ($only_active) {
            if ($where) {
                $where .= $wpdb->prepare("AND `status` = %s ", '1');
            } else {
                $where .= $wpdb->prepare("`status` = %s ", '1');
            }
        }

        if ($where) {
            $where = " WHERE {$where}";
        }

        if (count($columns) == 0) {
            $columns = array('mobile');
        }

        $select = implode(',', $columns);
        $query  = "SELECT {$select} FROM {$wpdb->prefix}sms_subscribes{$where}";

        if (count($columns) > 1) {
            return $wpdb->get_results($query);
        } else {
            return $wpdb->get_col($query);
        }
    }

    /**
     * @param $date
     * @param $name
     * @param $mobile
     * @param $status
     * @param $group_id
     *
     * @return mixed
     */
    public static function insertSubscriber($date, $name, $mobile, $status, $group_id)
    {
        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}sms_subscribes",
            array(
                'date'     => $date,
                'name'     => $name,
                'mobile'   => Helper::sanitizeMobileNumber($mobile),
                'status'   => $status,
                'group_ID' => $group_id
            )
        );

        return $result;
    }

    /**
     * Get Total Subscribers with Group ID
     *
     * @param null $group_id
     *
     * @return Object|null
     */
    public static function getTotal($group_id = null)
    {
        global $wpdb;

        if ($group_id) {
            $result = $wpdb->query($wpdb->prepare("SELECT name FROM {$wpdb->prefix}sms_subscribes WHERE group_ID = %d", $group_id));
        } else {
            $result = $wpdb->query("SELECT name FROM {$wpdb->prefix}sms_subscribes");
        }

        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Filter subscribers by country code
     *
     * @return void
     */
    public static function filterSubscribersByCountry()
    {
        global $wpdb;

        $result        = [];
        $country_codes = wp_sms_countries()->getCountriesMerged();

        foreach ($country_codes as $country_code => $country_name) {
            $temp_result = $wpdb->get_results(
                $wpdb->prepare("SELECT COUNT(mobile) AS 'total' FROM {$wpdb->prefix}sms_subscribes WHERE mobile LIKE %s", $country_code . '%')
            );

            if ($temp_result[0]->total != '0') {
                $result[] = [
                    'name'  => $country_name,
                    'code'  => $country_code,
                    'total' => $temp_result[0]->total
                ];
            }
        }

        return $result;
    }

    /**
     * Process the group_id parameter.
     *
     * @param mixed $group_id The group_id to process.
     *
     * @return array|WP_Error Returns an array of group IDs or an error if the group_id is invalid.
     */
    protected static function processGroupId($group_id)
    {
        if (!empty($group_id)) {
            $group_id  = str_replace('\\', '', $group_id); // Remove backslashes
            $group_ids = json_decode($group_id, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($group_ids)) {
                return new WP_Error('invalid_group_id', esc_html__('Invalid group_id format!', 'wp-sms'));
            }
        } else {
            $group_ids = [null];
        }

        return $group_ids;
    }


    /**
     * Check if a subscriber exists in a specific group.
     *
     * @param string $mobile The mobile number to check.
     * @param int $group_id The group ID to check against.
     * @param bool $only_active Whether to check only active subscribers. Default false.
     *
     * @return bool True if the subscriber exists in the group, false otherwise.
     */
    public static function subscriberExistsInGroup($mobile, $group_id, $only_active = false)
    {
        global $wpdb;

        // Process group_id
        $group_ids = self::processGroupId($group_id);
        if (is_wp_error($group_ids)) {
            return false; // Or handle the error as needed
        }

        $mobile_variations = Helper::prepareMobileNumberQuery($mobile);
        if (empty($mobile_variations)) {
            return false;
        }

        // Construct the query with placeholders
        $mobile_placeholders = implode(', ', array_fill(0, count($mobile_variations), '%s'));
        $group_placeholders  = implode(', ', array_fill(0, count($group_ids), '%d'));

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}sms_subscribes WHERE mobile IN ($mobile_placeholders) AND group_ID IN ($group_placeholders)";

        // Add active status condition if needed
        if ($only_active) {
            $sql .= " AND status = %d";
        }

        // Prepare and execute the query
        $params = array_merge($mobile_variations, $group_ids);
        if ($only_active) {
            $params[] = 1;
        }

        $prepared_sql = $wpdb->prepare($sql, $params);
        $count        = $wpdb->get_var($prepared_sql);

        return $count > 0;
    }

    /**
     * Get subscriber groups by mobile number
     *
     * @param string $mobile The mobile number to search for
     * @param bool $only_active Whether to include only active subscribers. Default false.
     * @return WP_Error Array of group information (empty if no groups found) or WP_Error on failure
     */
    public static function getSubscriberGroupsByNumber($mobile, $only_active = false)
    {
        global $wpdb;

        $mobile_variations = Helper::prepareMobileNumberQuery($mobile);
        if (empty($mobile_variations)) {
            return new WP_Error('invalid_mobile', esc_html__('Invalid mobile number format!', 'wp-sms'));
        }

        $mobile_placeholders = implode(', ', array_fill(0, count($mobile_variations), '%s'));
        $sql                 = "SELECT s.group_ID, g.name 
            FROM {$wpdb->prefix}sms_subscribes s
            LEFT JOIN {$wpdb->prefix}sms_subscribes_group g ON s.group_ID = g.ID
            WHERE s.mobile IN ($mobile_placeholders)";

        if ($only_active) {
            $sql .= " AND s.status = %d";
        }

        $params = $mobile_variations;
        if ($only_active) {
            $params[] = 1;
        }

        $prepared_sql = $wpdb->prepare($sql, $params);
        $results      = $wpdb->get_results($prepared_sql);

        $groups = [];

        if ($results) {
            foreach ($results as $row) {
                if ($row->group_ID) {
                    $groups[] = [
                        'group_id'   => $row->group_ID,
                        'group_name' => $row->name ?: __('No Group', 'wp-sms')
                    ];
                }
            }
        }

        return $groups;
    }

}

new Newsletter();
