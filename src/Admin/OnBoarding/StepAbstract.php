<?php

namespace WP_SMS\Admin\OnBoarding;

use WP_SMS\Components\View;
use WP_SMS\Utils\Request;
use WP_SMS\Utils\Validator;

abstract class StepAbstract
{
    protected $skippable = true;
    protected $data = [];
    protected $fields = [];
    protected $errors = [];

    public function __construct()
    {
        $this->initialize();
        $this->setFields();
    }

    protected function setFields()
    {
        $this->fields = $this->getFields();
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
        $data = [];

        foreach ($this->fields as $field) {
            $data[$field] = Request::get($field);
        }

        if (empty($this->validate($data))) {
            foreach ($data as $field => $value) {
                $this->setData($field, $value);
            }
            return true;
        }

        return $this->errors;
    }

    public function validate($data)
    {
        if (empty($this->validationRules())) {
            return [];
        }
        $validator = new Validator($data, $this->validationRules());

        if ($validator->fails()) {
            $this->errors = $validator->errors();
        }

        return $this->errors;
    }

    abstract protected function validationRules();

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
