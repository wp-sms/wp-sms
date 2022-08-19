<?php

namespace WP_SMS;

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
    public function getUnSubscriberQueryString()
    {
        return apply_filters('wpsms_unsubscribe_query_string', 'wpsms_unsubscribe');
    }

    /**
     * @param $number
     * @return string
     */
    public function generateUnSubscribeUrlByNumber($number)
    {
        $unSubscribeUrl = add_query_arg([
            $this->getUnSubscriberQueryString() => $number
        ], get_bloginfo('url'));

        return wp_sms_shorturl($unSubscribeUrl);
    }

    /**
     * Unsubscribe a number by query string action
     *
     */
    public function unSubscriberNumberByUrlAction()
    {
        $unSubscriberQueryString = $this->getUnSubscriberQueryString();

        if (!isset($_REQUEST[$unSubscriberQueryString]) || !wp_unslash($_REQUEST[$unSubscriberQueryString])) {
            return;
        }

        $number  = wp_unslash(trim($_REQUEST[$unSubscriberQueryString]));
        $numbers = [$number, "+{$number}"];

        foreach ($numbers as $number) {
            $response = $this->deleteSubscriberByNumber($number);

            do_action('wp_sms_number_unsubscribed_through_url', $number);

            if ($response['result'] == 'success') {
                wp_die($response['message'], __('SMS Subscription!'), [
                    'link_text' => __('Home page', 'wp-sms'),
                    'link_url'  => get_bloginfo('url'),
                ]);
            }
        }

        wp_die($response['message'], __('SMS Subscription!'), [
            'link_text' => __('Home page', 'wp-sms'),
            'link_url'  => get_bloginfo('url'),
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
     *
     * @return array
     */
    public static function addSubscriber($name, $mobile, $group_id = '', $status = '1', $key = null)
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
                'date'         => WP_SMS_CURRENT_DATE,
                'name'         => $name,
                'mobile'       => $mobile,
                'status'       => $status,
                'activate_key' => $key,
                'group_ID'     => $group_id,
            )
        );

        if ($result) {
            /**
             * Run hook after adding subscribe.
             *
             * @param string $name name.
             * @param string $mobile mobile.
             *
             * @since 3.0
             *
             */
            do_action('wp_sms_add_subscriber', $name, $mobile);

            return array('result' => 'success', 'message' => __('Subscriber successfully added.', 'wp-sms'));
        } else {
            return array('result' => 'error', 'message' => __('Having problem with add subscriber, please try again later.', 'wp-sms'));
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
        $result = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE ID = '" . $id . "'");

        if ($result) {
            return $result;
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

        $where['mobile'] = $mobile;

        if ($group_id) {
            $where['group_id'] = $group_id;
        }

        $result = $wpdb->delete("{$wpdb->prefix}sms_subscribes", $where);

        if (!$result) {
            return array('result' => 'error', 'message' => __('The mobile number does not exist!', 'wp-sms'));
        }

        /**
         * Run hook after deleting subscribe.
         *
         * @param string $result result query.
         *
         * @since 3.0
         *
         */
        do_action('wp_sms_delete_subscriber', $result);

        return array('result' => 'success', 'message' => __('Successfully canceled the subscription!', 'wp-sms'));
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
            return array('result' => 'error', 'message' => __('The fields must be valued.', 'wp-sms'));
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

            return array('result' => 'success', 'message' => __('Subscriber successfully updated.', 'wp-sms'));
        } else {
            return array('result' => 'error', 'message' => __('No change has been occurred.', 'wp-sms'));
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

        $db_prepare = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes_group` WHERE `ID` = %d", $group_id);
        $result     = $wpdb->get_row($db_prepare);

        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * Get Groups
     *
     * @param array|null $groupIds
     * @return array|object|null
     */
    public static function getGroups($groupIds = null)
    {
        global $wpdb;
        $where = '';

        if (is_array($groupIds) && !empty($groupIds)) {
            $groups = implode(',', wp_sms_sanitize_array($groupIds));
            $where  .= "`ID` IN ({$groups}) ";
        }

        $where = !empty($where) ? "WHERE {$where}" : '';

        return $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sms_subscribes_group`" . $where);
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
            return array('result' => 'error', 'message' => __('Name is empty!', 'wp-sms'));
        }

        $table   = $wpdb->prefix . 'sms_subscribes_group';
        $prepare = $wpdb->prepare("SELECT COUNT(ID) FROM {$table} WHERE `name` = %s", $name);
        $count   = $wpdb->get_var($prepare);
        if ($count) {
            return array(
                'result'  => 'error',
                'message' => sprintf(__('Group Name "%s" exists!', 'wp-sms'), $name)
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . "sms_subscribes_group",
                array(
                    'name' => $name,
                )
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

                return array('result' => 'success', 'message' => __('Group successfully added.', 'wp-sms'));
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

        $table   = $wpdb->prefix . 'sms_subscribes_group';
        $prepare = $wpdb->prepare("SELECT COUNT(ID) FROM {$table} WHERE `name` = %s", $name);
        $count   = $wpdb->get_var($prepare);

        if ($count) {
            return array(
                'result'  => 'error',
                'message' => sprintf(__('Group Name "%s" exists!', 'wp-sms'), $name)
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

                return array('result' => 'success', 'message' => __('Group successfully updated.', 'wp-sms'));
            } else {
                return array(
                    'result'  => 'error',
                    'message' => sprintf(__('Group Name "%s" exists!', 'wp-sms'), $name)
                );
            }
        }
    }

    /**
     * @param string $group_ids
     *
     * @return array
     */
    public static function getSubscribers($group_ids = false, $only_active = false)
    {
        global $wpdb;
        $where = '';

        if ($group_ids) {
            $groups = implode(',', wp_sms_sanitize_array($group_ids));
            $where  .= "`group_ID` IN ({$groups}) ";
        }

        if ($only_active) {
            if ($where) {
                $where .= "AND `status` = '1' ";
            } else {
                $where .= "`status` = '1' ";
            }
        }

        if ($where) {
            $where = " WHERE {$where}";
        }

        return $wpdb->get_col("SELECT `mobile` FROM {$wpdb->prefix}sms_subscribes" . $where);
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
}

new Newsletter();
