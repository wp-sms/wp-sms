<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Helper;
use WP_SMS\Newsletter;

class SubscriberFormAjax extends AjaxControllerAbstract {
	protected $action = 'wp_sms_edit_subscriber';

	protected function run() {
		$subscriber_id = $this->get( 'subscriber_id' );

		echo Helper::loadTemplate( 'admin/subscriber-form.php', array(
			'subscriber_id' => $subscriber_id,
			'subscriber'    => Newsletter::getSubscriber( $subscriber_id ),
			'groups'        => Newsletter::getGroups()
		) );

		exit;
	}
}