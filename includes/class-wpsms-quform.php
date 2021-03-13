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

                if ($field['elements']) {
                    foreach ($field['elements'] as $elements) {
                        foreach ($elements['elements'] as $element) {
                            $option_field[$element['id']] = $element['label'];
                        }
                    }

                    return $option_field;
                }
            }
        }

        return;
    }
}

new Quform();