<?php

namespace WP_SMS\Blocks;

use WP_SMS;
use WP_SMS\Newsletter;

class SendSmsBlock extends BlockAbstract
{
    protected $blockName = 'SendSms';
    protected $blockVersion = '1.0';
    protected $script = 'wp-sms-blocks-send-sms-editor-script';

    protected function output($attributes)
    {
        return wp_sms_send_sms_form($attributes);
    }

    public function ajaxData()
    {
        // Create an array of role options
        $all_roles    = wp_roles()->get_names();
        $role_options = array(['label' => __('All Roles', 'wp-sms'), 'value' => 'all']);
        foreach ($all_roles as $role_key => $role_label) {
            $role_options[] = array(
                'label' => $role_label,
                'value' => $role_key,
            );
        }

        // Create ab array of subscribe group options
        $groups        = Newsletter::getGroups();
        $group_options = array(['label' => __('Select a group', 'wp-sms'), 'value' => ''], ['label' => __('General', 'wp-sms'), 'value' => 0]);
        foreach ($groups as $group) {
            $group_options[] = array(
                'value' => $group->ID,
                'label' => $group->name
            );
        }

        // Define the options to pass to your JavaScript.
        $data = array(
            'userRoleOptions'  => $role_options,
            'subscriberGroups' => $group_options,
        );

        return $data;
    }
}