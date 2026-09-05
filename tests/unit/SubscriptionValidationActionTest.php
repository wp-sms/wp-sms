<?php

namespace unit;

use WP_SMS\Controller\PublicSubscribeAjax;
use WP_UnitTestCase;

class SubscriptionValidationActionTest extends WP_UnitTestCase
{
    public function testAllowsSafeStructuredActions()
    {
        $actions = PublicSubscribeAjax::sanitizeValidationActions(array(
            array(
                'label'   => '<strong>Text START</strong>',
                'href'    => 'sms:+155****4567?body=START',
                'type'    => 'link',
                'target'  => '_blank',
                'rel'     => 'nofollow external',
                'onclick' => 'alert(1)',
                'style'   => 'color:red',
            ),
            array(
                'label' => 'Email support',
                'href'  => 'mailto:support@example.com',
            ),
            array(
                'label' => 'Help center',
                'href'  => 'https://example.com/help',
            ),
        ));

        $this->assertSame(array(
            array(
                'label'  => 'Text START',
                'href'   => 'sms:+155****4567?body=START',
                'type'   => 'sms',
                'target' => '_blank',
                'rel'    => 'nofollow noopener noreferrer',
            ),
            array(
                'label' => 'Email support',
                'href'  => 'mailto:support@example.com',
                'type'  => 'link',
            ),
            array(
                'label' => 'Help center',
                'href'  => 'https://example.com/help',
                'type'  => 'link',
            ),
        ), $actions);
    }

    public function testRejectsUnsafeUrlsAndStripsUnsafeAttributes()
    {
        $actions = PublicSubscribeAjax::sanitizeValidationActions(array(
            array('label' => 'Run script', 'href' => 'javascript:alert(1)'),
            array('label' => 'Image payload', 'href' => 'data:image/svg+xml,<svg onload=alert(1)>'),
            array('label' => 'Insecure site', 'href' => 'http://example.com'),
            array('label' => array('Not text'), 'href' => 'https://example.com'),
            array('label' => 'Missing URL'),
            '<script>alert(1)</script>',
            array(
                'label'   => 'Safe link',
                'href'    => 'https://example.com',
                'target'  => 'javascript:alert(1)',
                'rel'     => 'opener external',
                'onclick' => 'alert(1)',
                'onerror' => 'alert(1)',
                'style'   => 'background:url(javascript:alert(1))',
                'script'  => '<script>alert(1)</script>',
                'img'     => '<img src=x onerror=alert(1)>',
            ),
        ));

        $this->assertSame(array(
            array(
                'label' => 'Safe link',
                'href'  => 'https://example.com',
                'type'  => 'link',
            ),
        ), $actions);
    }
}
