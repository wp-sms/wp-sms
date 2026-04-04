<?php

namespace WSms\Tests\Unit\Access;

use PHPUnit\Framework\TestCase;
use WSms\Access\AccessManager;

class AccessManagerTest extends TestCase
{
    private AccessManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AccessManager();
        $GLOBALS['_test_current_user_caps'] = [];
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_roles'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_current_user_caps'],
            $GLOBALS['_test_current_user_can'],
            $GLOBALS['_test_options'],
            $GLOBALS['_test_roles'],
        );
    }

    private function grantCaps(array $caps): void
    {
        foreach ($caps as $cap) {
            $GLOBALS['_test_current_user_caps'][$cap] = true;
        }
    }

    private function makeRole(string $name, array $caps = []): \WP_Role
    {
        $capMap = [];
        foreach ($caps as $cap) {
            $capMap[$cap] = true;
        }
        $role = new \WP_Role($name, $capMap);
        $GLOBALS['_test_roles'][$name] = $role;
        return $role;
    }

    // --- canViewSection() ---

    public function testCanViewSectionAdminBypass(): void
    {
        $this->grantCaps(['manage_options']);

        $this->assertTrue($this->manager->canViewSection('dashboard'));
        $this->assertTrue($this->manager->canViewSection('audience'));
        $this->assertTrue($this->manager->canViewSection('settings'));
    }

    public function testCanViewSectionWithViewCap(): void
    {
        $this->grantCaps(['wsms_view_audience']);

        $this->assertTrue($this->manager->canViewSection('audience'));
        $this->assertFalse($this->manager->canViewSection('campaigns'));
    }

    public function testManageImpliesView(): void
    {
        $this->grantCaps(['wsms_manage_audience']);

        $this->assertTrue($this->manager->canViewSection('audience'));
    }

    public function testViewDoesNotImplyManage(): void
    {
        $this->grantCaps(['wsms_view_audience']);

        $this->assertFalse($this->manager->canManageSection('audience'));
    }

    public function testSettingsRequiresManageCap(): void
    {
        // Settings has no view cap — only manage
        $this->assertFalse($this->manager->canViewSection('settings'));

        $this->grantCaps(['wsms_manage_settings']);
        $this->assertTrue($this->manager->canViewSection('settings'));
    }

    public function testDashboardHasNoManageCap(): void
    {
        $this->grantCaps(['wsms_view_dashboard']);

        $this->assertFalse($this->manager->canManageSection('dashboard'));
    }

    public function testUnknownSectionReturnsFalseForNonAdmin(): void
    {
        $this->grantCaps(['wsms_view_dashboard']);
        $this->assertFalse($this->manager->canViewSection('nonexistent'));
        $this->assertFalse($this->manager->canManageSection('nonexistent'));
    }

    public function testUnknownSectionReturnsTrueForAdmin(): void
    {
        $this->grantCaps(['manage_options']);
        // Admin bypass returns true before checking section map
        $this->assertTrue($this->manager->canViewSection('nonexistent'));
        $this->assertTrue($this->manager->canManageSection('nonexistent'));
    }

    // --- hasAnyAccess() ---

    public function testHasAnyAccessForAdmin(): void
    {
        $this->grantCaps(['manage_options']);
        $this->assertTrue($this->manager->hasAnyAccess());
    }

    public function testHasAnyAccessWithSingleCap(): void
    {
        $this->grantCaps(['wsms_view_monitoring']);
        $this->assertTrue($this->manager->hasAnyAccess());
    }

    public function testHasAnyAccessWithNoCaps(): void
    {
        $this->assertFalse($this->manager->hasAnyAccess());
    }

    // --- getCurrentUserCaps() ---

    public function testGetCurrentUserCapsForAdmin(): void
    {
        $this->grantCaps(['manage_options']);

        $caps = $this->manager->getCurrentUserCaps();

        $this->assertTrue($caps['is_admin']);
        foreach (AccessManager::ALL_CAPS as $cap) {
            $this->assertTrue($caps[$cap], "Admin should have {$cap}");
        }
    }

    public function testGetCurrentUserCapsForViewer(): void
    {
        $this->grantCaps(['wsms_view_dashboard', 'wsms_view_audience']);

        $caps = $this->manager->getCurrentUserCaps();

        $this->assertFalse($caps['is_admin']);
        $this->assertTrue($caps['wsms_view_dashboard']);
        $this->assertTrue($caps['wsms_view_audience']);
        $this->assertFalse($caps['wsms_manage_audience']);
        $this->assertFalse($caps['wsms_manage_settings']);
    }

    // --- Profile matching ---

    public function testGetProfileForRoleMatchesViewer(): void
    {
        $this->makeRole('editor', AccessManager::PROFILES['viewer']);

        $this->assertSame('viewer', $this->manager->getProfileForRole('editor'));
    }

    public function testGetProfileForRoleMatchesOperator(): void
    {
        $this->makeRole('editor', AccessManager::PROFILES['operator']);

        $this->assertSame('operator', $this->manager->getProfileForRole('editor'));
    }

    public function testGetProfileForRoleMatchesManager(): void
    {
        $this->makeRole('shop_manager', AccessManager::PROFILES['manager']);

        $this->assertSame('manager', $this->manager->getProfileForRole('shop_manager'));
    }

    public function testGetProfileForRoleMatchesAdmin(): void
    {
        $this->makeRole('editor', AccessManager::ALL_CAPS);

        $this->assertSame('admin', $this->manager->getProfileForRole('editor'));
    }

    public function testGetProfileForRoleMatchesNoAccess(): void
    {
        $this->makeRole('subscriber', []);

        $this->assertSame('no_access', $this->manager->getProfileForRole('subscriber'));
    }

    public function testGetProfileForRoleReturnsCustomForMismatch(): void
    {
        // A role with one cap that doesn't match any profile exactly
        $this->makeRole('editor', ['wsms_view_dashboard', 'wsms_manage_settings']);

        $this->assertSame('custom', $this->manager->getProfileForRole('editor'));
    }

    public function testGetProfileForRoleReturnsNoAccessForMissingRole(): void
    {
        $this->assertSame('no_access', $this->manager->getProfileForRole('nonexistent'));
    }

    // --- applyProfile() ---

    public function testApplyProfileSetsCapsOnRole(): void
    {
        $role = $this->makeRole('editor', ['wsms_view_dashboard']);

        $this->manager->applyProfile('editor', 'operator');

        $expectedCaps = AccessManager::PROFILES['operator'];
        foreach ($expectedCaps as $cap) {
            $this->assertTrue($role->has_cap($cap), "Role should have {$cap}");
        }

        // Old caps not in profile should be removed
        $this->assertFalse($role->has_cap('wsms_manage_settings'));
    }

    public function testApplyProfileRemovesOldCaps(): void
    {
        $role = $this->makeRole('editor', AccessManager::ALL_CAPS);

        $this->manager->applyProfile('editor', 'viewer');

        $viewerCaps = AccessManager::PROFILES['viewer'];
        foreach (AccessManager::ALL_CAPS as $cap) {
            if (in_array($cap, $viewerCaps, true)) {
                $this->assertTrue($role->has_cap($cap));
            } else {
                $this->assertFalse($role->has_cap($cap), "Viewer should not have {$cap}");
            }
        }
    }

    public function testApplyNoAccessRemovesAllCaps(): void
    {
        $role = $this->makeRole('editor', AccessManager::ALL_CAPS);

        $this->manager->applyProfile('editor', 'no_access');

        foreach (AccessManager::ALL_CAPS as $cap) {
            $this->assertFalse($role->has_cap($cap));
        }
    }

    public function testApplyProfileIgnoresNonexistentRole(): void
    {
        // Should not error
        $this->manager->applyProfile('nonexistent', 'viewer');
        $this->assertTrue(true);
    }

    // --- Profile definitions sanity checks ---

    public function testAllProfileCapsAreValid(): void
    {
        foreach (AccessManager::PROFILES as $id => $caps) {
            foreach ($caps as $cap) {
                $this->assertContains($cap, AccessManager::ALL_CAPS, "Profile '{$id}' has invalid cap: {$cap}");
            }
        }
    }

    public function testAdminProfileContainsAllCaps(): void
    {
        $adminCaps = AccessManager::PROFILES['admin'];
        sort($adminCaps);
        $allCaps = AccessManager::ALL_CAPS;
        sort($allCaps);

        $this->assertSame($allCaps, $adminCaps);
    }

    public function testNoAccessProfileIsEmpty(): void
    {
        $this->assertEmpty(AccessManager::PROFILES['no_access']);
    }

    /**
     * @dataProvider profileHierarchyProvider
     */
    public function testProfileHierarchyIsSubset(string $lower, string $higher): void
    {
        $lowerCaps = AccessManager::PROFILES[$lower];
        $higherCaps = AccessManager::PROFILES[$higher];

        foreach ($lowerCaps as $cap) {
            $this->assertContains(
                $cap,
                $higherCaps,
                "Profile '{$higher}' should include all caps from '{$lower}', missing: {$cap}"
            );
        }
    }

    public static function profileHierarchyProvider(): array
    {
        return [
            'viewer ⊂ operator'  => ['viewer', 'operator'],
            'operator ⊂ manager' => ['operator', 'manager'],
            'manager ⊂ admin'    => ['manager', 'admin'],
        ];
    }

    // --- Section cap map sanity ---

    public function testAllSectionCapsExistInAllCaps(): void
    {
        foreach (AccessManager::SECTION_CAPS as $section => $caps) {
            if ($caps['view'] !== null) {
                $this->assertContains($caps['view'], AccessManager::ALL_CAPS, "Section {$section} view cap not in ALL_CAPS");
            }
            if ($caps['manage'] !== null) {
                $this->assertContains($caps['manage'], AccessManager::ALL_CAPS, "Section {$section} manage cap not in ALL_CAPS");
            }
        }
    }

    public function testSectionCapCountMatches(): void
    {
        // Count all unique non-null caps in SECTION_CAPS — should match ALL_CAPS count
        $sectionCaps = [];
        foreach (AccessManager::SECTION_CAPS as $caps) {
            if ($caps['view'] !== null) $sectionCaps[] = $caps['view'];
            if ($caps['manage'] !== null) $sectionCaps[] = $caps['manage'];
        }
        $sectionCaps = array_unique($sectionCaps);
        sort($sectionCaps);

        $allCaps = AccessManager::ALL_CAPS;
        sort($allCaps);

        $this->assertSame($allCaps, $sectionCaps);
    }
}
