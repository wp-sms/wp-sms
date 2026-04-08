<?php

namespace WSms\Tests\Unit\Onboarding;

use PHPUnit\Framework\TestCase;
use WSms\Integration\IntegrationRegistry;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Migration\MigrationManager;
use WSms\Migration\MigrationStateManager;
use WSms\Onboarding\OnboardingManager;
use WSms\Service\Dashboard\DashboardService;

/**
 * Coverage for the status validation + terminal-state guard added to
 * OnboardingManager::updateState. The motivation:
 *
 * 1. updateState() previously accepted any string for `status`. With the
 *    REST endpoint forwarding raw JSON params, a malformed client (or stale
 *    page) could write garbage values into the option.
 *
 * 2. The wizard's mount effect PUTs `{status: 'in_progress'}` whenever its
 *    PHP-bootstrapped snapshot says 'pending'. In a multi-tab session where
 *    tab A has already finished the wizard ('completed'), tab B's stale
 *    snapshot would PUT 'in_progress' on its next mount and silently
 *    downgrade the terminal state. Terminal states should only be reset
 *    via the explicit resetWizard() path.
 */
class OnboardingManagerTest extends TestCase
{
    private OnboardingManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_current_user_can'] = true;

        $this->manager = new OnboardingManager(
            $this->createMock(DashboardService::class),
            $this->createMock(GatewayRegistry::class),
            $this->createMock(IntegrationRegistry::class),
            $this->createMock(MigrationManager::class),
            $this->createMock(MigrationStateManager::class),
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_current_user_can'],
        );
    }

    private function seed(array $overrides): void
    {
        $GLOBALS['_test_options'][OnboardingManager::OPTION_KEY] = array_merge(
            OnboardingManager::DEFAULTS,
            $overrides,
        );
    }

    private function stored(): array
    {
        return $GLOBALS['_test_options'][OnboardingManager::OPTION_KEY];
    }

    public function testUpdateStateRejectsInvalidStatusValues(): void
    {
        $this->seed(['status' => 'in_progress', 'current_step' => 1]);

        $this->manager->updateState(['status' => 'bogus']);

        $this->assertSame('in_progress', $this->stored()['status']);
        $this->assertSame(1, $this->stored()['current_step']);
    }

    public function testUpdateStateRejectsCompletedToInProgressDowngrade(): void
    {
        $this->seed([
            'status'       => 'completed',
            'completed_at' => '2026-04-08T12:00:00+00:00',
        ]);

        $this->manager->updateState(['status' => 'in_progress']);

        $this->assertSame('completed', $this->stored()['status']);
        $this->assertSame(
            '2026-04-08T12:00:00+00:00',
            $this->stored()['completed_at'],
            'completed_at must not be cleared by a rejected downgrade',
        );
    }

    public function testUpdateStateRejectsSkippedToInProgressDowngrade(): void
    {
        $this->seed(['status' => 'skipped']);

        $this->manager->updateState(['status' => 'in_progress']);

        $this->assertSame('skipped', $this->stored()['status']);
    }

    public function testUpdateStateAllowsPendingToInProgressTransition(): void
    {
        $this->seed(['status' => 'pending']);

        $this->manager->updateState(['status' => 'in_progress']);

        $this->assertSame('in_progress', $this->stored()['status']);
    }

    public function testUpdateStateAllowsInProgressToCompletedAndStampsCompletedAt(): void
    {
        $this->seed(['status' => 'in_progress', 'completed_at' => null]);

        $this->manager->updateState(['status' => 'completed']);

        $this->assertSame('completed', $this->stored()['status']);
        $this->assertNotNull(
            $this->stored()['completed_at'],
            'completed_at must be set when status moves to completed',
        );
    }

    public function testUpdateStateUpdatesOtherFieldsEvenWhenStatusIsRejected(): void
    {
        $this->seed(['status' => 'completed', 'checklist_dismissed' => false]);

        // Stale tab tries to PUT both a status downgrade AND a legitimate
        // field update. The status should be silently dropped while the
        // other field still applies.
        $this->manager->updateState([
            'status'              => 'in_progress',
            'checklist_dismissed' => true,
        ]);

        $this->assertSame('completed', $this->stored()['status']);
        $this->assertTrue($this->stored()['checklist_dismissed']);
    }

    public function testResetWizardStillWorksFromTerminalState(): void
    {
        // Sanity: the terminal-state guard lives in updateState() only — the
        // direct-write reset path must remain free to clear the terminal flag.
        $this->seed([
            'status'       => 'completed',
            'current_step' => 3,
            'completed_at' => '2026-04-08T12:00:00+00:00',
        ]);

        $this->manager->resetWizard();

        $this->assertSame('pending', $this->stored()['status']);
        $this->assertSame(0, $this->stored()['current_step']);
        $this->assertNull($this->stored()['completed_at']);
    }
}
