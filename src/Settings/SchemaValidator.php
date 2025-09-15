<?php

namespace WP_SMS\Settings;

use WP_SMS\Settings\SchemaRegistry;

class SchemaValidator
{
    /**
     * Validate and sanitize an entire array of settings.
     *
     * @param array $input
     * @return array [$cleaned, $errors]
     */
    public static function validate(array $input): array
    {
        $cleaned = [];
        $errors  = [];

        $schema = SchemaRegistry::instance()->allGroupsIncludingHidden();

        foreach ($schema as $groupName => $group) {
            foreach ($group->getFields() as $field) {
                $key   = $field->getKey();

                if (!array_key_exists($key, $input)) {
                    continue;
                }

                $value = $input[$key];

                // Sanitize
                $sanitized = is_callable($field->getSanitizeCallback())
                    ? call_user_func($field->getSanitizeCallback(), $value)
                    : $value;

                // Validate
                $callback = $field->getValidateCallback();
                $validationResult = is_callable($callback)
                    ? call_user_func($callback, $sanitized, $field)
                    : true;

                if ($validationResult === true) {
                    $cleaned[$key] = $sanitized;
                } else {
                    $errors[$key] = is_string($validationResult) ? $validationResult : 'Invalid value';
                }
            }
        }

        return [$cleaned, $errors];
    }

    /**
     * Sanitize a single key/value.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public static function sanitizeSingle(string $key, $value)
    {
        $field = self::getFieldByKey($key);

        if ($field && is_callable($field->getSanitizeCallback())) {
            return call_user_func($field->getSanitizeCallback(), $value);
        }

        return $value;
    }

    /**
     * Validate a single key/value.
     *
     * @param string $key
     * @param mixed $value
     * @return true|string
     */
    public static function validateSingle(string $key, $value)
    {
        $field = self::getFieldByKey($key);

        if ($field && is_callable($field->getValidateCallback())) {
            $result = call_user_func($field->getValidateCallback(), $value);
            return $result === true ? true : (is_string($result) ? $result : 'Invalid value');
        }

        return true;
    }

    /**
     * Locate a field object by its key.
     *
     * @param string $key
     * @return Field|null
     */
    protected static function getFieldByKey(string $key): ?Field
    {
        $schema = SchemaRegistry::instance()->all();

        foreach ($schema as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getKey() === $key) {
                    return $field;
                }
            }
        }

        return null;
    }
}
