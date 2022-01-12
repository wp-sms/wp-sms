<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Quform
{
    /**
     * Get each form Fields
     *
     * @param $form_id
     * @return array|void
     */
    static function get_fields($form_id)
    {
        if (!$form_id) {
            return;
        }

        if (!class_exists('Quform_Repository')) {
            return;
        }

        $quform = new \Quform_Repository();
        $fields = $quform->allForms();

        if (!$fields) {
            return;
        }

        foreach ($fields as $field) {
            if ($field['id'] == $form_id) {
                return self::getFieldsFromElements($field);
            }
        }
    }

    static function getFieldsFromElements($array)
    {
        $fields = [];
        foreach ($array['elements'] as $element) {

            if (isset($element['elements'])) {
                foreach ($element['elements'] as $item) {
                    if (isset($item['elements'])) {
                        $fields += self::getFieldsFromElements($item);
                    } else {
                        $fields[$item['id']] = $item['label'];
                    }
                }
            } else {
                $fields[$element['id']] = $element['label'];
            }

        }

        return $fields;
    }
}

new Quform();