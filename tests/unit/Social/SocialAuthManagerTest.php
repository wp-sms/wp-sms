<?php

namespace WSms\Tests\Unit\Social;

use PHPUnit\Framework\TestCase;
use WSms\Social\Contracts\SocialProviderInterface;
use WSms\Social\SocialAuthManager;

class SocialAuthManagerTest extends TestCase
{
    private SocialAuthManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $this->manager = new SocialAuthManager();
    }

    public function testRegisterAndGetProvider(): void
    {
        $provider = $this->makeProvider('google');

        $this->manager->registerProvider($provider);

        $this->assertSame($provider, $this->manager->getProvider('google'));
    }

    public function testGetProviderReturnsNullForUnregistered(): void
    {
        $this->assertNull($this->manager->getProvider('nonexistent'));
    }

    public function testGetAllProviders(): void
    {
        $google = $this->makeProvider('google');
        $apple = $this->makeProvider('apple');

        $this->manager->registerProvider($google);
        $this->manager->registerProvider($apple);

        $all = $this->manager->getAllProviders();

        $this->assertCount(2, $all);
    }

    public function testGetEnabledProvidersFiltersOnSettings(): void
    {
        $google = $this->makeProvider('google');
        $apple = $this->makeProvider('apple');

        $this->manager->registerProvider($google);
        $this->manager->registerProvider($apple);

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'social' => [
                'google' => ['enabled' => true, 'client_id' => 'gid', 'client_secret' => 'gsecret'],
                'apple'  => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
            ],
        ];

        $enabled = $this->manager->getEnabledProviders();

        $this->assertCount(1, $enabled);
        $this->assertSame('google', $enabled[0]->getId());
    }

    public function testGetEnabledProvidersRequiresCredentials(): void
    {
        $google = $this->makeProvider('google');
        $this->manager->registerProvider($google);

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'social' => [
                'google' => ['enabled' => true, 'client_id' => '', 'client_secret' => ''],
            ],
        ];

        $enabled = $this->manager->getEnabledProviders();

        $this->assertCount(0, $enabled);
    }

    public function testGetEnabledProvidersReturnsEmptyWhenNoSettings(): void
    {
        $google = $this->makeProvider('google');
        $this->manager->registerProvider($google);

        $enabled = $this->manager->getEnabledProviders();

        $this->assertCount(0, $enabled);
    }

    private function makeProvider(string $id): SocialProviderInterface
    {
        $provider = $this->createMock(SocialProviderInterface::class);
        $provider->method('getId')->willReturn($id);

        return $provider;
    }
}
