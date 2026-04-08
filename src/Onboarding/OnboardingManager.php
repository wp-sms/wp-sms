<?php

namespace WSms\Onboarding;

use WSms\Branding\BrandingRepository;
use WSms\Integration\IntegrationRegistry;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Migration\MigrationManager;
use WSms\Migration\MigrationStatus;
use WSms\Migration\MigrationStateManager;
use WSms\Service\Dashboard\DashboardService;

defined('ABSPATH') || exit;

class OnboardingManager
{
    public const OPTION_KEY = 'wsms_onboarding';

    public const VALID_STATUSES = ['pending', 'in_progress', 'completed', 'skipped'];

    public const TERMINAL_STATUSES = ['completed', 'skipped'];

    public const DEFAULTS = [
        'status'             => 'pending',
        'goals'              => [],
        'current_step'       => 0,
        'skipped_steps'      => [],
        'checklist_dismissed' => false,
        'migration_deferred' => false,
        'completed_at'       => null,
        'version'            => '8.0',
    ];

    /** Only show 3rd-party integrations in onboarding (not internal WSMS ones). */
    private const ONBOARDING_CATEGORIES = ['ecommerce', 'forms', 'email_marketing'];

    /** Goal IDs mapped to relevant integration categories. */
    private const GOAL_INTEGRATION_MAP = [
        'woocommerce'   => ['notifications', 'campaigns'],
        'contactform7'  => ['notifications'],
        'gravityforms'  => ['notifications'],
    ];

    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly IntegrationRegistry $integrationRegistry,
        private readonly MigrationManager $migrationManager,
        private readonly MigrationStateManager $migrationStateManager,
    ) {
    }

    /**
     * Get full onboarding state including computed checklist.
     */
    public function getState(): array
    {
        $state = $this->getRawState();
        $checklist = $this->computeChecklist($state);

        return [
            'state'     => $state,
            'checklist' => $checklist,
        ];
    }

    /**
     * Get raw onboarding option with defaults merged.
     */
    public function getRawState(): array
    {
        $raw = get_option(self::OPTION_KEY, null);

        if (!is_array($raw)) {
            return self::DEFAULTS;
        }

        return array_merge(self::DEFAULTS, $raw);
    }

    /**
     * Update onboarding state fields.
     *
     * Status writes are gated: invalid enum values are dropped, and any write
     * to a terminal state is dropped (multi-tab safety — a stale snapshot
     * from a tab that hasn't seen the completion must not downgrade it).
     * Other fields in the same payload still apply. Terminal states are only
     * resettable via {@see resetWizard()}.
     */
    public function updateState(array $data): void
    {
        $current = $this->getRawState();

        if (isset($data['status'])) {
            $isValid = in_array($data['status'], self::VALID_STATUSES, true);
            $isLocked = in_array($current['status'], self::TERMINAL_STATUSES, true);
            if (!$isValid || $isLocked) {
                unset($data['status']);
            }
        }

        $allowedKeys = ['status', 'goals', 'current_step', 'skipped_steps', 'checklist_dismissed', 'migration_deferred'];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $current[$key] = $data[$key];
            }
        }

        if (isset($data['status']) && $data['status'] === 'completed' && $current['completed_at'] === null) {
            $current['completed_at'] = gmdate('c');
        }

        update_option(self::OPTION_KEY, $current, false);
    }

    public function isComplete(): bool
    {
        return $this->getRawState()['status'] === 'completed';
    }

    public function isSkipped(): bool
    {
        return $this->getRawState()['status'] === 'skipped';
    }

    public function isPending(): bool
    {
        return in_array($this->getRawState()['status'], ['pending', 'in_progress'], true);
    }

    /**
     * Whether the frontend should auto-redirect to the wizard.
     */
    public function shouldAutoRedirect(): bool
    {
        return $this->getRawState()['status'] === 'pending';
    }

    /**
     * Reset wizard for re-run.
     */
    public function resetWizard(): void
    {
        $current = $this->getRawState();
        $current['status'] = 'pending';
        $current['current_step'] = 0;
        $current['skipped_steps'] = [];
        $current['completed_at'] = null;

        update_option(self::OPTION_KEY, $current, false);
    }

    /**
     * Get detected integrations mapped to goal associations.
     */
    public function getDetectedIntegrations(): array
    {
        $available = $this->integrationRegistry->getAvailable();
        $result = [];

        foreach ($available as $integration) {
            // Only include 3rd-party integrations, not internal WSMS ones.
            if (!in_array($integration->getCategory(), self::ONBOARDING_CATEGORIES, true)) {
                continue;
            }

            $id = $integration->getId();
            $goals = self::GOAL_INTEGRATION_MAP[$id] ?? ['notifications'];

            $result[] = [
                'id'    => $id,
                'name'  => $integration->getName(),
                'icon'  => $integration->getIcon(),
                'goals' => $goals,
            ];
        }

        return $result;
    }

    /**
     * Get detected migration sources.
     */
    public function getDetectedMigrations(): array
    {
        $available = $this->migrationManager->getAvailableMigrators();
        $migrationState = $this->migrationStateManager->getState();
        $result = [];

        foreach ($available as $id => $detection) {
            $result[] = [
                'id'              => $id,
                'name'            => $detection->pluginName,
                'active'          => $detection->active,
                'version'         => $detection->version,
                'summary'         => $detection->summary,
                'migrationStatus' => $migrationState['status'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Compute checklist items from live data.
     */
    private function computeChecklist(array $state): array
    {
        $goals = $state['goals'] ?? [];
        $hasGoals = !empty($goals);

        try {
            $summary = $this->dashboardService->getSummary();
            $featureState = $summary['feature_state'];
        } catch (\Throwable) {
            $featureState = [
                'auth_enabled'        => false,
                'gateways_configured' => false,
                'has_contacts'        => false,
                'has_campaigns'       => false,
            ];
        }

        $items = [];

        // Always shown: Connect a gateway
        $items[] = [
            'id'        => 'connect_gateway',
            'label'     => __('Connect a messaging gateway', 'wp-sms'),
            'route'     => 'channels/gateways',
            'completed' => $featureState['gateways_configured'],
            'goals'     => [],
        ];

        // Auth goals
        if (!$hasGoals || in_array('auth', $goals, true)) {
            if ($featureState['auth_enabled'] || in_array('auth', $goals, true)) {
                $items[] = [
                    'id'        => 'review_auth',
                    'label'     => __('Review your auth channels', 'wp-sms'),
                    'route'     => 'identity',
                    'completed' => $featureState['auth_enabled'],
                    'goals'     => ['auth'],
                ];

                $items[] = [
                    'id'        => 'customize_branding',
                    'label'     => __('Customize your branding', 'wp-sms'),
                    'route'     => 'settings/branding',
                    'completed' => $this->isBrandingCustomized(),
                    'goals'     => ['auth'],
                ];
            }
        }

        // Notification goals
        if (!$hasGoals || in_array('notifications', $goals, true)) {
            $items[] = [
                'id'        => 'create_automation',
                'label'     => __('Create your first automation', 'wp-sms'),
                'route'     => 'automation',
                'completed' => ($summary['flows']['active'] ?? 0) > 0,
                'goals'     => ['notifications'],
            ];

            $items[] = [
                'id'        => 'connect_integration',
                'label'     => __('Connect an integration', 'wp-sms'),
                'route'     => 'channels/integrations',
                'completed' => $this->hasConnectedIntegration(),
                'goals'     => ['notifications'],
            ];
        }

        // Campaign goals
        if (!$hasGoals || in_array('campaigns', $goals, true)) {
            $items[] = [
                'id'        => 'add_contact',
                'label'     => __('Add your first contact', 'wp-sms'),
                'route'     => 'audience',
                'completed' => $featureState['has_contacts'],
                'goals'     => ['campaigns'],
            ];

            $items[] = [
                'id'        => 'create_campaign',
                'label'     => __('Create your first campaign', 'wp-sms'),
                'route'     => 'campaigns',
                'completed' => $featureState['has_campaigns'],
                'goals'     => ['campaigns'],
            ];
        }

        // Migration item (if detected and not completed)
        $migrationState = $this->migrationStateManager->getState();
        $migrationStatus = $migrationState['status'] ?? null;
        $detectedMigrations = $this->migrationManager->getAvailableMigrators();

        if (!empty($detectedMigrations) && $migrationStatus !== MigrationStatus::Completed->value) {
            $firstMigration = reset($detectedMigrations);
            $items[] = [
                'id'        => 'import_migration',
                'label'     => sprintf(__('Import from %s', 'wp-sms'), $firstMigration->pluginName),
                'route'     => 'settings/migration',
                'completed' => false,
                'goals'     => [],
            ];
        }

        return $items;
    }

    private function isBrandingCustomized(): bool
    {
        $branding = get_option(BrandingRepository::OPTION_KEY, []);

        if (!is_array($branding)) {
            return false;
        }

        return !empty($branding['logo_url']) || (
            isset($branding['primary_color']) &&
            $branding['primary_color'] !== BrandingRepository::DEFAULTS['primary_color']
        );
    }

    private function hasConnectedIntegration(): bool
    {
        $available = $this->integrationRegistry->getAvailable();
        foreach ($available as $integration) {
            if ($integration->isConnected()) {
                return true;
            }
        }
        return false;
    }
}
