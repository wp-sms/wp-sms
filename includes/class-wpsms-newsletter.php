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
        add_action('wp_enqueue_scripts', array($this, 'load_script'));
    }

    /**
     * Include front table
     */
    public function load_script()
    {
        // jQuery will be included automatically
        wp_enqueue_script('wpsms-ajax-script', WP_SMS_URL . 'assets/js/script.js', array('jquery'), WP_SMS_VERSION);

        // Ajax params
        wp_localize_script('wpsms-ajax-script', 'wpsms_ajax_object', array(
            'ajaxurl'         => get_rest_url(null, 'wpsms/v1/newsletter'),
            'unknown_error'   => __('Unknown Error! Check your connection and try again.', 'wp-sms'),
            'loading_text'    => __('Loading...', 'wp-sms'),
            'subscribe_text'  => __('Subscribe', 'wp-sms'),
            'activation_text' => __('Activation', 'wp-sms')
        ));
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

        if (self::isDuplicate($mobile, $group_id)) {
            return array('result' => 'error', 'message' => __('The mobile numbers has been already duplicate.', 'wp-sms'));
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
        $result = $wpdb->delete(
            $wpdb->prefix . "sms_subscribes",
            array(
                'mobile'   => $mobile,
                'group_id' => $group_id,
            )
        );

        if (!$result) {
            return array('result' => 'error', 'message' => __('The subscribe does not exist.', 'wp-sms'));
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

        return array('result' => 'success', 'message' => __('Subscribe successfully removed.', 'wp-sms'));
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

        if (self::isDuplicate($mobile, $group_id, $id)) {
            return array('result' => 'error', 'message' => __('The mobile numbers has been already duplicate.', 'wp-sms'));
        }

        $result = $wpdb->update(
            $wpdb->prefix . "sms_subscribes",
            array(
                'name'     => $name,
                'mobile'   => $mobile,
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
            return array('result' => 'error', 'message' => __('Having problem with update subscriber, Duplicate entries or subscriber not found! please try again.', 'wp-sms'));
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
     * @return array|object|null
     */
    public static function getGroups()
    {
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sms_subscribes_group`");

        if ($result) {
            return $result;
        }
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
     * Check the mobile number is duplicate
     *
     * @param $mobile_number
     * @param null $group_id
     * @param null $id
     *
     * @return mixed
     */
    public static function isDuplicate($mobile_number, $group_id = null, $id = null)
    {
        global $wpdb;
        $sql = "SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE mobile = '" . $mobile_number . "'";

        if ($group_id) {
            $sql .= " AND group_id = '" . $group_id . "'";
        }

        if ($id) {
            $sql .= " AND id != '" . $id . "'";
        }

        $result = $wpdb->get_row($sql);

        return $result;
    }


    /**
     * @param string $group_id
     *
     * @return array
     */
    public static function getSubscribers($group_id = '')
    {
        global $wpdb;

        $where = '';

        if ($group_id) {
            $where = $wpdb->prepare(' WHERE group_ID = %d', $group_id);
        }

        $result = $wpdb->get_col("SELECT `mobile` FROM {$wpdb->prefix}sms_subscribes" . $where);

        return $result;

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

        $result = $wpdb->insert("{$wpdb->prefix}sms_subscribes",
            array(
                'date'     => $date,
                'name'     => $name,
                'mobile'   => $mobile,
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
     * Load NewsLetter form for Shortcode or Widget usage
     *
     * @param null $widget_id
     * @param null $instance
     */
    public static function loadNewsLetter($widget_id = null, $instance = null)
    {
        global $wpdb;
        $get_group_result = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sms_subscribes_group`");

        include_once WP_SMS_DIR . "includes/templates/subscribe-form.php";
    }

}

new Newsletter();
