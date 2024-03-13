<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Helper;

class GroupFormAjax extends AjaxControllerAbstract {
	protected $action = 'wp_sms_edit_group';

	protected function run() {
        $args = [
            'group_id'   => $this->get( 'group_id' ),
            'group_name' => $this->get( 'group_name' )
        ];

		echo Helper::loadTemplate( '/admin/group-form.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}
}