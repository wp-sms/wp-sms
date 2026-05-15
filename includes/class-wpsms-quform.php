<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Quform
{
    /**
     * Element types that should be excluded from phone number field selection
     * These are non-input elements that cannot contain a phone number
     */
    private static $excludedElementTypes = [
        'submit',
        'button',
        'recaptcha',
        'hcaptcha',
        'turnstile',
        'html',
        'page',
        'group',
        'row',
        'column',
    ];

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
                $fields += self::getFieldsFromElements($element);
                continue;
            }

            $elementType = $element['type'] ?? '';
            if (in_array($elementType, self::$excludedElementTypes, true)) {
                continue;
            }

            $fields[$element['id']] = $element['label'];
        }

        return $fields;
    }
}

new Quform();