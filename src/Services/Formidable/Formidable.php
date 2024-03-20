<?php

namespace WP_SMS\Services\Formidable;

use FrmField;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;

class Formidable
{

    private $fields;
    private $data;

    public function init()
    {
        add_filter("frm_pre_create_entry", array($this, 'pre_create'), 30, 2);
        add_action("frm_after_create_entry", array($this, 'handle_sms'), 30, 2);
    }

    public function handle_sms($entry_id, $form_id)
    {
        $base_options = Option::getOption('formidable_metabox');
        $sms_options  = Option::getOption('formdiable_wp_sms_options_' . $this->data['form_id']);

        $formidableNotification = NotificationFactory::getFormidable($this->data['form_id'], $this->data);

        if (!$base_options) return;

        if (isset($sms_options['phone']) && isset($sms_options['message'])) {
            $formidableNotification->send(
                $sms_options['message'],
                $sms_options['phone']
            );
        }

        if (isset($sms_options['field']['phone']) && isset($sms_options['field']['message']) && isset($this->data[$sms_options['field']['phone']])) {
            $formidableNotification->send(
                $sms_options['field']['message'],
                $this->data[$sms_options['field']['phone']]
            );
        }
    }

    public function pre_create($values)
    {
        $values          = wp_sms_sanitize_array($values);
        $data            = [];
        $data['form_id'] = $values['form_id'];

        if (isset($values['item_meta'])) {
            $this->fields = $this->get_form_fields($values['form_id']);

            foreach ($values['item_meta'] as $key => $value) {

                if (isset($this->fields[$key])) {
                    $data[$this->fields[$key]] = $value;
                }
            }
        }

        $this->data = $data;

        return $values;
    }

    public static function get_form_fields($form_id)
    {
        $final  = [];
        $fields = FrmField::get_all_for_form($form_id);

        foreach ($fields as $field) {
            $final[$field->id] = strtolower(str_replace(' ', '-', $field->name));
        }

        return $final;
    }
}
