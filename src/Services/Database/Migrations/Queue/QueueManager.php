<?php

namespace WP_SMS\Services\Database\Migrations\Queue;

use WP_SMS\Abstracts\BaseMigrationManager;
use WP_SMS\Option;
use WP_SMS\Services\Database\DatabaseHelper;

/**
 * Queue Migration Manager
 *
 * Manages the background execution of database migrations using WordPress queue system.
 * This class provides a comprehensive queue migration system with automatic execution,
 * user notifications, and proper security handling.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class QueueManager extends BaseMigrationManager
{
    /**
     * The action slug used for manually triggering the queue migration.
     *
     * @var string
     */
    private const MIGRATION_ACTION = 'wp_sms_run_queue_background_process';

    /**
     * The nonce name used to secure the manual migration action.
     *
     * @var string
     */
    private const MIGRATION_NONCE = 'wp_sms_run_queue_background_process_nonce';

    /**
     * Class constructor.
     *
     * Initializes the migration handling system and attaches necessary WordPress hooks.
     */
    public function __construct()
    {
        add_action('current_screen', [$this, 'handleDoneNotice']);
        add_action('current_screen', [$this, 'handleNotice']);
        add_action('admin_post_' . self::MIGRATION_ACTION, [$this, 'handleQueueMigration']);
    }

    /**
     * Displays a success notice when the database migration process is completed.
     *
     * @return void
     */
    public function handleDoneNotice()
    {
        if (!$this->isValidContext() || QueueFactory::isMigrationCompleted()) {
            return;
        }

        $status = Option::getFromGroup('queue_status', Option::DB_GROUP);

        if ($status !== 'done') {
            return;
        }

        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-success wpsms-admin-notice is-dismissible"><p><strong>%s</strong> — %s</p></div>',
                esc_html__('Update complete', 'wp-sms'),
                esc_html__('thanks for staying up to date!', 'wp-sms')
            );
        });

        Option::deleteFromGroup('queue_status', Option::DB_GROUP);
    }

    /**
     * Displays an admin notice with a start button for queue migration.
     *
     * @return void
     */
    public function handleNotice()
    {
        if (
            !$this->isValidContext() ||
            QueueFactory::isMigrationCompleted() ||
            !QueueFactory::needsMigration()
        ) {
            return;
        }

        $isMigrated = QueueFactory::isDatabaseMigrated();

        if (!$isMigrated) {
            return;
        }

        $migrationUrl = add_query_arg(
            [
                'action'       => self::MIGRATION_ACTION,
                'nonce'        => wp_create_nonce(self::MIGRATION_NONCE),
                'current_page' => DatabaseHelper::getCurrentAdminUrl()
            ],
            admin_url('admin-post.php')
        );

        add_action('admin_notices', function () use ($migrationUrl) {
            printf(
                '<div class="notice notice-warning wpsms-admin-notice">
                    <div style="display: block;">
                        <p><strong>%1$s:</strong> %2$s</p>
                        <p><a href="%3$s" class="button button-primary">%4$s</a></p>
                    </div>
                </div>',
                esc_html__('WP SMS needs a quick update', 'wp-sms'),
                esc_html__('Run this brief update to keep your SMS functionality working correctly.', 'wp-sms'),
                esc_url($migrationUrl),
                esc_html__('Update Now', 'wp-sms')
            );
        });
    }

    /**
     * Handles the request to start the queue migration process.
     *
     * @return bool|void False if the request is invalid, void otherwise.
     */
    public function handleQueueMigration()
    {
        check_admin_referer(self::MIGRATION_NONCE, 'nonce');

        if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== self::MIGRATION_ACTION) {
            return false;
        }

        $this->verifyMigrationPermission();

        if (!QueueFactory::needsMigration()) {
            return false;
        }

        $this->executeAllMigrations();

        Option::updateInGroup('queue_status', 'done', Option::DB_GROUP);

        $this->handleRedirect();
    }

    /**
     * Executes all pending queue-based migration steps.
     *
     * @return void
     */
    private function executeAllMigrations()
    {
        $pendingSteps = QueueFactory::getPendingMigrationSteps();

        foreach ($pendingSteps as $step) {
            QueueFactory::executeMigrationStep($step);
        }
    }

    /**
     * Handles the redirect after processing queue migrations.
     *
     * @return void
     */
    private function handleRedirect()
    {
        $redirectUrl = isset($_POST['current_page']) ? $_POST['current_page'] : '';
        if (empty($redirectUrl) && isset($_GET['current_page'])) {
            $redirectUrl = $_GET['current_page'];
        }

        if (empty($redirectUrl)) {
            $redirectUrl = admin_url();
        }

        wp_redirect(esc_url_raw(urldecode($redirectUrl)));
        exit;
    }
}
