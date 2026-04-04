<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Access\AccessManager;

defined('ABSPATH') || exit;

class AccessController extends Controller
{
    public function __construct(
        private readonly AccessManager $accessManager,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/access/profiles', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getProfiles'],
                'permission_callback' => $this->canManageSection('settings'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveProfiles'],
                'permission_callback' => $this->canManageSection('settings'),
                'args'                => [
                    'profiles' => ['required' => true, 'type' => 'object'],
                ],
            ],
        ]);
    }

    public function getProfiles(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            if (!function_exists('get_editable_roles')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            $editableRoles = \get_editable_roles();
            $roles = [];
            $profiles = [];

            foreach ($editableRoles as $slug => $role) {
                if ($slug === 'administrator') {
                    continue;
                }
                $roles[$slug] = $role['name'];
                $profiles[$slug] = $this->accessManager->getProfileForRole($slug);
            }

            $availableProfiles = [
                ['id' => 'no_access', 'label' => __('No Access', 'wp-sms')],
                ['id' => 'viewer',    'label' => __('Viewer', 'wp-sms')],
                ['id' => 'operator',  'label' => __('Operator', 'wp-sms')],
                ['id' => 'manager',   'label' => __('Manager', 'wp-sms')],
                ['id' => 'admin',     'label' => __('Admin', 'wp-sms')],
            ];

            return $this->ok([
                'profiles'           => $profiles,
                'available_profiles' => $availableProfiles,
                'roles'              => $roles,
                'sections'           => AccessManager::SECTION_CAPS,
                'profile_caps'       => AccessManager::PROFILES,
            ]);
        });
    }

    public function saveProfiles(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            if (!function_exists('get_editable_roles')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }

            $input = $request->get_param('profiles');
            $validProfileIds = array_keys(AccessManager::PROFILES);
            $editableRoles = array_keys(\get_editable_roles());
            $savedProfiles = \get_option(AccessManager::OPTION_KEY, []);

            foreach ($input as $roleName => $profileId) {
                if ($roleName === 'administrator') {
                    continue;
                }
                if (!in_array($roleName, $editableRoles, true)) {
                    continue;
                }
                if (!in_array($profileId, $validProfileIds, true)) {
                    continue;
                }
                $this->accessManager->applyProfile($roleName, $profileId);
                $savedProfiles[$roleName] = $profileId;
            }

            \update_option(AccessManager::OPTION_KEY, $savedProfiles, false);

            return $this->ok(['message' => __('Access profiles updated.', 'wp-sms')]);
        });
    }
}
