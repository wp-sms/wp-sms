<?php

namespace WSms\Access;

defined('ABSPATH') || exit;

class AccessManager
{
    public const OPTION_KEY = 'wsms_access_profiles';

    /** All 15 WSMS capabilities. */
    public const ALL_CAPS = [
        'wsms_view_dashboard',
        'wsms_view_audience',
        'wsms_manage_audience',
        'wsms_view_campaigns',
        'wsms_manage_campaigns',
        'wsms_view_automation',
        'wsms_manage_automation',
        'wsms_view_channels',
        'wsms_manage_channels',
        'wsms_view_identity',
        'wsms_manage_identity',
        'wsms_view_monitoring',
        'wsms_manage_monitoring',
        'wsms_manage_settings',
    ];

    /** Section → [view_cap, manage_cap] map. Null means section has no cap for that action. */
    public const SECTION_CAPS = [
        'dashboard'  => ['view' => 'wsms_view_dashboard',   'manage' => null],
        'audience'   => ['view' => 'wsms_view_audience',     'manage' => 'wsms_manage_audience'],
        'campaigns'  => ['view' => 'wsms_view_campaigns',    'manage' => 'wsms_manage_campaigns'],
        'automation' => ['view' => 'wsms_view_automation',   'manage' => 'wsms_manage_automation'],
        'channels'   => ['view' => 'wsms_view_channels',     'manage' => 'wsms_manage_channels'],
        'identity'   => ['view' => 'wsms_view_identity',     'manage' => 'wsms_manage_identity'],
        'monitoring' => ['view' => 'wsms_view_monitoring',   'manage' => 'wsms_manage_monitoring'],
        'settings'   => ['view' => null,                     'manage' => 'wsms_manage_settings'],
    ];

    /** Profile definitions — each maps to a set of capabilities. */
    public const PROFILES = [
        'no_access' => [],
        'viewer'    => [
            'wsms_view_dashboard',
            'wsms_view_audience',
            'wsms_view_campaigns',
            'wsms_view_monitoring',
        ],
        'operator'  => [
            'wsms_view_dashboard',
            'wsms_view_audience',
            'wsms_manage_audience',
            'wsms_view_campaigns',
            'wsms_manage_campaigns',
            'wsms_view_monitoring',
        ],
        'manager'   => [
            'wsms_view_dashboard',
            'wsms_view_audience',
            'wsms_manage_audience',
            'wsms_view_campaigns',
            'wsms_manage_campaigns',
            'wsms_view_automation',
            'wsms_manage_automation',
            'wsms_view_channels',
            'wsms_view_identity',
            'wsms_view_monitoring',
            'wsms_manage_monitoring',
        ],
        'admin'     => self::ALL_CAPS,
    ];

    /**
     * Check if the current user can view a section.
     * Manage cap implies view access.
     */
    public function canViewSection(string $section): bool
    {
        if (\current_user_can('manage_options')) {
            return true;
        }

        $caps = self::SECTION_CAPS[$section] ?? null;
        if ($caps === null) {
            return false;
        }

        if ($caps['view'] !== null && \current_user_can($caps['view'])) {
            return true;
        }

        if ($caps['manage'] !== null && \current_user_can($caps['manage'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current user can manage (write to) a section.
     */
    public function canManageSection(string $section): bool
    {
        if (\current_user_can('manage_options')) {
            return true;
        }

        $caps = self::SECTION_CAPS[$section] ?? null;
        if ($caps === null || $caps['manage'] === null) {
            return false;
        }

        return \current_user_can($caps['manage']);
    }

    /**
     * Check if the current user has any WSMS access at all.
     */
    public function hasAnyAccess(): bool
    {
        if (\current_user_can('manage_options')) {
            return true;
        }

        foreach (self::ALL_CAPS as $cap) {
            if (\current_user_can($cap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply a profile to a WordPress role.
     * Removes all wsms_* caps first, then adds the profile's caps.
     */
    public function applyProfile(string $roleName, string $profileId): void
    {
        $role = \get_role($roleName);
        if ($role === null) {
            return;
        }

        foreach (self::ALL_CAPS as $cap) {
            $role->remove_cap($cap);
        }

        $caps = self::PROFILES[$profileId] ?? [];
        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }

    /**
     * Determine which profile a role currently matches.
     * Returns 'custom' if caps don't match any preset.
     */
    public function getProfileForRole(string $roleName): string
    {
        $role = \get_role($roleName);
        if ($role === null) {
            return 'no_access';
        }

        $roleCaps = [];
        foreach (self::ALL_CAPS as $cap) {
            if (!empty($role->capabilities[$cap])) {
                $roleCaps[] = $cap;
            }
        }

        sort($roleCaps);

        foreach (self::sortedProfiles() as $profileId => $sorted) {
            if ($roleCaps === $sorted) {
                return $profileId;
            }
        }

        return 'custom';
    }

    /**
     * Get an associative array of all wsms capabilities for the current user.
     */
    public function getCurrentUserCaps(): array
    {
        if (\current_user_can('manage_options')) {
            return ['is_admin' => true] + array_fill_keys(self::ALL_CAPS, true);
        }

        $caps = ['is_admin' => false];
        foreach (self::ALL_CAPS as $cap) {
            $caps[$cap] = \current_user_can($cap);
        }

        return $caps;
    }

    /** @var array<string, string[]>|null Cached sorted profile caps for comparison. */
    private static ?array $sortedProfilesCache = null;

    /** @return array<string, string[]> */
    private static function sortedProfiles(): array
    {
        if (self::$sortedProfilesCache === null) {
            self::$sortedProfilesCache = [];
            foreach (self::PROFILES as $id => $caps) {
                $sorted = $caps;
                sort($sorted);
                self::$sortedProfilesCache[$id] = $sorted;
            }
        }

        return self::$sortedProfilesCache;
    }

    /**
     * Register the user_has_cap filter that grants the dynamic wsms_access meta-cap.
     * This meta-cap is used by add_menu_page to gate the admin menu.
     */
    public function registerMetaCap(): void
    {
        \add_filter('user_has_cap', function (array $allcaps, array $caps): array {
            if (!in_array('wsms_access', $caps, true)) {
                return $allcaps;
            }

            // Administrators always have access
            if (!empty($allcaps['manage_options'])) {
                $allcaps['wsms_access'] = true;
                return $allcaps;
            }

            // Grant wsms_access if user has any wsms_* cap
            foreach (self::ALL_CAPS as $cap) {
                if (!empty($allcaps[$cap])) {
                    $allcaps['wsms_access'] = true;
                    return $allcaps;
                }
            }

            return $allcaps;
        }, 10, 2);
    }
}
