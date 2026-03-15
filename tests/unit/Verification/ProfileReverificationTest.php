<?php

namespace WSms\Tests\Unit\Verification;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\AccountManager;
use WSms\Auth\SettingsRepository;
use WSms\Verification\ProfileReverification;

class ProfileReverificationTest extends TestCase
{
    private ProfileReverification $reverification;
    private MockObject&AccountManager $accountManager;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_userdata'] = null;
        $GLOBALS['_test_wc_notices'] = [];

        $this->accountManager = $this->createMock(AccountManager::class);
        $this->reverification = new ProfileReverification($this->accountManager, new SettingsRepository());
    }

    // --- WordPress profile email intercept ---

    public function testInterceptWpEmailChangeRevertsEmailAndDelegatesToAccountManager(): void
    {
        $user = new \WP_User(1);
        $user->user_email = 'old@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $this->accountManager->expects($this->once())
            ->method('updateProfile')
            ->with(1, ['email' => 'new@example.com'])
            ->willReturn(['success' => true, 'message' => 'OK', 'email_verification_required' => true]);

        $data = ['user_email' => 'new@example.com', 'user_login' => 'test'];
        $result = $this->reverification->interceptWpEmailChange($data, true, 1, []);

        // Email should be reverted to the original.
        $this->assertSame('old@example.com', $result['user_email']);
    }

    public function testInterceptWpEmailChangeSkipsWhenEmailUnchanged(): void
    {
        $user = new \WP_User(1);
        $user->user_email = 'same@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $this->accountManager->expects($this->never())->method('updateProfile');

        $data = ['user_email' => 'same@example.com'];
        $result = $this->reverification->interceptWpEmailChange($data, true, 1, []);

        $this->assertSame('same@example.com', $result['user_email']);
    }

    public function testInterceptWpEmailChangeSkipsOnInsert(): void
    {
        $this->accountManager->expects($this->never())->method('updateProfile');

        $data = ['user_email' => 'new@example.com'];
        $result = $this->reverification->interceptWpEmailChange($data, false, null, []);

        $this->assertSame('new@example.com', $result['user_email']);
    }

    // --- WooCommerce email intercept ---

    public function testInterceptWcEmailChangeAddsErrorAndReverts(): void
    {
        // WC sets $user->user_email to the NEW email before the hook fires.
        $user = new \WP_User(1);
        $user->user_email = 'new@example.com';

        // get_userdata() returns the CURRENT (old) email from DB.
        $dbUser = new \WP_User(1);
        $dbUser->user_email = 'old@example.com';
        $GLOBALS['_test_userdata'] = $dbUser;

        $_POST['account_email'] = 'new@example.com';

        $this->accountManager->expects($this->once())
            ->method('updateProfile')
            ->with(1, ['email' => 'new@example.com'])
            ->willReturn(['success' => true, 'message' => 'OK', 'email_verification_required' => true]);

        $errors = new \WP_Error();
        $this->reverification->interceptWcEmailChange($errors, $user);

        $this->assertSame('wsms_email_reverify', $errors->get_error_code());
        // Both $user and POST should be reverted to old email.
        $this->assertSame('old@example.com', $user->user_email);
        $this->assertSame('old@example.com', $_POST['account_email']);
    }

    public function testInterceptWcEmailChangeSkipsWhenUnchanged(): void
    {
        // WC sets $user->user_email, but it matches the DB value.
        $user = new \WP_User(1);
        $user->user_email = 'same@example.com';

        $dbUser = new \WP_User(1);
        $dbUser->user_email = 'same@example.com';
        $GLOBALS['_test_userdata'] = $dbUser;

        $_POST['account_email'] = 'same@example.com';

        $this->accountManager->expects($this->never())->method('updateProfile');

        $errors = new \WP_Error();
        $this->reverification->interceptWcEmailChange($errors, $user);

        $this->assertEmpty($errors->get_error_code());
    }

    public function testInterceptWcEmailChangeAddsErrorOnFailure(): void
    {
        $user = new \WP_User(1);
        $user->user_email = 'new@example.com';

        $dbUser = new \WP_User(1);
        $dbUser->user_email = 'old@example.com';
        $GLOBALS['_test_userdata'] = $dbUser;

        $_POST['account_email'] = 'new@example.com';

        $this->accountManager->method('updateProfile')
            ->willReturn(['success' => false, 'error' => 'cooldown', 'message' => 'Please wait.']);

        $errors = new \WP_Error();
        $this->reverification->interceptWcEmailChange($errors, $user);

        $this->assertSame('wsms_email_change_failed', $errors->get_error_code());
    }

    // --- WooCommerce phone intercept ---

    public function testInterceptWcPhoneChangeCreatesVerification(): void
    {
        $GLOBALS['_test_user_meta'][1]['billing_phone'] = '+1234567890';
        $_POST['billing_phone'] = '+0987654321';

        $this->accountManager->expects($this->once())
            ->method('updateProfile')
            ->with(1, ['phone' => '+0987654321'])
            ->willReturn(['success' => true, 'message' => 'OK', 'phone_verification_required' => true]);

        $this->reverification->interceptWcPhoneChange(1, 'billing', [], null);

        $this->assertCount(1, $GLOBALS['_test_wc_notices']);
        $this->assertSame('notice', $GLOBALS['_test_wc_notices'][0]['type']);
    }

    public function testInterceptWcPhoneChangeSkipsNonBilling(): void
    {
        $this->accountManager->expects($this->never())->method('updateProfile');

        $this->reverification->interceptWcPhoneChange(1, 'shipping', [], null);

        $this->assertEmpty($GLOBALS['_test_wc_notices']);
    }

    public function testInterceptWcPhoneChangeSkipsWhenUnchanged(): void
    {
        $GLOBALS['_test_user_meta'][1]['billing_phone'] = '+1234567890';
        $_POST['billing_phone'] = '+1234567890';

        $this->accountManager->expects($this->never())->method('updateProfile');

        $this->reverification->interceptWcPhoneChange(1, 'billing', [], null);

        $this->assertEmpty($GLOBALS['_test_wc_notices']);
    }

    public function testInterceptWcPhoneChangeShowsErrorOnFailure(): void
    {
        $GLOBALS['_test_user_meta'][1]['billing_phone'] = '+1234567890';
        $_POST['billing_phone'] = '+0987654321';

        $this->accountManager->method('updateProfile')
            ->willReturn(['success' => false, 'error' => 'phone_exists', 'message' => 'Phone taken.']);

        $this->reverification->interceptWcPhoneChange(1, 'billing', [], null);

        $this->assertCount(1, $GLOBALS['_test_wc_notices']);
        $this->assertSame('error', $GLOBALS['_test_wc_notices'][0]['type']);
    }

    protected function tearDown(): void
    {
        unset($_POST['account_email'], $_POST['billing_phone']);
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_wc_notices'] = [];
        unset($GLOBALS['_test_userdata']);
    }
}
