<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\AuthRouter;

class AuthRouterTest extends TestCase
{
    private AuthRouter $router;

    protected function setUp(): void
    {
        $this->router = new AuthRouter();

        unset(
            $GLOBALS['_test_query_vars'],
            $GLOBALS['_test_redirect'],
        );

        $GLOBALS['_test_query_vars'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_query_vars'],
            $GLOBALS['_test_redirect'],
        );
    }

    public function testRegisterQueryVarsAddsAuthVars(): void
    {
        $vars = $this->router->registerQueryVars(['existing_var']);

        $this->assertContains('wsms_auth_page', $vars);
        $this->assertContains('wsms_auth_route', $vars);
        $this->assertContains('existing_var', $vars);
    }

    public function testLoadTemplateReturnsOriginalWhenNotAuthPage(): void
    {
        $GLOBALS['_test_query_vars']['wsms_auth_page'] = '';

        $result = $this->router->loadTemplate('/path/to/theme/page.php');

        $this->assertSame('/path/to/theme/page.php', $result);
    }

    public function testLoadTemplateOverridesWhenAuthPageSet(): void
    {
        $GLOBALS['_test_query_vars']['wsms_auth_page'] = '1';

        $result = $this->router->loadTemplate('/path/to/theme/page.php');

        $this->assertStringContainsString('views/auth/app.php', $result);
    }

    public function testEnqueueAssetsSkipsWhenNotAuthPage(): void
    {
        $GLOBALS['_test_query_vars']['wsms_auth_page'] = '';

        // Should not throw — just returns early.
        $this->router->enqueueAssets();
        $this->addToAssertionCount(1);
    }

    public function testMaybeRedirectLoginSkipsWhenSettingDisabled(): void
    {
        // No redirect_login setting — should not redirect.
        $this->router->maybeRedirectLogin();

        $this->assertArrayNotHasKey('_test_redirect', $GLOBALS);
    }
}
