<?php 
namespace WP_SMS\Services\Forminator;

use Forminator_API;
use WP_SMS\Notification\Handler\ForminatorNotification;

include WP_SMS_DIR . "src/Notification/Handler/ForminatorNotification.php";

class Forminator
{

    private $data;

    public function init()
    {
        add_action("forminator_form_draft_after_save_entry", array($this, 'handle_sms'), 10, 2);  
        add_action("forminator_form_after_save_entry", array($this, 'handle_sms'), 10, 2);  
    }


    public function handle_sms($form, $res)
    {       
        //forminator-sms-from &&‌  forminator-sms
        $sms_options       = get_option('wpsms_settings');
        $this->set_data();

        /**
         * Send SMS to the specific number or subscribers' group
         */
        if ((isset($sms_options['forminator_notify_enable_form_'.$form]) || 
        isset($sms_options['forminator_notify_receiver_form_'.$form])) && 
        isset($sms_options['forminator_notify_message_form_'.$form])) {
            
            (new ForminatorNotification($form, $this->data))->send(
                $sms_options['forminator_notify_message_form_'.$form],
                $sms_options['forminator_notify_receiver_form_'.$form]
            );


        }
        
        if ((isset($sms_options['forminator_notify_enable_field_form_'.$form]) || 
        isset($sms_options['forminator_notify_receiver_field_form_'.$form])) && 
        isset($sms_options['forminator_notify_message_field_form_'.$form])) {
            
            if(isset($this->data[$sms_options['forminator_notify_receiver_field_form_'.$form]]))
            {
                (new ForminatorNotification($form, $this->data))->send(
                    $sms_options['forminator_notify_message_field_form_'.$form],
                    $this->data[$sms_options['forminator_notify_receiver_field_form_'.$form]]
                );
            }
        }

    }
    private function set_data()
    {
        foreach ($_POST as $index => $key) {
            if (is_array($key)) {
                $this->data[$index] = implode(', ', $key);
            } else {
                $this->data[$index] = $key;
            }
        }
    }

    public static function formFields($form)
    {
        $form_fields = Forminator_API::get_form_fields($form);                
        $fields = [];
        foreach($form_fields as $field)
        {
            $fields[$field->slug] = $field->raw['field_label'];
        };
        return $fields;
    }

}