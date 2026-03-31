<?php

namespace WSms\Tests\Unit\Service\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\SettingsRepository;
use WSms\Service\Admin\AdminBarManager;

class AdminBarManagerTest extends TestCase
{
    private AdminBarManager $manager;
    private MockObject&SettingsRepository $settingsRepo;
    private FakeAdminBar $adminBar;

    protected function setUp(): void
    {
        $this->settingsRepo = $this->createMock(SettingsRepository::class);
        $this->manager = new AdminBarManager($this->settingsRepo);
        $this->adminBar = new FakeAdminBar();
        $GLOBALS['_test_current_user_id'] = 1;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_current_user_id']);
    }

    public function testAddsNodeWithCorrectIdAndParent(): void
    {
        $this->settingsRepo->method('get')
            ->with('auth_base_url', '/account')
            ->willReturn('/account');

        $this->manager->addAccountNode($this->adminBar);

        $node = $this->adminBar->nodes['wsms-account'] ?? null;
        $this->assertNotNull($node);
        $this->assertSame('user-actions', $node['parent']);
        $this->assertSame('My Account', $node['title']);
        $this->assertSame('http://localhost/account', $node['href']);
    }

    public function testSkipsNodeWhenLoggedOut(): void
    {
        $GLOBALS['_test_current_user_id'] = 0;

        $this->manager->addAccountNode($this->adminBar);

        $this->assertEmpty($this->adminBar->nodes);
    }

    public function testRespectsCustomBaseUrl(): void
    {
        $this->settingsRepo->method('get')
            ->with('auth_base_url', '/account')
            ->willReturn('/my-portal');

        $this->manager->addAccountNode($this->adminBar);

        $node = $this->adminBar->nodes['wsms-account'];
        $this->assertSame('http://localhost/my-portal', $node['href']);
    }

    public function testNormalizesTrailingSlash(): void
    {
        $this->settingsRepo->method('get')
            ->with('auth_base_url', '/account')
            ->willReturn('/account/');

        $this->manager->addAccountNode($this->adminBar);

        $node = $this->adminBar->nodes['wsms-account'];
        $this->assertSame('http://localhost/account', $node['href']);
    }

    public function testAccountNodeAppearsAboveLogout(): void
    {
        // Simulate WordPress having already added the logout node.
        $this->adminBar->add_node([
            'id'     => 'logout',
            'parent' => 'user-actions',
            'title'  => 'Log Out',
            'href'   => '/wp-login.php?action=logout',
        ]);

        $this->settingsRepo->method('get')
            ->with('auth_base_url', '/account')
            ->willReturn('/account');

        $this->manager->addAccountNode($this->adminBar);

        $keys = array_keys($this->adminBar->nodes);
        $accountPos = array_search('wsms-account', $keys, true);
        $logoutPos = array_search('logout', $keys, true);

        $this->assertLessThan($logoutPos, $accountPos);
    }
}

/**
 * Minimal WP_Admin_Bar stand-in that captures add_node calls.
 */
class FakeAdminBar
{
    /** @var array<string, array> */
    public array $nodes = [];

    public function add_node(array $args): void
    {
        $this->nodes[$args['id']] = $args;
    }

    public function get_node(string $id): ?object
    {
        return isset($this->nodes[$id]) ? (object) $this->nodes[$id] : null;
    }

    public function remove_node(string $id): void
    {
        unset($this->nodes[$id]);
    }
}
