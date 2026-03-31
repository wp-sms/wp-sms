<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\UserInfo;
use WSms\Support\UserMeta;

class UserInfoTest extends TestCase
{
    // --- isPlaceholderEmail ---

    public function testIsPlaceholderEmailReturnsTrueForPlaceholder(): void
    {
        $this->assertTrue(UserInfo::isPlaceholderEmail('abc123def0@noreply.wsms.local'));
    }

    public function testIsPlaceholderEmailReturnsFalseForRealEmail(): void
    {
        $this->assertFalse(UserInfo::isPlaceholderEmail('user@example.com'));
    }

    public function testIsPlaceholderEmailReturnsFalseForSimilarDomain(): void
    {
        $this->assertFalse(UserInfo::isPlaceholderEmail('user@notnoreply.wsms.local'));
    }

    // --- isPlaceholderUsername ---

    public function testIsPlaceholderUsernameReturnsTrueForPlaceholder(): void
    {
        $this->assertTrue(UserInfo::isPlaceholderUsername('wsms_abc123'));
    }

    public function testIsPlaceholderUsernameReturnsFalseForRegularUsername(): void
    {
        $this->assertFalse(UserInfo::isPlaceholderUsername('john_doe'));
    }

    // --- hasUsablePassword ---

    public function testHasUsablePasswordReturnsTrueWhenMetaNotSet(): void
    {
        $GLOBALS['_test_user_meta'][1] = [];
        $this->assertTrue(UserInfo::hasUsablePassword(1));
    }

    public function testHasUsablePasswordReturnsTrueWhenExplicitlySet(): void
    {
        $GLOBALS['_test_user_meta'][1][UserMeta::HAS_USABLE_PASSWORD] = '1';
        $this->assertTrue(UserInfo::hasUsablePassword(1));
    }

    public function testHasUsablePasswordReturnsFalseWhenExplicitlyUnset(): void
    {
        $GLOBALS['_test_user_meta'][1][UserMeta::HAS_USABLE_PASSWORD] = '0';
        $this->assertFalse(UserInfo::hasUsablePassword(1));
    }

    // --- getUserVerificationState ---

    public function testGetUserVerificationStateWithRealEmail(): void
    {
        $GLOBALS['_test_userdata'] = (object) ['user_email' => 'user@example.com'];
        $GLOBALS['_test_user_meta'][10] = [
            UserMeta::EMAIL_VERIFIED => '1',
            UserMeta::PHONE          => '+1234567890',
            UserMeta::PHONE_VERIFIED => '',
        ];

        $state = UserInfo::getUserVerificationState(10);

        $this->assertTrue($state['email']['has']);
        $this->assertTrue($state['email']['verified']);
        $this->assertTrue($state['phone']['has']);
        $this->assertFalse($state['phone']['verified']);
    }

    public function testGetUserVerificationStateWithPlaceholderEmail(): void
    {
        $GLOBALS['_test_userdata'] = (object) ['user_email' => 'abc@noreply.wsms.local'];
        $GLOBALS['_test_user_meta'][11] = [];

        $state = UserInfo::getUserVerificationState(11);

        $this->assertFalse($state['email']['has']);
        $this->assertFalse($state['email']['verified']);
        $this->assertFalse($state['phone']['has']);
        $this->assertFalse($state['phone']['verified']);
    }

    // --- isPhoneTaken ---

    public function testIsPhoneTakenReturnsFalseForInvalidPhone(): void
    {
        $this->assertFalse(UserInfo::isPhoneTaken('not-a-phone'));
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_user_meta'],
            $GLOBALS['_test_userdata'],
        );
    }
}
