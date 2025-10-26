<?php

namespace WP_SMS\Abstracts;

use WP_SMS\User\UserHelper as User;
use WP_SMS\Traits\MigrationAccess;

/**
 * Abstract base class for managing migration-related operations.
 *
 * This abstract class centralizes common functionality needed by migration
 * managers, such as security checks and context validation.
 */
abstract class BaseMigrationManager
{
    use MigrationAccess;

    /**
     * Ensures the current user has permission to run migration-related operations.
     *
     * If the user lacks the required capability defined by the plugin option
     * `manage_capability` (defaulting to `manage_options`), execution will halt with
     * a 403 response.
     *
     * @return void
     */
    protected function verifyMigrationPermission()
    {
        if (User::hasCapability('manage_options')) {
            return;
        }

        wp_die(
            __('You do not have sufficient permissions to run the ajax migration process.', 'wp-sms'),
            __('Permission Denied', 'wp-sms'),
            [
                'response' => 403
            ]
        );
    }
}