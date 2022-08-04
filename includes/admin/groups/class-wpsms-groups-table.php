<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Subscribers_Groups_List_Table extends \WP_List_Table
{

    protected $db;
    protected $tb_prefix;
    protected $limit;
    protected $count;
    protected $adminUrl;
    var $data;

    function __construct()
    {
        global $wpdb;

        //Set parent defaults
        parent::__construct(array(
            'singular' => 'ID',     //singular name of the listed records
            'plural'   => 'ID',    //plural name of the listed records
            'ajax'     => false    //does this table support ajax?
        ));

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->count     = $this->get_total();
        $this->limit     = 50;
        $this->data      = $this->get_data();
        $this->adminUrl  = admin_url('admin.php?page=wp-sms-subscribers-group');
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'ID':
            case 'name':
                return $item[$column_name];

            case 'total_subscribers':
                $total = Newsletter::getTotal($item['ID']);
                if ($total) {
                    return $total;
                } else {
                    return '0';
                }

            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function column_name($item)
    {
        /**
         * Sanitize the input
         */
        $page = sanitize_text_field($_REQUEST['page']);

        //Build row actions
        $actions = array(
            'edit'   => sprintf('<a href="#" onclick="wp_sms_edit_group(%d, \'%s\')" />' . __('Edit', 'wp-sms') . '</a>', $item['ID'], esc_attr($item['name'])),
            'delete' => sprintf('<a href="?page=%s&action=%s&ID=%s">' . __('Delete', 'wp-sms') . '</a>', $page, 'delete', $item['ID']),
        );

        //Return the title contents
        return sprintf('%1$s %3$s',
            /*$1%s*/
            wp_sms_render_quick_reply($item['name'], $item['ID']),
            /*$1%s*/
            $item['ID'],
            /*$2%s*/
            $this->row_actions($actions)
        );
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/
            $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/
            $item['ID']                //The value of the checkbox should be the record's id
        );
    }

    function get_columns()
    {
        $columns = array(
            'cb'                => '<input type="checkbox" />', //Render a checkbox instead of text
            'ID'                => __('Group ID', 'wp-sms'),
            'name'              => __('Name', 'wp-sms'),
            'total_subscribers' => __('Total subscribers', 'wp-sms'),
        );

        return $columns;
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'ID'   => array('ID', true),     //true means it's already sorted
            'name' => array('name', false),     //true means it's already sorted
        );

        return $sortable_columns;
    }

    function get_bulk_actions()
    {
        $actions = array(
            'bulk_delete' => __('Delete', 'wp-sms')
        );

        return $actions;
    }

    function process_bulk_action()
    {

        //Detect when a bulk action is being triggered...
        // Search action
        if (isset($_GET['s'])) {
            $prepare     = $this->db->prepare("SELECT * from `{$this->tb_prefix}sms_subscribes_group` WHERE name LIKE %s", '%' . $this->db->esc_like($_GET['s']) . '%');
            $this->data  = $this->get_data($prepare);
            $this->count = $this->get_total($prepare);
        }

        // Bulk delete action
        if ('bulk_delete' == $this->current_action()) {
            $get_ids = array_map('sanitize_text_field', $_GET['id']);
            foreach ($get_ids as $id) {
                $this->db->delete($this->tb_prefix . "sms_subscribes_group", array('ID' => intval($id)), ['%d']);
            }

            $this->data  = $this->get_data();
            $this->count = $this->get_total();
            \WP_SMS\Admin\Helper::addFlashNotice(__('Items removed.', 'wp-sms'), 'success', $this->adminUrl);
        }

        // Single delete action
        if ('delete' == $this->current_action()) {
            $get_id = sanitize_text_field($_GET['ID']);
            $this->db->delete($this->tb_prefix . "sms_subscribes_group", array('ID' => intval($get_id)), ['%d']);
            $this->data  = $this->get_data();
            $this->count = $this->get_total();
            \WP_SMS\Admin\Helper::addFlashNotice(__('Item removed.', 'wp-sms'), 'success', $this->adminUrl);
        }

        if (!empty($_GET['_wp_http_referer'])) {
            wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
            exit;
        }
    }

    function prepare_items()
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

        usort($data, '\WP_SMS\Subscribers_Groups_List_Table::usort_reorder');

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
    function usort_reorder($a, $b)
    {
        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'ID'; //If no sort, default to sender
        $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'desc'; //If no order, default to asc
        $result  = strcmp($a[$orderby], $b[$orderby]); //Determine sort order

        return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
    }

    //set $per_page item as int number
    function get_data($query = '')
    {
        $page_number = ($this->get_pagenum() - 1) * $this->limit;
        $orderby     = "";

        if (isset($_REQUEST['orderby'])) {
            $orderby .= "ORDER BY {$this->tb_prefix}sms_subscribes_group.{$_REQUEST['orderby']} {$_REQUEST['order']}";
        }

        if (!$query) {
            $query = $this->db->prepare("SELECT * FROM {$this->tb_prefix}sms_subscribes_group {$orderby} LIMIT %d OFFSET %d", $this->limit, $page_number);
        } else {
            $query .= $this->db->prepare(" LIMIT %d OFFSET %d", $this->limit, $page_number);
        }

        $result = $this->db->get_results($query, ARRAY_A);

        return $result;
    }

    //get total items on different Queries
    function get_total($query = '')
    {
        if (!$query) {
            $query = 'SELECT * FROM `' . $this->tb_prefix . 'sms_subscribes_group`';
        }
        $result = $this->db->get_results($query, ARRAY_A);
        $result = count($result);

        return $result;
    }

}
