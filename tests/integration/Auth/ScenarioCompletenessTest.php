<?php

namespace WSms\Tests\Integration\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Tests\Support\AuthScenarios;

/**
 * Canary tests that ensure AuthScenarios stays synchronized with actual channels.
 *
 * When a new channel is added, someone must add it to allChannelIds() and create
 * at least one scenario preset — otherwise these tests fail.
 */
class ScenarioCompletenessTest extends TestCase
{
    public function testEveryChannelHasAtLeastOneScenario(): void
    {
        $presets = AuthScenarios::allPresets();

        foreach (AuthScenarios::allChannelIds() as $channelId) {
            $found = false;

            foreach ($presets as $name => $preset) {
                if (!empty($preset[$channelId]['enabled'])) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found, "No scenario preset enables channel: {$channelId}");
        }
    }

    public function testEveryVerificationMethodHasScenario(): void
    {
        $presets = AuthScenarios::allPresets();

        foreach (AuthScenarios::verificationMethods() as $method) {
            $found = false;

            foreach ($presets as $preset) {
                foreach (['email', 'phone'] as $channel) {
                    $methods = $preset[$channel]['verification_methods'] ?? [];

                    if (in_array($method, $methods, true)) {
                        $found = true;
                        break 2;
                    }
                }
            }

            $this->assertTrue($found, "No scenario preset uses verification method: {$method}");
        }
    }

    public function testAllPresetsReturnValidSettings(): void
    {
        $presets = AuthScenarios::allPresets();

        $this->assertNotEmpty($presets, 'allPresets() returned empty array');

        foreach ($presets as $name => $preset) {
            $this->assertIsArray($preset, "Preset '{$name}' is not an array");
            // Every preset should have at least one channel key.
            $channelKeys = array_intersect(array_keys($preset), ['password', 'email', 'phone', 'backup_codes']);
            $this->assertNotEmpty($channelKeys, "Preset '{$name}' has no channel configuration");
        }
    }

    public function testWithOverridesDoesDeepMerge(): void
    {
        $base = AuthScenarios::passwordOnly();
        $override = AuthScenarios::withOverrides($base, [
            'phone' => ['enabled' => true, 'usage' => 'login'],
        ]);

        $this->assertTrue($override['phone']['enabled']);
        $this->assertSame('login', $override['phone']['usage']);
        // Password from base should remain.
        $this->assertTrue($override['password']['enabled']);
    }

    public function testAllChannelIdsAreStrings(): void
    {
        foreach (AuthScenarios::allChannelIds() as $id) {
            $this->assertIsString($id);
            $this->assertNotEmpty($id);
        }
    }
}
