<?php

namespace WP_SMS\Settings;

use WP_SMS\Settings\Field;

class FieldValidator
{
    /**
     * Get the default validation callback for a given field type.
     *
     * @param string $type
     * @return callable
     */
    public static function get($type)
    {
        switch ($type) {
            case 'checkbox':
                return function ($value) {
                    return is_bool($value) ? true : 'Must be true or false';
                };

            case 'number':
                return function ($value) {
                    return is_numeric($value) ? true : 'Must be a number';
                };

            case 'text':
            case 'textarea':
            case 'image':
                return function ($value) {
                    return is_string($value) ? true : 'Must be a string';
                };

            case 'multiselect':
            case 'select':
            case 'advancedselect':
            case 'countryselect':
                return [__CLASS__, 'validateSelect'];

            case 'array':
                return function ($value) {
                    return is_array($value) ? true : 'Must be an array';
                };

            default:
                return function () {
                    return true; // Accept anything if unknown type
                };
        }
    }

    /**
     * Validate a value against a field's defined options (for select-like types).
     *
     * @param mixed $value
     * @param Field|null $field
     * @return true|string
     */
    public static function validateSelect($value, Field $field = null)
    {
        if (!$field instanceof Field) {
            return 'Invalid field context for select validation';
        }

        $validOptions = self::flattenOptions($field->getOptions());

        if (is_array($value)) {
            foreach ($value as $v) {
                if (!in_array($v, $validOptions, true)) {
                    return "Invalid option selected: '{$v}'";
                }
            }
            return true;
        }

        if (!in_array($value, $validOptions, true)) {
            return "Invalid option selected: '{$value}'";
        }

        return true;
    }

    /**
     * Flatten options array, especially for nested advancedselect types.
     *
     * @param array $options
     * @return array
     */
    protected static function flattenOptions($options)
    {
        $flattened = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                // Grouped options (advancedselect)
                $flattened = array_merge($flattened, array_keys($value));
            } else {
                $flattened[] = $key;
            }
        }

        return $flattened;
    }
}
