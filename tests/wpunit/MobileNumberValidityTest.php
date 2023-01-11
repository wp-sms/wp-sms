<?php

class MobileNumberValidityTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    public function testNumeric()
    {
        $validity = \WP_SMS\Helper::checkMobileNumberValidity('+1111111111');

        $this->assertTrue($validity);
    }

    public function testNotNumeric()
    {
        $validity = \WP_SMS\Helper::checkMobileNumberValidity('+hello');

        $this->assertInstanceOf(WP_Error::class, $validity);
    }

    public function testDuplicateNumberInUserMeta()
    {
        add_user_meta(1, 'mobile', '+1111111111');

        $validity = \WP_SMS\Helper::checkMobileNumberValidity('+1111111111');

        $this->assertInstanceOf(WP_Error::class, $validity);
        $this->assertStringContainsString($validity->get_error_code(), 'is_duplicate');
    }
}
