<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Migration\ConflictResolution;
use WSms\Migration\MigrationBatchHandler;
use WSms\Migration\MigrationManager;
use WSms\Migration\MigrationStateManager;
use WSms\Migration\MigrationStatus;
use WSms\Migration\Adapters\Digits\DigitsDetector;
use WSms\Migration\Adapters\Digits\DigitsShortcodeCompat;
use WSms\Migration\Adapters\Digits\DigitsRedirectCompat;
use WSms\Auth\SettingsRepository;

defined('ABSPATH') || exit;

class MigrationController extends Controller
{
    public function __construct(
        private readonly MigrationManager $manager,
        private readonly MigrationStateManager $stateManager,
        private readonly DigitsDetector $detector,
        private readonly DigitsShortcodeCompat $shortcodeCompat,
        private readonly DigitsRedirectCompat $redirectCompat,
        private readonly SettingsRepository $settingsRepo,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/migration/available', [
            'methods'             => 'GET',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->getAvailable($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/(?P<id>[a-z_]+)/preview', [
            'methods'             => 'GET',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->getPreview($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/(?P<id>[a-z_]+)/preflight', [
            'methods'             => 'GET',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->getPreflight($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/(?P<id>[a-z_]+)/start', [
            'methods'             => 'POST',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->startMigration($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/status', [
            'methods'             => 'GET',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->getStatus($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/pause', [
            'methods'             => 'POST',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->pause($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/resume', [
            'methods'             => 'POST',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->resume($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/rollback', [
            'methods'             => 'POST',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->rollback($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/errors', [
            'methods'             => 'GET',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->getErrors($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/(?P<id>[a-z_]+)/switchover', [
            'methods'             => 'POST',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->switchover($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/migration/transition-status', [
            'methods'             => 'GET',
            'callback'            => fn(WP_REST_Request $r) => $this->handle(fn() => $this->getTransitionStatus($r)),
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    private function getAvailable(WP_REST_Request $request): WP_REST_Response
    {
        $available = $this->manager->getAvailableMigrators();

        $data = [];
        foreach ($available as $id => $detection) {
            $data[] = array_merge(['id' => $id], $detection->toArray());
        }

        return $this->ok($data);
    }

    private function getPreview(WP_REST_Request $request): WP_REST_Response
    {
        $migratorId = $request->get_param('id');
        $migrator = $this->manager->getMigrator($migratorId);
        if ($migrator === null) {
            return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        $options = $request->get_params();
        $previews = [];
        foreach ($migrator->getSteps() as $step) {
            if (!$step->isApplicable()) {
                continue;
            }
            $previews[$step->getId()] = array_merge(
                ['label' => $step->getLabel(), 'description' => $step->getDescription()],
                $migrator->preview($step->getId(), $options)->toArray(),
            );
        }

        return $this->ok($previews);
    }

    private function getPreflight(WP_REST_Request $request): WP_REST_Response
    {
        $checks = [];

        // Background jobs working.
        $cronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $checks['cron'] = [
            'label'  => __('Background job processing', 'wp-sms'),
            'status' => !$cronDisabled ? 'pass' : 'warning',
            'detail' => $cronDisabled
                ? __('WP_CRON is disabled. Background migration will require manual batch triggers or an external cron.', 'wp-sms')
                : __('Working normally.', 'wp-sms'),
        ];

        // TOTP encryption key.
        $hasTotpData = $this->detector->countTotpUsers() > 0;
        if ($hasTotpData) {
            $hasKey = defined('WSMS_MFA_ENCRYPTION_KEY') && WSMS_MFA_ENCRYPTION_KEY !== '';
            $checks['encryption_key'] = [
                'label'  => __('TOTP encryption key', 'wp-sms'),
                'status' => $hasKey ? 'pass' : 'fail',
                'detail' => $hasKey
                    ? __('Configured.', 'wp-sms')
                    : __('Required for authenticator app migration. Add define(\'WSMS_MFA_ENCRYPTION_KEY\', \'...\') to wp-config.php.', 'wp-sms'),
            ];
        }

        // Memory limit.
        $memoryLimit = ini_get('memory_limit') ?: '256M';
        $memoryBytes = MigrationBatchHandler::parseMemoryLimit($memoryLimit);
        $checks['memory'] = [
            'label'  => __('Server memory', 'wp-sms'),
            'status' => $memoryBytes >= 128 * 1024 * 1024 ? 'pass' : 'warning',
            'detail' => sprintf(__('Memory limit: %s', 'wp-sms'), $memoryLimit),
        ];

        $allPass = !array_filter($checks, fn($c) => $c['status'] === 'fail');

        return $this->ok([
            'checks'   => $checks,
            'can_start' => $allPass,
        ]);
    }

    private function startMigration(WP_REST_Request $request): WP_REST_Response
    {
        $migratorId = $request->get_param('id');
        $conflictResolution = ConflictResolution::tryFrom($request->get_param('conflict_resolution') ?? 'skip') ?? ConflictResolution::Skip;
        $options = [
            'default_country'   => $request->get_param('default_country'),
            'wc_sync'           => (bool) $request->get_param('wc_sync'),
            'dry_run'           => (bool) $request->get_param('dry_run'),
            'selected_settings' => $request->get_param('selected_settings'),
        ];

        $this->manager->startMigration($migratorId, $conflictResolution, $options);

        return $this->ok($this->stateManager->getProgress());
    }

    private function getStatus(WP_REST_Request $request): WP_REST_Response
    {
        $progress = $this->stateManager->getProgress();
        if (empty($progress)) {
            return $this->ok(null);
        }

        // Enrich with creator info.
        if (!empty($progress['created_by'])) {
            $user = get_userdata($progress['created_by']);
            $progress['created_by_name'] = $user ? $user->display_name : __('Unknown', 'wp-sms');
        }

        return $this->ok($progress);
    }

    private function pause(WP_REST_Request $request): WP_REST_Response
    {
        $this->manager->pauseMigration();
        return $this->ok($this->stateManager->getProgress());
    }

    private function resume(WP_REST_Request $request): WP_REST_Response
    {
        $this->manager->resumeMigration();
        return $this->ok($this->stateManager->getProgress());
    }

    private function rollback(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->manager->rollback();
        return $this->ok($result->toArray());
    }

    private function getErrors(WP_REST_Request $request): WP_REST_Response
    {
        $state = $this->stateManager->getState();
        if ($state === null) {
            return $this->ok([]);
        }

        $allErrors = [];
        foreach ($state['steps'] as $stepId => $step) {
            foreach ($step['errors'] ?? [] as $error) {
                $allErrors[] = array_merge(['step' => $stepId], $error);
            }
        }

        return $this->ok($allErrors);
    }

    private function switchover(WP_REST_Request $request): WP_REST_Response
    {
        $migratorId = $request->get_param('id');

        // Verify all steps completed.
        if (!$this->stateManager->areAllStepsComplete()) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'incomplete',
                'message' => __('Not all migration steps have completed.', 'wp-sms'),
            ], 422);
        }

        // Register compatibility shims.
        $this->shortcodeCompat->activate();

        $authBaseUrl = $this->settingsRepo->get('auth_base_url', '/account');
        $this->redirectCompat->activate($authBaseUrl);

        // Deactivate Digits.
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins('developer-flavor/digit.php');
        }

        // Clear transition mode.
        delete_option('wsms_transition_mode');

        return $this->ok([
            'message'        => __('WP-SMS authentication is now active. Digits has been deactivated.', 'wp-sms'),
            'affected_pages' => $this->shortcodeCompat->getAffectedPages(),
            'redirects'      => $this->redirectCompat->getRedirects(),
        ]);
    }

    private function getTransitionStatus(WP_REST_Request $request): WP_REST_Response
    {
        $transitionMode = get_option('wsms_transition_mode');
        $detection = $this->detector->detect();

        return $this->ok([
            'transition_mode' => $transitionMode ?: null,
            'detection'       => $detection->toArray(),
            'migration'       => $this->stateManager->getProgress() ?: null,
        ]);
    }
}
