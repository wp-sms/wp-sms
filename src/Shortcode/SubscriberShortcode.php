<?php

namespace WP_SMS\Shortcode;

use WP_SMS\Newsletter;

class SubscriberShortcode
{
    public function register()
    {
        add_shortcode('wp_sms_subscriber_form', array($this, 'registerSubscriberShortcodeCallback'));
    }

    public function registerSubscriberShortcodeCallback($attributes)
    {
        $attrs = $attributes;

        if (isset($attributes['groups'])) {
            $attrs['groups'] = $this->retrieveGroupsData($attributes);
        }
        if (isset($attributes['fields'])) {
            $attrs['fields'] = $this->retrieveFieldsData($attributes);
        }

        return wp_sms_subscriber_form($attrs);
    }


    public function retrieveGroupsData($attrs)
    {
        $groups           = self::explodeData($attrs['groups']);
        $newsletterGroups = Newsletter::getGroups();

        foreach ($newsletterGroups as $key => $group) {
            if (!in_array($group->ID, $groups)) {
                unset($newsletterGroups[$key]);
            }
        }

        return $newsletterGroups;
    }

    public function retrieveFieldsData($attrs)
    {
        $fields        = array();
        $custom_fields = explode('|', $attrs['fields']);

        foreach ($custom_fields as $custom_field) {
            $field          = explode(':', $custom_field);
            $label          = strtolower(ltrim($field[0]));
            $fields[$label] = array(
                'label'       => $label,
                'type'        => 'text',
                'description' => isset($field[1]) ? $field[1] : '',
            );
        }

        return $fields;
    }

    public static function explodeData($string)
    {
        $delimiters = ['|', ',', ', ', '-'];
        $string     = str_replace($delimiters, $delimiters[0], $string);

        return explode($delimiters[0], $string);
    }
}