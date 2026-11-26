<?php

namespace unit;

use WP_SMS\Controller\PrivacyDataAjax;
use WP_UnitTestCase;

class PrivacyDataExportTest extends WP_UnitTestCase
{
    public function testSubscriberRowsCarryTheirId()
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'sms_subscribes', array(
            'date'   => current_time('mysql'),
            'name'   => 'Test User',
            'mobile' => '+12025550123',
            'status' => '1',
        ));
        $id = $wpdb->insert_id;

        $rows = (new PrivacyDataAjax())->checkUserExistMobile('+12025550123');

        $this->assertCount(1, $rows);
        $this->assertEquals($id, $rows[0]['ID']);
    }
}
