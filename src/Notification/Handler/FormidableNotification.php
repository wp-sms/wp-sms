<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;
use WP_SMS\Services\Formidable\Formidable;

class FormidableNotification extends Notification
{

    protected $data = [];

    protected $variables = [
        '%site_name%'    => 'getSiteName',
        '%site_url%'     => 'getSiteUrl',
    ];

    public function __construct($form, $data = [])
    {
        $this->data = $data;

        if ($form) {
            $fields = (Formidable::get_form_fields($form));
            foreach ($fields as $key => $value) {
                $this->variables["%field-$value%"] = "getFormField_$value";
            }
        }
    }

    public function getSiteName()
    {
        return get_bloginfo('name');
    }

    public function getSiteUrl()
    {
        return get_bloginfo('url');
    }


    public function __call($method, $args)
    {
        if (!method_exists($this, $method)) {
            if (strpos($method, "getFormField_") !== false) {
                $field = str_replace("getFormField_", "", $method);
                return $this->data[$field];
            }
        }
    }
}
