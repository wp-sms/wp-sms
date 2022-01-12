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
                    $option_fields = [];

                    foreach ($field['elements'] as $elements) {
                        foreach ($elements['elements'] as $element) {
                            if (isset($element['label'])) {
                                $option_fields[$element['id']] = $element['label'];
                            }
                        }
                    }

                    return $option_fields;
                }
            }
        }

        return;
    }
}

new Quform();