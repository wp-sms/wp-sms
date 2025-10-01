<?php

namespace WP_SMS\Services\Database\Migrations\Ajax;

use Doctrine\DBAL\Driver\Exception;
use WP_SMS\Components\Ajax;
use WP_SMS\Traits\AjaxUtilityTrait;

class AjaxActions
{
    use AjaxUtilityTrait;

    /**
     * Register the AJAX handlers.
     *
     * @return void
     */
    public function register()
    {
        Ajax::register('background_process', [$this, 'backgroundProcess'], false);
    }

    /**
     * Handle the background process AJAX request.
     *
     * @return void
     */
    public function backgroundProcess()
    {
        try {
            if (AjaxFactory::isDatabaseMigrated()) {
                AjaxFactory::getCurrentMigrate();

                Ajax::success(esc_html__('Database migrated successfully.', 'wp-sms'));
            } else {
                Ajax::success(esc_html__('No migration needed.', 'wp-sms'));
            }
        } catch (Exception $e) {
            Ajax::error($e->getMessage(), null, $e->getCode());
        }
    }
}