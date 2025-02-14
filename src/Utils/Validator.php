<?php

namespace WP_SMS\Utils;

class Validator
{
    protected $data;
    protected $rules;
    protected $errors = [];

    protected $messages = [];

    public function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
    }

    public function setMessages()
    {
        $this->messages = [
            'required' => __('The :attribute field is required.', 'wp-sms'),
            'email'    => __('The :attribute must be a valid email address.', 'wp-sms'),
            'min'      => __('The :attribute must be at least :min characters.', 'wp-sms'),
            'max'      => __('The :attribute may not be greater than :max characters.', 'wp-sms'),
            'numeric'  => __('The :attribute must be a number.', 'wp-sms'),

        ];
    }

    public function passes()
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules = explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $rule = trim($rule);

                if (strpos($rule, ':') !== false) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                    $param = trim($param);
                } else {
                    $ruleName = $rule;
                    $param    = null;
                }

                $method = "validate" . ucfirst($ruleName);
                if (method_exists($this, $method) && !$this->$method($field, $value, $param)) {
                    $this->addError($field, $ruleName, $param);
                }
            }
        }

        return empty($this->errors);
    }

    public function fails()
    {
        return !$this->passes();
    }

    public function errors()
    {
        return $this->errors;
    }

    protected function validateRequired($field, $value)
    {
        return !is_null($value) && trim($value) !== '';
    }

    protected function validateEmail($field, $value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin($field, $value, $param)
    {
        if (is_numeric($value)) {
            return $value >= (float)$param;
        }
        return mb_strlen($value) >= (int)$param;
    }

    protected function validateMax($field, $value, $param)
    {
        if (is_numeric($value)) {
            return $value <= (float)$param;
        }
        return mb_strlen($value) <= (int)$param;
    }

    protected function validateNumeric($field, $value)
    {
        return is_numeric($value);
    }

    protected function addError($field, $rule, $param = null)
    {
        $message = str_replace(':attribute', ucfirst($field), $this->messages[$rule] ?? 'Invalid input.');

        if ($param !== null) {
            $message = str_replace(":$rule", $param, $message);
        }

        $this->errors[$field][] = $message;
    }
}
