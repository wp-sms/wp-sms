<?php

namespace WP_SMS\Traits;

use WP_SMS\Utils\MenuUtil;

/**
 * Trait: MigrationAccess
 *
 * Minimal, stateless check used by migration-related classes to validate the
 * access context. It only verifies capability (defaulting to `manage_options`)
 * and that the current admin page belongs to the WP SMS plugin.
 *
 * Note: Nonce/CSRF validation and other context rules should be handled
 * separately (e.g., in controllers or managers) to keep this trait focused.
 */
trait MigrationAccess
{
    /**
     * True if the current request is allowed to run migration operations.
     *
     * @return bool
     */
    protected function isValidContext()
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        return MenuUtil::isInPluginPage();
    }
}
