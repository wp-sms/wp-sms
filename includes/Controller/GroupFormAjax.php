<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Helper;

class GroupFormAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_edit_group';

    protected function run()
    {
        try {

            echo Helper::loadTemplate('/admin/group-form.php', array(
                'group_id'   => $this->get('group_id'),
                'group_name' => $this->get('group_name')
            ));

            exit;

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), $e->getCode());
        }
    }
}