<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;
use WP_SMS\Services\Forminator\Forminator;

class ForminatorNotification extends Notification
{

    protected $data = [];

    protected $variables = [
        '%site_name%' => 'getSiteName',
        '%site_url%'  => 'getSiteUrl',
    ];

    public function __construct($form, $data)
    {
        $this->data = $data;

        if ($this->data) {
            foreach (Forminator::formFields($form) as $key => $value) {
                $this->variables["%field-$key%"] = "getFormField_$key";
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

            if (str_contains($method, "getFormField_")) { // @todo, should work with PHP 5.6 or greater
                $field = str_replace("getFormField_", "", $method);
                return $this->data[$field];
            }
        }
    }
}
