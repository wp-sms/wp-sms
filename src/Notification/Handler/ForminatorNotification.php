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

    public function __construct($form, $data  = [])
    {
        $this->data = $data;

        if ($form) {
            $fields = Forminator::formFields($form);
            if ($fields) {
                foreach ($fields as $key => $value) {
                    $this->variables["%field-$key%"] = "getFormField_$key";
                }
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

    /**
     * __call method handles dynamic methods fields for which come from the form itself 
     * e.g: field-email 
     *
     * @param [type] $method
     * @param [type] $args
     * @return void
     */
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
