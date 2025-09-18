<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Subscribers_List_Table extends \WP_List_Table
{
    protected $db;
    protected $tb_prefix;
    protected $limit;
    protected $count;
    protected $adminUrl;
    var $data;

    public function __construct()
    {
        global $wpdb;

        //Set parent defaults
        parent::__construct(array(
            'singular' => 'ID',     //singular name of the listed records
            'plural'   => 'ID',    //plural name of the listed records
            'ajax'     => false        //does this table support ajax?
        ));

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->count     = $this->get_total();
        $this->limit     = $this->get_items_per_page('wp_sms_subscriber_per_page');
        $this->data      = $this->get_data();
        $this->adminUrl  = admin_url('admin.php?page=wp-sms-subscribers');
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
            case 'mobile':
                return wp_sms_render_quick_reply($item['mobile']);
            case 'activate_key':
                return $item[$column_name];

            case 'group_ID':
                $group = Newsletter::getGroup($item[$column_name]);
                if ($group) {
                    return $group->name;
                } else {
                    return '-';
                }

            case 'date':
                // translators: %1$s: Date, %2$s: Time
                return sprintf(__('%1$s <span class="wpsms-time">%2$s</span>', 'wp-sms'), date_i18n('Y-m-d', strtotime($item[$column_name])), date_i18n('H:i', strtotime($item[$column_name])));

            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    public function column_name($item)
    {
        /**
         * Sanitize the input
         */
        $page  = wp_unslash(sanitize_text_field($_REQUEST['page']));
        $nonce = wp_create_nonce('wp_sms_subscriber');

        //Build row actions
        $actions = array(
            'edit'   => sprintf('<a href="#" onclick="wp_sms_edit_subscriber(%s)">' . esc_html__('Edit', 'wp-sms') . '</a>', $item['ID']),
            'delete' => sprintf('<a href="%s">' . esc_html__('Delete', 'wp-sms') . '</a>', add_query_arg(array('page' => esc_attr($page), 'action' => 'delete', 'ID' => $item['ID'], '_wpnonce' => $nonce), '')),
        );

        //Return the title contents
        return sprintf('%1$s %3$s',
            /*$1%s*/
            esc_html($item['name']),
            /*$2%s*/
            $item['ID'],
            /*$2%s*/
            $this->row_actions($actions)
        );
    }

    public function column_status($item)
    {
        return Helper::loadTemplate('admin/label-button.php', array(
            'type'  => ($item['status'] == '1' ? 'active' : 'inactive'),
            'label' => ($item['status'] == '1' ? esc_html__('Active', 'wp-sms') : esc_html__('Inactive', 'wp-sms'))
        ));
    }

    public function column_custom_fields($item)
    {
        $customFields = unserialize($item['custom_fields']);

        if (is_array($customFields)) {
            foreach ($customFields as $key => $value) {
                printf('<div class="wpsms-custom-field"><strong>%s</strong>: %s</div>', esc_html($key), esc_html($value));
            }
        }
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/
            $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/
            $item['ID']                //The value of the checkbox should be the record's id
        );
    }

    public function get_columns()
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />', //Render a checkbox instead of text
            'name'          => esc_html__('Name', 'wp-sms'),
            'mobile'        => esc_html__('Mobile', 'wp-sms'),
            'group_ID'      => esc_html__('Group', 'wp-sms'),
            'date'          => esc_html__('Date', 'wp-sms'),
            'activate_key'  => esc_html__('Activate code', 'wp-sms'),
            'status'        => esc_html__('Status', 'wp-sms'),
            'custom_fields' => esc_html__('Custom Fields', 'wp-sms'),
        );

        return $columns;
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'ID'            => array('ID', true),     //true means it's already sorted
            'name'          => array('name', false),     //true means it's already sorted
            'mobile'        => array('mobile', false),     //true means it's already sorted
            'group_ID'      => array('group_ID', false),     //true means it's already sorted
            'date'          => array('date', false),   //true means it's already sorted
            'status'        => array('status', false), //true means it's already sorted
            'custom_fields' => array('custom_fields', false), //true means it's already sorted
        );

        if (Option::getOption('newsletter_form_verify')) {
            $sortable_columns['activate_key'] = array('activate_key', false);
        }

        return $sortable_columns;
    }

    public function get_bulk_actions()
    {
        $actions = ['bulk_delete' => esc_html__('Delete', 'wp-sms')];

        $groups = $this->db->get_results("SELECT * FROM `{$this->tb_prefix}sms_subscribes_group`", ARRAY_A);
        if (count($groups)) {
            foreach ($groups as $value) {
                // translators: %s: Group name
                $actions['move_to_' . $value['ID']] = sprintf(__('Move to «%s»', 'wp-sms'), $value['name']);
            }
        }
        return $actions;
    }

    public function process_bulk_action()
    {
        $current_action = $this->current_action();
        // Detect when a bulk action is being triggered

        // Search action
        if (isset($_GET['s']) and $_GET['s']) {
            $metaValue   = Helper::prepareMobileNumberQuery($this->db->esc_like($_GET['s']));
            $metaValue   = "'" . implode("','", $metaValue) . "'";
            $prepare     = $this->db->prepare("SELECT * FROM `{$this->tb_prefix}sms_subscribes` WHERE name LIKE %s OR mobile IN ({$metaValue})", '%' . $this->db->esc_like($_GET['s']) . '%');
            $this->data  = $this->get_data($prepare);
            $this->count = $this->get_total($prepare);
        }

        // Bulk delete action
        if ('bulk_delete' == $current_action) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], "bulk-{$this->_args['plural']}")) {
                Helper::notice(esc_html__('Access denied.', 'wp-sms'), false);
                exit();
            }

            $get_ids = array_map('sanitize_text_field', $_GET['id']);
            foreach ($get_ids as $id) {
                $this->db->delete($this->tb_prefix . "sms_subscribes", ['ID' => intval($id)], ['%d']);
            }
            $this->data  = $this->get_data();
            $this->count = $this->get_total();
            \WP_SMS\Helper::flashNotice(esc_html__('Items removed.', 'wp-sms'), 'success', $this->adminUrl);
        }

        // Single delete action
        if ('delete' == $current_action) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'wp_sms_subscriber')) {
                \WP_SMS\Helper::flashNotice(esc_html__('Access denied.', 'wp-sms'), 'error', $this->adminUrl);
                exit;
            }

            $get_id = sanitize_text_field($_GET['ID']);
            $this->db->delete($this->tb_prefix . "sms_subscribes", ['ID' => intval($get_id)], ['%d']);
            $this->data  = $this->get_data();
            $this->count = $this->get_total();
            \WP_SMS\Helper::flashNotice(esc_html__('Item removed.', 'wp-sms'), 'success', $this->adminUrl);
        }

        if (false !== strpos($current_action, 'move_to_') && isset($_GET['id']) && is_array($_GET['id'])) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], "bulk-{$this->_args['plural']}")) {
                Helper::notice(esc_html__('Access denied.', 'wp-sms'), 'error');
                exit();
            }

            $new_group_id = substr($current_action, 8);
            $new_group    = Newsletter::getGroup($new_group_id);
            if ($new_group) {
                $get_ids = array_map('sanitize_text_field', $_GET['id']);
                foreach ($get_ids as $id) {
                    $this->db->update($this->tb_prefix . "sms_subscribes", ['group_ID' => $new_group->ID], ['ID' => intval($id)], ['%d']);
                }
                $this->data  = $this->get_data();
                $this->count = $this->get_total();
                // translators: %s: Group name
                \WP_SMS\Helper::flashNotice(sprintf(__('Items moved to «%s» group.', 'wp-sms'), $new_group->name), 'success', $this->adminUrl);
            }
        }

        if (!empty($_GET['_wp_http_referer'])) {
            wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))));
            exit;
        }
    }

    public function prepare_items()
    {
        /**
         * First, lets decide how many records per page to show
         */
        $per_page = $this->limit;

        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();

        /**
         * REQUIRED. Finally, we build an array to be used by the class for column
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);

        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();

        /**
         * Instead of querying a database, we're going to fetch the example data
         * property we created for use in this plugin. This makes this example
         * package slightly different than one you might build on your own. In
         * this example, we'll be using array manipulation to sort and paginate
         * our data. In a real-world implementation, you will probably want to
         * use sort and pagination data to build a custom query instead, as you'll
         * be able to use your precisely-queried data immediately.
         */
        $data = $this->data;

        /**
         * This checks for sorting input and sorts the data in our array accordingly.
         *
         * In a real-world situation involving a database, you would probably want
         * to handle sorting by passing the 'orderby' and 'order' values directly
         * to a custom query. The returned data will be pre-sorted, and this array
         * sorting technique would be unnecessary.
         */
        usort($data, '\WP_SMS\Subscribers_List_Table::usort_reorder');

        /**
         * REQUIRED for pagination. Let's check how many items are in our data array.
         * In real-world use, this would be the total number of items in your database,
         * without filtering. We'll need this later, so you should always include it
         * in your own package classes.
         */
        $total_items = $this->count;

        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        $this->items = $data;

        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args(array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //WE have to calculate the total number of pages
        ));
    }

    /**
     * Usort Function
     *
     * @param $a
     * @param $b
     *
     * @return array
     */
    public function usort_reorder($a, $b)
    {
        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'date'; //If no sort, default to sender
        $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'desc'; //If no order, default to asc
        $result  = strcmp($a[$orderby], $b[$orderby]); //Determine sort order

        return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
    }

    // Set $per_page item as int number
    public function get_data($query = '')
    {
        $page_number = ($this->get_pagenum() - 1) * $this->limit;
        $orderby     = "ORDER BY {$this->tb_prefix}sms_subscribes.date DESC";
        $where       = "";
        $params      = array();

        if (isset($_REQUEST['orderby'])) {
            $orderby = "ORDER BY {$this->tb_prefix}sms_subscribes.{$_REQUEST['orderby']} {$_REQUEST['order']}";
        }

        if (!$query) {
            if (isset($_GET['group_id']) && $_GET['group_id']) {
                $group_id = sanitize_text_field($_GET['group_id']);
                $where    = "WHERE group_ID = %d";
                $params[] = intval($group_id);
            }

            if (isset($_GET['country_code']) && $_GET['country_code']) {
                $country_code = sanitize_text_field($_GET['country_code']);

                if ($where) {
                    $where .= " AND mobile LIKE %s";
                } else {
                    $where = "WHERE mobile LIKE %s";
                }

                $params[] = $country_code . '%';
            }

            $params[] = $this->limit;
            $params[] = $page_number;

            $query = $this->db->prepare("SELECT * FROM {$this->tb_prefix}sms_subscribes {$where} {$orderby} LIMIT %d OFFSET %d", $params);
        } else {
            $query .= $this->db->prepare(" LIMIT %d OFFSET %d", $this->limit, $page_number);
        }

        $result = $this->db->get_results($query, ARRAY_A);
        return $result;
    }

    // Get total items on different Queries
    public function get_total($query = '')
    {
        if (!$query) {
            $query = 'SELECT * FROM `' . $this->tb_prefix . 'sms_subscribes`';
        }

        $result = $this->db->get_results($query, ARRAY_A);

        return count($result);
    }

    /**
     * @param $which
     * @return void
     */
    protected function extra_tablenav($which)
    {
        switch ($which) {
            case 'top':

                // Filter by Group
                $group_filter_args = [
                    'groups'   => Newsletter::getGroups(),
                    'selected' => (isset($_GET['group_id']) ? sanitize_text_field($_GET['group_id']) : '')
                ];
                echo Helper::loadTemplate('admin/group-filter.php', $group_filter_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                // Filter by Country
                $country_filter_args = [
                    'countries' => Newsletter::filterSubscribersByCountry(),
                    'selected'  => (isset($_GET['country_code']) ? sanitize_text_field($_GET['country_code']) : '')
                ];
                echo Helper::loadTemplate('admin/country-filter.php', $country_filter_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                break;
        }
    }
}
