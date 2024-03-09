<?php 
namespace WP_SMS\Services\Forminator;

use WP_SMS\Newsletter;

class Forminator
{

    private $data;

    public function init()
    {
        add_action("forminator_form_draft_after_save_entry", array($this, 'handle_sms'), 10, 2);  
        add_action("forminator_form_after_save_entry", array($this, 'handle_sms'), 10, 2);  
        // add_action("forminator_custom_form_mail_before_send_mail", array($this, 'handle_sms'), 10, 4);  
        // add_filter('forminator_form_admin_data', array($this, 'test2'), 10, 3);
        // add_filter("forminator_custom_form_admin_page_entries", array($this,"test3"),10,3);
    }


    public function handle_sms($form, $res)
    {       
        //forminator-sms-from &&‌  forminator-sms
        $sms_options       = get_option('wp_sms_forminator_form' . $form);
        $to_options = $sms_options['forminator-sms']; 
        $from_options = $sms_options['forminator-sms-from']; 
        $this->set_data();
                
        /**
         * Send SMS to the specific number or subscribers' group
         */
        if ((isset($to_options['phone']) || isset($to_options['recipient'])) && isset($to_options['message'])) {
            
            switch ($to_options['recipient']) {
                case 'subscriber':
                    $to = Newsletter::getSubscribers($to_options['groups'], true);
                    break;

                default:
                    $to = explode(',', $to_options['phone']);
                    break;
            }

            $message = preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) {
                $form_tags = $this->data;
                $tag       = $matches[1];

                if (array_key_exists($tag, $form_tags)) {
                    return $this->data[$tag];
                } else {
                    return $matches[0];
                }
            }, $to_options['message']);


            if ($to && count($to) && $message) {
                wp_sms_send($to, $message);
            }

        }


        /**
         * Send SMS to a specific field
         */
        if ($from_options['message'] && $from_options['phone']) {
            $to = preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) {
                foreach ($matches as $item) {
                    if (isset($this->data[$item])) {
                        return $this->data[$item];
                    }
                }
            }, $from_options['phone']);
            

            // Check if the type of the field is select
            // if($form->fields)
            // {
            //     foreach ($form->fields as $field) {
            //         if (is_a($field, Forminator_Select::class)) {
            //             foreach ($scan_form_tag['raw_values'] as $raw_value) {
            //                 $option = explode('|', $raw_value);

            //                 if (isset($option[0]) and $option[0] == $to) {
            //                     $to = $option[1];
            //                 }
            //             }
            //         }
            //     }
            // }

            if (strpos($to, ',') !== false) {
                $to = explode(',', $to);
            } else if (strpos($to, '|') !== false) {
                $to = explode('|', $to);
            }

            $to      = is_array($to) ? $to : array($to);
            $message = preg_replace_callback('/%([a-zA-Z0-9._-]+)%/', function ($matches) {
                foreach ($matches as $item) {
                    if (isset($this->data[$item])) {
                        return $this->data[$item];
                    }
                }
            }, $from_options['message']);

            if ($to && count($to) && $message) {
                wp_sms_send($to, $message);
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


}