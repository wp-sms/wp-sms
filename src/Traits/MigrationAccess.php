<?php

namespace WP_SMS\Traits;

use WP_SMS\Utils\MenuUtil as Menus;
use WP_SMS\User\UserHelper as User;

/**
 * Trait: MigrationAccess
 *
 * Minimal, stateless check used by migration-related classes to validate the
 * access context. It only verifies capability and that the current
 * admin page belongs to the WP SMS plugin.
 *
 * Note: Nonce/CSRF validation and other context rules should be handled
 * separately (e.g., in controllers or managers) to keep this trait focused.
 */
trait MigrationAccess
{
    /**
     * Validates whether the current admin page and user have access to handle migration-related functionality.
     *
     * This method performs security checks to ensure that:
     * - The current user has the sufficient perimissions
     * - The current page is a WP SMS plugin page
     *
     * @return bool True if the context is valid for migration operations, false otherwise
     */
    protected function isValidContext()
    {
        if (!User::hasCapability('manage_options')) {
            return false;
        }

        return Menus::isInPluginPage();
    }
}