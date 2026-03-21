<?php

namespace Tests\Unit\PhoneRestriction;

use PHPUnit\Framework\TestCase;
use WSms\PhoneRestriction\RestrictionSettings;

class RestrictionSettingsTest extends TestCase
{
    private RestrictionSettings $settings;

    protected function setUp(): void
    {
        unset($GLOBALS['_test_options']['wsms_phone_restriction_settings']);
        $this->settings = new RestrictionSettings();
    }

    public function test_defaults_when_no_option_saved(): void
    {
        $all = $this->settings->all();

        $this->assertFalse($all['auth']['enabled']);
        $this->assertSame('allow', $all['auth']['mode']);
        $this->assertSame([], $all['auth']['allowed_countries']);
        $this->assertFalse($all['messaging']['enabled']);
        $this->assertSame('allow', $all['messaging']['mode']);
        $this->assertSame([], $all['messaging']['allowed_countries']);
        $this->assertFalse($all['number_type_blocking']['enabled']);
        $this->assertSame(['premium_rate', 'toll_free', 'shared_cost'], $all['number_type_blocking']['blocked_types']);
        $this->assertTrue($all['enhanced_db']['auto_update']);
    }

    public function test_convenience_methods_return_defaults(): void
    {
        $this->assertFalse($this->settings->isRestrictionEnabled('auth'));
        $this->assertFalse($this->settings->isRestrictionEnabled('messaging'));
        $this->assertFalse($this->settings->isNumberTypeBlockingEnabled());
        $this->assertTrue($this->settings->isAutoUpdateEnabled());
        $this->assertSame('allow', $this->settings->getMode('auth'));
        $this->assertSame('allow', $this->settings->getMode('messaging'));
        $this->assertSame([], $this->settings->getAllowedCountries('auth'));
        $this->assertSame([], $this->settings->getAllowedCountries('messaging'));
        $this->assertSame(['premium_rate', 'toll_free', 'shared_cost'], $this->settings->getBlockedNumberTypes());
    }

    public function test_save_and_load_round_trip(): void
    {
        $this->settings->save([
            'auth' => [
                'enabled'           => true,
                'allowed_countries' => ['US', 'CA'],
            ],
        ]);

        $all = $this->settings->all();

        $this->assertTrue($all['auth']['enabled']);
        $this->assertSame(['US', 'CA'], $all['auth']['allowed_countries']);
        // Other sections should remain at defaults
        $this->assertFalse($all['messaging']['enabled']);
    }

    public function test_partial_update_merges_with_existing(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $this->settings->save([
            'messaging' => ['enabled' => true, 'allowed_countries' => ['GB']],
        ]);

        $all = $this->settings->all();

        $this->assertTrue($all['auth']['enabled']);
        $this->assertSame(['US'], $all['auth']['allowed_countries']);
        $this->assertTrue($all['messaging']['enabled']);
        $this->assertSame(['GB'], $all['messaging']['allowed_countries']);
    }

    public function test_static_defaults_method(): void
    {
        $defaults = RestrictionSettings::defaults();

        $this->assertArrayHasKey('auth', $defaults);
        $this->assertArrayHasKey('messaging', $defaults);
        $this->assertArrayHasKey('number_type_blocking', $defaults);
        $this->assertArrayHasKey('enhanced_db', $defaults);
    }

    public function test_get_mode_defaults_to_allow(): void
    {
        $this->assertSame('allow', $this->settings->getMode('auth'));
        $this->assertSame('allow', $this->settings->getMode('messaging'));
    }

    public function test_get_mode_returns_saved_value(): void
    {
        $this->settings->save([
            'auth'      => ['mode' => 'block'],
            'messaging' => ['mode' => 'allow'],
        ]);

        $this->assertSame('block', $this->settings->getMode('auth'));
        $this->assertSame('allow', $this->settings->getMode('messaging'));
    }

    public function test_mode_defaults_to_allow_for_existing_installations(): void
    {
        // Simulate existing installation that saved settings without mode
        $GLOBALS['_test_options']['wsms_phone_restriction_settings'] = [
            'auth' => [
                'enabled'           => true,
                'allowed_countries' => ['US'],
            ],
        ];

        $settings = new RestrictionSettings();

        $this->assertSame('allow', $settings->getMode('auth'));
        $this->assertTrue($settings->isRestrictionEnabled('auth'));
        $this->assertSame(['US'], $settings->getAllowedCountries('auth'));
    }
}
