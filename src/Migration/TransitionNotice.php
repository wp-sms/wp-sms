<?php

namespace WSms\Migration;

use WSms\Migration\Adapters\Digits\DigitsDetector;
use WSms\Service\Admin\AdminManager;

defined('ABSPATH') || exit;

class TransitionNotice
{
    private const SNOOZE_META = 'wsms_migration_notice_snoozed';
    private const SNOOZE_DAYS = 7;

    public function __construct(
        private readonly DigitsDetector $detector,
        private readonly MigrationStateManager $stateManager,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_notices', [$this, 'render']);
        add_action('wp_ajax_wsms_snooze_migration_notice', [$this, 'handleSnooze']);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->shouldShowNotice()) {
            return;
        }

        $state = $this->stateManager->getState();
        $status = $state !== null ? MigrationStatus::tryFrom($state['status'] ?? '') : null;

        // Running/completed states don't need detection — skip the COUNT queries.
        if ($status === MigrationStatus::Running) {
            $this->renderProgressNotice($state);
            return;
        }
        if ($status === MigrationStatus::Completed) {
            $this->renderCompletedNotice($state);
            return;
        }

        $detection = $this->detector->detect();
        if ($detection->active) {
            $this->renderDetectedNotice($detection, $state);
        } elseif ($detection->dataFound) {
            $this->renderDeactivatedDataNotice($detection);
        }
    }

    private function shouldShowNotice(): bool
    {
        // Only show on WP-SMS admin pages and the main dashboard.
        $screen = get_current_screen();
        if ($screen === null) {
            return false;
        }

        $isWsmsPage = str_contains($screen->id, 'wsms') || str_contains($screen->id, 'wp-sms');
        $isDashboard = $screen->id === 'dashboard';

        if (!$isWsmsPage && !$isDashboard) {
            return false;
        }

        // Check snooze.
        $snoozedUntil = get_user_meta(get_current_user_id(), self::SNOOZE_META, true);
        if ($snoozedUntil && strtotime($snoozedUntil) > time()) {
            return false;
        }

        return true;
    }

    /**
     * State 1: Digits detected, awaiting admin action.
     */
    private function renderDetectedNotice(DetectionResult $detection, ?array $state): void
    {
        $summary = implode(', ', $detection->summary);
        $migrationUrl = $this->migrationUrl();
        $snoozeUrl = $this->snoozeUrl();

        // Show initiator info if migration was started by another admin.
        $initiatorInfo = '';
        if ($state !== null && !empty($state['created_by'])) {
            $creator = get_userdata($state['created_by']);
            if ($creator && $creator->ID !== get_current_user_id()) {
                $initiatorInfo = sprintf(
                    '<br><small>%s</small>',
                    sprintf(
                        esc_html__('Started by %s on %s', 'wp-sms'),
                        esc_html($creator->display_name),
                        esc_html($state['started_at'] ?? ''),
                    ),
                );
            }
        }

        echo '<div class="notice notice-info">';
        echo '<p><strong>' . esc_html__('Welcome to WP-SMS!', 'wp-sms') . '</strong> ';
        echo esc_html__('We noticed you\'re using Digits.', 'wp-sms') . '</p>';
        echo '<p>' . sprintf(
            esc_html__('We can import your %s. Your Digits data stays completely untouched — you can roll back at any time.', 'wp-sms'),
            esc_html($summary),
        ) . '</p>';
        echo '<p>' . esc_html__('Login and registration continue to work normally through Digits while you review the import.', 'wp-sms') . '</p>';
        echo $initiatorInfo;
        echo '<p>';
        echo '<a href="' . esc_url($migrationUrl) . '" class="button button-primary">' . esc_html__('Review & Start Import', 'wp-sms') . '</a> ';
        echo '<a href="' . esc_url($snoozeUrl) . '" class="button">' . esc_html__('Remind Me Later', 'wp-sms') . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * State 2: Import in progress.
     */
    private function renderProgressNotice(array $state): void
    {
        $progress = $this->stateManager->getProgress();
        $percent = $progress['percent'] ?? 0;
        $migrationUrl = $this->migrationUrl();

        // Find current step label.
        $currentStepLabel = '';
        foreach ($progress['steps'] ?? [] as $stepId => $step) {
            if ($step['status'] === MigrationStatus::Running->value) {
                $currentStepLabel = sprintf(
                    '%s (%s of %s)',
                    $this->getStepLabel($stepId),
                    number_format_i18n($step['processed_items']),
                    number_format_i18n($step['total_items']),
                );
                break;
            }
        }

        echo '<div class="notice notice-info">';
        echo '<p><strong>' . esc_html__('Importing from Digits...', 'wp-sms') . '</strong></p>';
        echo '<div style="background:#e0e0e0;border-radius:4px;height:20px;margin:8px 0;overflow:hidden">';
        echo '<div style="background:#2271b1;height:100%;width:' . (int) $percent . '%;transition:width 0.3s"></div>';
        echo '</div>';
        echo '<p>' . sprintf(
            esc_html__('%d%% — %s', 'wp-sms'),
            (int) $percent,
            esc_html($currentStepLabel),
        ) . '</p>';
        echo '<p>' . esc_html__('This runs safely in the background. You can close this page.', 'wp-sms') . '</p>';
        echo '<p><a href="' . esc_url($migrationUrl) . '" class="button">' . esc_html__('View Details', 'wp-sms') . '</a></p>';
        echo '</div>';
    }

    /**
     * State 3: Import complete, ready to switch.
     */
    private function renderCompletedNotice(array $state): void
    {
        $migrationUrl = $this->migrationUrl();
        $snoozeUrl = $this->snoozeUrl();

        $steps = $state['steps'] ?? [];
        $summaryParts = [];
        foreach ($steps as $stepId => $step) {
            $count = $step['migrated_count'] ?? 0;
            if ($count > 0) {
                $summaryParts[] = number_format_i18n($count) . ' ' . $this->getStepLabel($stepId);
            }
        }

        echo '<div class="notice notice-success">';
        echo '<p><strong>' . esc_html__('Import complete! Your data is ready.', 'wp-sms') . '</strong></p>';
        if (!empty($summaryParts)) {
            echo '<p>' . sprintf(
                esc_html__('%s have been imported. When you\'re ready, activate WP-SMS authentication to replace Digits.', 'wp-sms'),
                esc_html(implode(', ', $summaryParts)),
            ) . '</p>';
        }
        echo '<p>';
        echo '<a href="' . esc_url($migrationUrl) . '" class="button button-primary">' . esc_html__('Activate WP-SMS Auth', 'wp-sms') . '</a> ';
        echo '<a href="' . esc_url($migrationUrl) . '" class="button">' . esc_html__('Review Results', 'wp-sms') . '</a> ';
        echo '<a href="' . esc_url($snoozeUrl) . '" class="button">' . esc_html__('Later', 'wp-sms') . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * State 4: Digits deactivated, unmigrated data found.
     */
    private function renderDeactivatedDataNotice(DetectionResult $detection): void
    {
        $migrationUrl = $this->migrationUrl();
        $snoozeUrl = $this->snoozeUrl();

        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html__('We found phone numbers and settings from Digits in your database. Want to import them into WP-SMS? Nothing will be deleted.', 'wp-sms') . '</p>';
        echo '<p>';
        echo '<a href="' . esc_url($migrationUrl) . '" class="button button-primary">' . esc_html__('Import from Digits', 'wp-sms') . '</a> ';
        echo '<a href="' . esc_url($snoozeUrl) . '" class="button">' . esc_html__('Remind Me Later', 'wp-sms') . '</a>';
        echo '</p>';
        echo '</div>';
    }

    private function migrationUrl(): string
    {
        return admin_url('admin.php?page=' . AdminManager::MENU_SLUG . '#/s-migration');
    }

    private function snoozeUrl(): string
    {
        return wp_nonce_url(admin_url('admin-ajax.php?action=wsms_snooze_migration_notice'), 'wsms_snooze_migration');
    }

    public function handleSnooze(): void
    {
        check_ajax_referer('wsms_snooze_migration');

        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }

        $until = gmdate('Y-m-d H:i:s', strtotime('+' . self::SNOOZE_DAYS . ' days'));
        update_user_meta(get_current_user_id(), self::SNOOZE_META, $until);

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    private function getStepLabel(string $stepId): string
    {
        return match ($stepId) {
            'user_phones'     => __('phone numbers', 'wp-sms'),
            'totp_enrollment' => __('authenticator enrollments', 'wp-sms'),
            'settings'        => __('settings', 'wp-sms'),
            default           => $stepId,
        };
    }
}
