<?php

namespace WP_SMS\Admin\OnBoarding;

use WP_SMS\Components\View;
use WP_SMS\Utils\Request;
use WP_SMS\Utils\Validator;

abstract class StepAbstract
{
    public $title;
    private $skippable = true;
    private $data = [];
    private $fields = [];
    private $errors = [];

    public function __construct()
    {
        $this->initialize();
        $this->title  = $this->getTitle();
        $this->fields = $this->getFields();
    }

    abstract protected function initialize();

    abstract public function getSlug();

    abstract protected function getTitle();

    abstract protected function getDescription();

    public function render($data)
    {
        View::load(sprintf('pages/onboarding/steps/%s', $this->getSlug()), $data);
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

        if (!$this->validate($data)) {
            foreach ($data as $field => $value) {
                $this->setData($field, $value);
            }
            $this->afterValidation();
            return true;
        }

        return $this->errors;
    }

    public function validate($data)
    {
        $rules = $this->validationRules();
        if (!$rules) {
            return [];
        }

        $validator = new Validator($data, $rules);

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

    public function afterValidation()
    {
    }
}
