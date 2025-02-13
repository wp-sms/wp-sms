<?php

namespace WP_SMS\Admin\OnBoarding;

use WP_SMS\Components\View;

abstract class StepAbstract
{
    protected $skippable = true;
    protected $data = [];
    protected $fields = [];

    public function __construct()
    {
        $this->initialize();
    }

    abstract protected function initialize();

    abstract public function getSlug();

    abstract protected function getTitle();

    abstract protected function getDescription();

    public function render($data)
    {
        View::load('pages/onboarding/steps/' . $this->getSlug(), $data);
    }

    public function process()
    {
        if (empty($this->fields)) {
            return true;
        }

        if ($this->validate()) {
            foreach ($this->fields as $field => $value) {
                $this->setData($field, $value);
            }
            return true;
        }

        return false;
    }

    public function validate()
    {
        //todo error handling
        $this->errors = [];

        foreach ($this->fields as $field => $value) {
            if (empty($value)) {
                $this->errors[$field] = "The field '{$field}' is required.";
            }
        }

        return empty($this->errors);
    }

    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function getCTAs()
    {
        return [];
    }

    public function isCompleted()
    {
        return true;
    }
}
