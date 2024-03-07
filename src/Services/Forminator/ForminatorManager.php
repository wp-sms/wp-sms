<?php 
namespace WP_SMS\Services\Forminator;

use Forminator_API;
use WP_SMS\Helper;
use WP_SMS\Newsletter;

include "ForminatorListTable.php";

class ForminatorManager
{    
    public function init()
    {   
        
        add_action('admin_menu', array($this, 'forminator_panel_submenu'));
        
        add_action( 'wp_ajax_forminator_form_sms_data', 'wp_ajax_forminator_form_sms_data' );

    
    }


    public function forminator_panel_submenu()
    {
     
        add_submenu_page(
            'forminator',
            'wp sms',
            'wp sms',
            'manage_options',
            'wp-sms-forminator',
            array($this, 'forminator_panel_callback') );


        add_action( 'admin_notices', array($this, 'admin_notif'), 10, 1 );
        
    }


    public function forminator_panel_callback()
    {
        
        if($form = wp_sms_sanitize_array($_GET['form'])){

            
            if($_POST['submit_action'] == 'forminator_form_sms_data' && wp_verify_nonce( $_POST['_wpnonce']) ){

                $this->wp_ajax_forminator_form_sms_data($form);

            }
            ob_start();
            
            $form = Forminator_API::get_form($form);

            $get_group_result = Newsletter::getGroups();
            $sms_data = get_option('wp_sms_forminator_form' . $form->id);
            
            echo Helper::loadTemplate('forminator/forminator-form.php', [
                'form' => $form,
                'sms_data' => $sms_data,
                'get_group_result' => $get_group_result
            ]);
            
            return;

            
        }


        // $forms = Forminator_API::get_forms();
        $formTable = new ForminatorListTable();
        $formTable->prepare_items();
        echo Helper::loadTemplate('forminator/forminator-list.php', ['list_table' => $formTable]);
    }

    public function admin_notif()
    {   
        if($_POST['submit_action'] == 'forminator_form_sms_data' && wp_verify_nonce( $_POST['_wpnonce']))
        {
            $class = 'notice notice-success';
            $message = __( 'Form ('. $_GET['form'] .  ') Sms details has been updated successfuly.', 'sample-text-domain' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
    }

    /**
     * This proccess should be ajax 
     * This is the temperory implementations
     *
     * @return void
     */
    private function wp_ajax_forminator_form_sms_data($formID)
    {
        $form = wp_sms_sanitize_array($_POST);
        $data = collect($form)->only('forminator-sms', 'forminator-sms-from')->toArray();
        if($data)
            update_option('wp_sms_forminator_form'.$formID, $data);
     
        return;
    }

}