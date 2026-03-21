<?php

namespace Tests\Unit\PhoneRestriction;

use PHPUnit\Framework\TestCase;
use WSms\PhoneRestriction\CountryResolver;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\PhoneRestriction\SendingPolicyGuard;

class SendingPolicyGuardTest extends TestCase
{
    private RestrictionSettings $settings;
    private SendingPolicyGuard $guard;

    protected function setUp(): void
    {
        unset($GLOBALS['_test_options']['wsms_phone_restriction_settings']);
        $this->settings = new RestrictionSettings();
        $resolver       = new CountryResolver();
        $this->guard    = new SendingPolicyGuard($resolver, $this->settings);
    }

    // --- Disabled restrictions ---

    public function test_allows_all_when_auth_restriction_disabled(): void
    {
        $result = $this->guard->check('+447911123456', 'auth');

        $this->assertTrue($result->allowed);
    }

    public function test_allows_all_when_messaging_restriction_disabled(): void
    {
        $result = $this->guard->check('+447911123456', 'messaging');

        $this->assertTrue($result->allowed);
    }

    // --- Auth allowlist ---

    public function test_auth_allows_country_in_allowlist(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $result = $this->guard->check('+12025551234', 'auth');

        $this->assertTrue($result->allowed);
        $this->assertSame('US', $result->country);
    }

    public function test_auth_blocks_country_not_in_allowlist(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $result = $this->guard->check('+447911123456', 'auth');

        $this->assertFalse($result->allowed);
        $this->assertSame('country_blocked', $result->reason);
        $this->assertSame('GB', $result->country);
    }

    public function test_auth_blocks_all_when_enabled_with_empty_allowlist(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => []],
        ]);

        $result = $this->guard->check('+12025551234', 'auth');

        $this->assertFalse($result->allowed);
        $this->assertSame('country_blocked', $result->reason);
    }

    // --- Messaging allowlist ---

    public function test_messaging_allows_country_in_allowlist(): void
    {
        $this->settings->save([
            'messaging' => ['enabled' => true, 'allowed_countries' => ['US', 'GB']],
        ]);

        $result = $this->guard->check('+447911123456', 'messaging');

        $this->assertTrue($result->allowed);
    }

    public function test_messaging_blocks_country_not_in_allowlist(): void
    {
        $this->settings->save([
            'messaging' => ['enabled' => true, 'allowed_countries' => ['US', 'GB']],
        ]);

        $result = $this->guard->check('+4915112345678', 'messaging');

        $this->assertFalse($result->allowed);
        $this->assertSame('country_blocked', $result->reason);
        $this->assertSame('DE', $result->country);
    }

    // --- Block mode ---

    /**
     * @dataProvider blockModeProvider
     */
    public function test_block_mode(
        string $context,
        bool $enabled,
        array $list,
        string $phone,
        bool $expectedAllowed,
    ): void {
        $this->settings->save([
            $context => [
                'enabled'           => $enabled,
                'mode'              => 'block',
                'allowed_countries' => $list,
            ],
        ]);

        $result = $this->guard->check($phone, $context);

        $this->assertSame($expectedAllowed, $result->allowed);
    }

    public static function blockModeProvider(): array
    {
        return [
            // mode=block, disabled → allow all
            'auth disabled'                     => ['auth', false, ['US'], '+12025551234', true],
            'messaging disabled'                => ['messaging', false, ['US'], '+12025551234', true],

            // mode=block, empty list, enabled → allow all (nothing is blocked)
            'auth empty list allows all'        => ['auth', true, [], '+12025551234', true],
            'messaging empty list allows all'   => ['messaging', true, [], '+447911123456', true],

            // mode=block, country in list → block
            'auth blocks listed country'        => ['auth', true, ['US'], '+12025551234', false],
            'messaging blocks listed country'   => ['messaging', true, ['GB'], '+447911123456', false],

            // mode=block, country not in list → allow
            'auth allows unlisted country'      => ['auth', true, ['US'], '+447911123456', true],
            'messaging allows unlisted country' => ['messaging', true, ['GB'], '+12025551234', true],
        ];
    }

    public function test_block_mode_fails_open_for_unresolvable(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'mode' => 'block', 'allowed_countries' => ['US']],
        ]);

        $result = $this->guard->check('09121234567', 'auth');

        $this->assertTrue($result->allowed);
    }

    // --- Cross-context independent modes ---

    public function test_auth_allow_mode_and_messaging_block_mode_are_independent(): void
    {
        $this->settings->save([
            'auth'      => ['enabled' => true, 'mode' => 'allow', 'allowed_countries' => ['US']],
            'messaging' => ['enabled' => true, 'mode' => 'block', 'allowed_countries' => ['US']],
        ]);

        // US: allowed for auth (in allowlist), blocked for messaging (in blocklist)
        $this->assertTrue($this->guard->check('+12025551234', 'auth')->allowed);
        $this->assertFalse($this->guard->check('+12025551234', 'messaging')->allowed);

        // GB: blocked for auth (not in allowlist), allowed for messaging (not in blocklist)
        $this->assertFalse($this->guard->check('+447911123456', 'auth')->allowed);
        $this->assertTrue($this->guard->check('+447911123456', 'messaging')->allowed);
    }

    // --- Separate contexts ---

    public function test_auth_and_messaging_use_separate_allowlists(): void
    {
        $this->settings->save([
            'auth'      => ['enabled' => true, 'allowed_countries' => ['US']],
            'messaging' => ['enabled' => true, 'allowed_countries' => ['GB']],
        ]);

        // US number: allowed for auth, blocked for messaging
        $authResult = $this->guard->check('+12025551234', 'auth');
        $msgResult  = $this->guard->check('+12025551234', 'messaging');

        $this->assertTrue($authResult->allowed);
        $this->assertFalse($msgResult->allowed);

        // GB number: blocked for auth, allowed for messaging
        $authResult2 = $this->guard->check('+447911123456', 'auth');
        $msgResult2  = $this->guard->check('+447911123456', 'messaging');

        $this->assertFalse($authResult2->allowed);
        $this->assertTrue($msgResult2->allowed);
    }

    // --- Fail open for unresolvable numbers ---

    public function test_allows_non_e164_number_fail_open(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $result = $this->guard->check('09121234567', 'auth');

        $this->assertTrue($result->allowed);
    }

    public function test_allows_empty_string_fail_open(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $result = $this->guard->check('', 'auth');

        $this->assertTrue($result->allowed);
    }

    // --- Convenience methods ---

    public function test_is_allowed_for_auth_convenience(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $this->assertTrue($this->guard->isAllowedForAuth('+12025551234')->allowed);
        $this->assertFalse($this->guard->isAllowedForAuth('+447911123456')->allowed);
    }

    public function test_is_allowed_for_messaging_convenience(): void
    {
        $this->settings->save([
            'messaging' => ['enabled' => true, 'allowed_countries' => ['GB']],
        ]);

        $this->assertTrue($this->guard->isAllowedForMessaging('+447911123456')->allowed);
        $this->assertFalse($this->guard->isAllowedForMessaging('+12025551234')->allowed);
    }
}
