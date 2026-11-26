<?php

namespace unit;

use WP_SMS\Controller\PublicUnsubscribeAjax;
use WP_SMS\Newsletter;
use WP_UnitTestCase;

class UnsubscribeTest extends WP_UnitTestCase
{
    private $mobile = '+12025550123';

    protected function tearDown(): void
    {
        $_REQUEST = array();

        parent::tearDown();
    }

    public function testLookupByNumberReturnsEachRowOnce()
    {
        $this->addRow($this->mobile, 0);

        $this->assertCount(1, Newsletter::getSubscriberByMobile($this->mobile, false));
    }

    public function testUnsubscribeWithGroupsOffReportsSuccess()
    {
        $this->addRow($this->mobile, 0);

        $this->assertUnsubscribed();
    }

    public function testNumberWithSeveralRowsIsRemovedWithoutAnError()
    {
        $this->addRow($this->mobile, 1);
        $this->addRow($this->mobile, 2);

        $this->assertUnsubscribed();
    }

    private function assertUnsubscribed()
    {
        global $wpdb;

        update_option('wpsms_settings', array());
        $_REQUEST = array('name' => 'Test User', 'mobile' => $this->mobile);

        $dieHandler = function () {
            return function () {
                throw new \RuntimeException('wp_die');
            };
        };

        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $dieHandler);

        $run = new \ReflectionMethod(PublicUnsubscribeAjax::class, 'run');
        if (PHP_VERSION_ID < 80100) {
            $run->setAccessible(true);
        }

        $error = '';
        ob_start();
        try {
            $run->invoke(new PublicUnsubscribeAjax());
        } catch (\RuntimeException $e) {
            // wp_send_json_success() ends the request.
        } catch (\Exception $e) {
            $error = $e->getMessage();
        } finally {
            $output = ob_get_clean();
            remove_filter('wp_die_ajax_handler', $dieHandler);
            remove_filter('wp_doing_ajax', '__return_true');
        }

        $this->assertSame('', $error);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertSame('0', $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sms_subscribes WHERE mobile = %s", $this->mobile)));
    }

    private function addRow($mobile, $groupId)
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'sms_subscribes', array(
            'date'     => current_time('mysql'),
            'name'     => 'Test User',
            'mobile'   => $mobile,
            'status'   => '1',
            'group_ID' => $groupId,
        ));
    }
}
