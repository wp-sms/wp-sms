<?php 
namespace WP_SMS\Services\Formidable;

use Forminator_API;
use FrmEntry;
use FrmField;
use FrmForm;
use Symfony\Component\DomCrawler\Field\FormField;
use WP_SMS\Notification\Handler\FormidableNotification;
use WP_SMS\Notification\Handler\ForminatorNotification;

include WP_SMS_DIR . "src/Notification/Handler/FormidableNotification.php";

class Formidable
{

    private $fields;
    private $data;

    public function init()
    {
        add_action("frm_pre_create_entry", array($this, 'pre_create'), 30, 2);  
        add_action("frm_after_create_entry", array($this, 'handle_sms'), 30, 2);  
        
    }


    public function handle_sms($entry_id, $form_id)
    {      
        $sms_options       = get_option("formdiable_wp_sms_options_" . $this->data['form_id']);
        $base_options       = get_option("wpsms_settings");
        
        // /**
        //  * Send SMS to the specific number or subscribers' group
        //  */
        if(!isset($base_options["formidable_metabox"])) return;
        if (isset($sms_options['phone']) && 
        isset($sms_options['message'])) {
            (new FormidableNotification($this->fields, $this->data))->send(
                $sms_options['message'],
                $sms_options['phone']
            );


        }
        
        if (isset($sms_options['phone']) && 
        isset($sms_options['message'])) {

            (new FormidableNotification($this->fields, $this->data))->send(
                $sms_options['field']['message'],
                $sms_options['field']['phone']
            );
        }

    }

    public function pre_create($values)
    {
        $data = [];
        $data['form_id'] = $values['form_id'];
        if(isset($values['item_meta']))
        {   
            $this->fields = $this->get_form_fields($values['form_id']);
            foreach ($values['item_meta'] as $key => $value) {
                
                if(isset($this->fields[$key])){
                    
                    $data[$this->fields[$key]] = $value;
                    
                };
            }
        }
        $this->data = $data;
        return;
    }

    public function get_form_fields($form_id)
    {
        $final = [];
        $fields = FrmField::get_all_for_form($form_id);

        foreach($fields as $field){
            $final[$field->id] = strtolower(str_replace(' ', '-', $field->name));
        }
        return $final;

    }

}