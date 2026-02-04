<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly


class Gravityforms
{
    /**
     * Field types that should be excluded from phone number field selection
     * These are non-input fields that cannot contain a phone number
     */
    private static $excludedFieldTypes = [
        'submit',
        'button',
        'html',
        'section',
        'page',
        'captcha',
        'honeypot',
    ];

    static function get_field($form_id)
    {
        $option_field = array();

        if (!$form_id) {
            return $option_field;
        }

        if (!class_exists('RGFormsModel')) {
            return $option_field;
        }

        $fields = \RGFormsModel::get_form_meta($form_id);

        if ($fields) {
            foreach ($fields['fields'] as $field) {
                // Get field type - handle both array and object access
                $fieldType = isset($field['type']) ? $field['type'] : (isset($field->type) ? $field->type : '');

                // Skip non-input fields (buttons, sections, etc.)
                if (in_array($fieldType, self::$excludedFieldTypes, true)) {
                    continue;
                }

                if (isset($field['label'])) {
                    $option_field[$field['id']] = $field['label'];
                } elseif (isset($field->label)) {
                    $option_field[$field->id] = $field->label;
                }
            }

            return $option_field;
        }
    }
}