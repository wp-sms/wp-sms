<?php 
namespace WP_SMS\Services\Forminator;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

use Forminator_API;

class ForminatorListTable extends \WP_List_Table 
{

    private $table_data;
    
    // Define table columns
    public function get_columns()
    {
        $columns = array(
                'cb'            => '<input type="checkbox" />',
                'id'          => __('ID', 'wp-sms'),
                'name'          => __('Name', 'wp-sms'),
                'status'   => __('Status', 'wp-sms'),
                'action'   => __('Action', 'swp-sms'),
        );
        return $columns;
    }


    public function prepare_items()
    {
        //data
        $this->table_data = Forminator_API::get_forms();
        /* pagination */
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = count($this->table_data);

        $this->table_data = array_slice($this->table_data, (($current_page - 1) * $per_page), $per_page);
        
        $this->set_pagination_args(array(
            'total_items' => $total_items, // total number of items
            'per_page'    => $per_page, // items to show on a page
            'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
        ));


        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $primary = "name";
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        usort($this->table_data, array(&$this, 'usort_reorder'));


        $this->items = $this->table_data;   
    }



    function column_default($item, $column_name)
    {
        switch ($column_name) {
                case "action" :
                    $current_url =  home_url( add_query_arg( NULL, NULL )  ) .'&form=' . $item->id ;
                    return "<a href='$current_url '>Edit</a>";
                case 'id':
                case 'name':
                case 'status':
                default:
                    return $item->$column_name;
          }
    }

    function column_cb($item)
    {
        return sprintf(
                '<input type="checkbox" name="element[]" value="%s" />',
                $item->id
        );
    }

    protected function get_sortable_columns()
    {
          $sortable_columns = array(
                'id'   => array('id', true),
                'name'  => array('name', false),
                'status' => array('status', false),
          );
          return $sortable_columns;
    }
    
        // Sorting function
    public    function usort_reorder($a, $b)
        {
            // If no sort, default to user_login
            $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'id';
    
            // If no order, default to asc
            $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
    
            // Determine sort order
            $result = strcmp($a->$orderby, $b->$orderby);
    
            // Send final sort direction to usort
            return ($order === 'asc') ? $result : -$result;
        }

}