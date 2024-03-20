<?php

namespace WP_SMS\Services\Formidable;

use FrmField;
use WP_SMS\Helper;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;

class FormidableManager
{

    public function init()
    {

        add_filter('wp_sms_registered_integration_tabs', function ($tabs) {
            $tabs['formidable'] = __('Formidable', 'wp-sms');
            return $tabs;
        });
        add_filter('wp_sms_formidable_settings', array($this, 'setting_fields'));

        $wp_sms_options = Option::getOptions();

        if (isset($wp_sms_options['formidable_metabox']) && $wp_sms_options['formidable_metabox']) {
            add_filter('frm_add_form_settings_section', array($this, "frm_add_new_settings_tab"), 10, 2);
            add_filter('frm_form_options_before_update', array($this, 'frm_save_new_settings_tab'), 20, 2);
        }

        $Formidable = new Formidable();
        $Formidable->init();
    }

    public function setting_fields($options)
    {
        $formidable_array = array();

        if (is_plugin_active('formidable/formidable.php')) {
            $formidable_array['formidable_title']   = array(
                'id'   => 'formidable_title',
                'name' => __('SMS Notification Metabox', 'wp-sms'),
                'type' => 'header',
                'doc'  => '',
                'desc' => __('By this option you can add SMS notification tools in all edit forms.', 'wp-sms'),
            );
            $formidable_array['formidable_metabox'] = array(
                'id'      => 'formidable_metabox',
                'name'    => __('Status', 'wp-sms'),
                'type'    => 'checkbox',
                'options' => $options,
                'desc'    => __('This option adds SMS Notification tab in the Settings forms.', 'wp-sms')
            );
        } else {
            $formidable_array['formidable_notify_form'] = array(
                'id'   => 'formidable_notify_form',
                'name' => __('Not active', 'wp-sms'),
                'type' => 'notice',
                'desc' => __('Formidable plugin should be enable to run this tab', 'wp-sms')
            );
        }

        return $formidable_array;
    }

    public function frm_add_new_settings_tab($sections, $values)
    {
        $sections[] = array(
            'name'     => __('SMS Notifications', 'wp-sms'),
            'anchor'   => 'wp_sms_notification',
            'function' => 'get_wp_sms_settings',
            'class'    => $this
        );

        return $sections;
    }

    public function get_wp_sms_settings($values)
    {
        $values   = wp_sms_sanitize_array($values);
        $sms_data = Option::getOption("formdiable_wp_sms_options_" . $values['id']);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Helper::loadTemplate('formidable/formidable-form.php', [
            'form'       => $values['id'],
            'sms_data'   => $sms_data,
            'formFields' => $this->formfileds($values['id']),
            'fieldGroup' => NotificationFactory::getFormidable($values['id'])->getVariables()
        ]);
    }

    public function frm_save_new_settings_tab($options, $values)
    {
        $values = wp_sms_sanitize_array($values);

        if (isset($values['id']) && isset($values['formidable-sms'])) {
            $id = $values['id'];
            Option::updateOption("formdiable_wp_sms_options_$id", wp_sms_sanitize_array($values['formidable-sms']));
        }

        return $options;
    }

    protected function formfileds($form)
    {
        $final  = array();
        $fields = FrmField::get_all_for_form($form);

        foreach ($fields as $field) {
            $final[$field->id] = strtolower(str_replace(' ', '-', $field->name));
        }

        return $final;
    }
}
