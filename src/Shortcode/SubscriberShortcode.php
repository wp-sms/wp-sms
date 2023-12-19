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
        $attrs = shortcode_atts([
            'title'       => __('Subscribe SMS', 'wp-sms'),
            'description' => '',
            'groups'      => '',
            'fields'      => '',
        ], $attributes);

        if (isset($attrs['groups'])) {
            $attrs['groups'] = $this->retrieveGroupsData($attributes);
        }
        if (isset($attrs['fields'])) {
            $attrs['fields'] = $this->retrieveFieldsData($attributes);
        }

        return wp_sms_subscriber_form($attrs);
    }

    public function retrieveGroupsData($attrs)
    {
        if (isset($attrs['groups'])) {
            $groups           = self::explodeData($attrs['groups']);
            $newsletterGroups = Newsletter::getGroups();

            foreach ($newsletterGroups as $key => $group) {
                if (!in_array($group->ID, $groups)) {
                    unset($newsletterGroups[$key]);
                }
            }

            return $newsletterGroups;
        }
    }

    public function retrieveFieldsData($attrs)
    {
        $fields = array();

        if (isset($attrs['fields'])) {
            $custom_fields = explode('|', $attrs['fields']);

            foreach ($custom_fields as $custom_field) {
                $field          = explode(':', $custom_field);
                $label          = $this->_sanitizeFiledAttr(strtolower(ltrim($field[0])));
                $description    = isset($field[1]) ? $this->_sanitizeFiledAttr($field[1]) : '';
                $fields[$label] = array(
                    'label'       => $label,
                    'type'        => 'text',
                    'description' => $description,
                );
            }
        }
        return $fields;
    }

    private function _sanitizeFiledAttr($attr)
    {
        return preg_replace('/[^a-zA-Z0-9 ]/', '', $attr);
    }

    public static function explodeData($string)
    {
        $delimiters = ['|', ',', ', ', '-'];
        $string     = str_replace($delimiters, $delimiters[0], $string);

        return explode($delimiters[0], $string);
    }
}