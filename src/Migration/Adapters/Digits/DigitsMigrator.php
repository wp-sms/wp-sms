<?php

namespace WSms\Migration\Adapters\Digits;

use WSms\Migration\BatchResult;
use WSms\Migration\Contracts\MigrationStepInterface;
use WSms\Migration\Contracts\PluginMigratorInterface;
use WSms\Migration\DetectionResult;
use WSms\Migration\MigrationBackupService;
use WSms\Migration\PreviewResult;
use WSms\Migration\RollbackResult;
use WSms\Migration\Adapters\Digits\Steps\UserPhoneStep;
use WSms\Migration\Adapters\Digits\Steps\TotpEnrollmentStep;
use WSms\Migration\Adapters\Digits\Steps\SettingsStep;

defined('ABSPATH') || exit;

class DigitsMigrator implements PluginMigratorInterface
{
    /** @var array<string, MigrationStepInterface&object{preview: callable, migrateBatch: callable}> */
    private array $steps;

    public function __construct(
        private readonly DigitsDetector $detector,
        private readonly MigrationBackupService $backupService,
        UserPhoneStep $phoneStep,
        TotpEnrollmentStep $totpStep,
        SettingsStep $settingsStep,
    ) {
        $this->steps = [
            $phoneStep->getId() => $phoneStep,
            $totpStep->getId()  => $totpStep,
            $settingsStep->getId() => $settingsStep,
        ];
    }

    public function getId(): string
    {
        return 'digits';
    }

    public function getName(): string
    {
        return 'Digits';
    }

    public function detect(): DetectionResult
    {
        return $this->detector->detect();
    }

    /** @return MigrationStepInterface[] */
    public function getSteps(): array
    {
        return array_values($this->steps);
    }

    public function preview(string $stepId, array $options = []): PreviewResult
    {
        $step = $this->steps[$stepId] ?? null;
        if ($step === null) {
            return new PreviewResult(0, 0, 0, 0, 0);
        }

        return $step->preview($options);
    }

    public function migrateBatch(string $stepId, array $options, ?string $cursor = null): BatchResult
    {
        $step = $this->steps[$stepId] ?? null;
        if ($step === null) {
            return new BatchResult(0, 0, 0, 0, 0, null, true);
        }

        return $step->migrateBatch($options, $cursor);
    }

    public function rollback(string $stepId): RollbackResult
    {
        return match ($stepId) {
            'user_phones'     => $this->backupService->rollbackPhones(),
            'totp_enrollment' => $this->backupService->rollbackTotp(),
            'settings'        => $this->backupService->rollbackSettings(),
            default           => new RollbackResult(0, 0),
        };
    }
}
