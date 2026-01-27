<?php

namespace WP_SMS\Services\Database\Migrations\BackgroundProcess;

use WP_SMS\Abstracts\BaseMigrationManager;
use WP_SMS\Services\Database\DatabaseHelper;
use WP_SMS\Utils\OptionUtil;

/**
 * Class BackgroundProcessManager
 *
 * Manages background processes for database migrations.
 *
 * ## Extensibility
 *
 * Add-ons can register custom background processes using the `wp_sms_register_background_jobs` filter:
 *
 * ```php
 * add_filter('wp_sms_register_background_jobs', function ($jobs) {
 *     $jobs['my_custom_job'] = \MyAddon\BackgroundJobs\MyCustomJob::class;
 *     return $jobs;
 * });
 * ```
 *
 * Custom job classes must extend `WP_SMS\Abstracts\BaseBackgroundProcess`.
 *
 * @see \WP_SMS\Abstracts\BaseBackgroundProcess
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class BackgroundProcessManager extends BaseMigrationManager
{
    /**
     * Filter hook name for registering background jobs.
     *
     * @var string
     */
    public const REGISTER_JOBS_FILTER = 'wp_sms_register_background_jobs';

    /**
     * Filter hook name for registering data migrations.
     *
     * @var string
     */
    public const REGISTER_DATA_MIGRATIONS_FILTER = 'wp_sms_register_data_migrations';

    /**
     * The background process instances.
     *
     * @var array<string, object>
     */
    private $backgroundProcess = [];

    /**
     * List of background process classes to be registered.
     *
     * @var array<string, string>
     */
    private $backgroundProcesses = [];

    /**
     * Core background process classes (always registered).
     *
     * @var array<string, string>
     */
    private $coreBackgroundProcesses = [
        // Add core background processes here as needed
    ];

    /**
     * List of available data migration keys.
     *
     * @var array<string> Array of migration keys.
     */
    private $dataMigrations = [];

    /**
     * Core data migration keys (always registered).
     *
     * @var array<string> Array of core migration keys.
     */
    private $coreDataMigrations = [
        // Add core data migrations here as needed
    ];

    /**
     * The key of the currently running background process.
     *
     * @var string
     */
    private $currentProcess = '';

    /**
     * Success message for the currently active background job.
     *
     * @var string
     */
    private $successNotice = '';

    /**
     * The action slug used for manually triggering the background migration.
     *
     * @var string
     */
    public const BACKGROUND_PROCESS_ACTION = 'wp_sms_run_async_background_process';

    /**
     * The nonce name used to secure the manual migration action.
     *
     * @var string
     */
    public const BACKGROUND_PROCESS_NONCE = 'wp_sms_run_ajax_background_process_nonce';

    /**
     * The action slug used for retrying a failed background process.
     *
     * @var string
     */
    public const RETRY_PROCESS_ACTION = 'wp_sms_retry_background_process';

    /**
     * The action slug used for cancelling a background process.
     *
     * @var string
     */
    public const CANCEL_PROCESS_ACTION = 'wp_sms_cancel_background_process';

    /**
     * Class constructor.
     *
     * Initializes background processes and attaches necessary WordPress hooks.
     */
    public function __construct()
    {
        $this->initializeBackgroundProcess();

        add_action('admin_init', [$this, 'showProgressNotices']);
        add_action('admin_enqueue_scripts', [$this, 'registerScript']);
        add_action('admin_post_' . self::BACKGROUND_PROCESS_ACTION, [$this, 'handleBackgroundProcessAction']);
        add_action('admin_post_' . self::RETRY_PROCESS_ACTION, [$this, 'handleRetryAction']);
        add_action('admin_post_' . self::CANCEL_PROCESS_ACTION, [$this, 'handleCancelAction']);
        add_action('wp_ajax_wp_sms_background_process_progress', [$this, 'handleProgressAjax']);
    }

    /**
     * Initialize and register background processes.
     *
     * Merges core background processes with any additional processes registered
     * via the `wp_sms_register_background_jobs` filter.
     *
     * @return void
     */
    private function initializeBackgroundProcess()
    {
        if (!empty($this->backgroundProcess)) {
            return;
        }

        $this->backgroundProcesses = $this->getRegisteredBackgroundProcesses();
        $this->dataMigrations      = $this->getRegisteredDataMigrations();

        if (empty($this->backgroundProcesses)) {
            return;
        }

        foreach ($this->backgroundProcesses as $key => $className) {
            $this->registerBackgroundProcess($className, $key);
        }
    }

    /**
     * Get all registered background processes (core + add-on).
     *
     * Applies the `wp_sms_register_background_jobs` filter to allow
     * add-ons to register their own background processes.
     *
     * @return array<string, string> Array of process key => class name.
     */
    private function getRegisteredBackgroundProcesses()
    {
        /**
         * Filter to register custom background processes.
         *
         * Add-ons can use this filter to register their own background processes.
         * Each process must extend `WP_SMS\Abstracts\BaseBackgroundProcess`.
         *
         * @since 7.1
         *
         * @param array<string, string> $jobs Array of job key => fully qualified class name.
         *
         * @example
         * ```php
         * add_filter('wp_sms_register_background_jobs', function ($jobs) {
         *     $jobs['my_addon_sync_job'] = \Addon\Jobs\SyncDataJob::class;
         *     return $jobs;
         * });
         * ```
         */
        $allJobs = apply_filters(self::REGISTER_JOBS_FILTER, $this->coreBackgroundProcesses);

        return $this->validateBackgroundProcesses($allJobs);
    }

    /**
     * Get all registered data migrations (core + add-on).
     *
     * Applies the `wp_sms_register_data_migrations` filter to allow
     * add-ons to register their data migration keys.
     *
     * @return array<string> Array of data migration keys.
     */
    private function getRegisteredDataMigrations()
    {
        /**
         * Filter to register custom data migrations.
         *
         * Add-ons can use this filter to register their own data migration keys.
         * These keys should correspond to background process keys that handle data migrations.
         *
         * @since 7.1
         *
         * @param array<string> $migrations Array of data migration keys.
         *
         * @example
         * ```php
         * add_filter('wp_sms_register_data_migrations', function ($migrations) {
         *     $migrations[] = 'my_addon_data_migration';
         *     return $migrations;
         * });
         * ```
         */
        $allMigrations = apply_filters(self::REGISTER_DATA_MIGRATIONS_FILTER, $this->coreDataMigrations);

        return array_unique(array_filter($allMigrations, 'is_string'));
    }

    /**
     * Validate registered background processes.
     *
     * Ensures all registered classes exist and extend BaseBackgroundProcess.
     *
     * @param array $jobs Array of job key => class name.
     * @return array<string, string> Validated array of jobs.
     */
    private function validateBackgroundProcesses($jobs)
    {
        if (!is_array($jobs)) {
            return $this->coreBackgroundProcesses;
        }

        $validated = [];

        foreach ($jobs as $key => $className) {
            if (!is_string($key) || empty($key)) {
                continue;
            }

            if (!is_string($className) || !class_exists($className)) {
                continue;
            }

            if (!is_subclass_of($className, \WP_SMS\Abstracts\BaseBackgroundProcess::class)) {
                continue;
            }

            $validated[$key] = $className;
        }

        return $validated;
    }

    /**
     * Register a background process by its class name and key.
     *
     * @param string $className The class name of the background process.
     * @param string $processKey The key to identify the background process.
     *
     * @return void
     */
    private function registerBackgroundProcess($className, $processKey)
    {
        if (!class_exists($className) || !empty($this->backgroundProcess[$processKey])) {
            return;
        }

        $this->backgroundProcess[$processKey] = new $className();
    }

    /**
     * Get a background process instance by its key.
     *
     * @param string $processKey The key of the background process.
     *
     * @return object|null The background process instance or null if not found.
     */
    public function getBackgroundProcess($processKey)
    {
        return $this->backgroundProcess[$processKey] ?? null;
    }

    /**
     * Get all registered background migration processes.
     *
     * @return array
     */
    public function getAllBackgroundProcesses()
    {
        return $this->backgroundProcesses;
    }

    /**
     * Get the list of available data migrations (keys).
     *
     * @return array
     */
    public function getAllDataMigrations()
    {
        if (empty($this->dataMigrations)) {
            $this->dataMigrations = $this->getRegisteredDataMigrations();
        }

        return $this->dataMigrations;
    }

    /**
     * Show progress notices for each registered background process.
     * Displays a notice like: "Job Title: 34% complete (34/100)."
     * Only shows while a process is active and has a non-zero total.
     * Also displays error notices when dispatch fails due to loopback issues.
     *
     * @return void
     */
    public function showProgressNotices()
    {
        if (empty($this->backgroundProcess) || !$this->isValidContext()) {
            return;
        }

        foreach ($this->backgroundProcess as $key => $instance) {
            if (!is_object($instance)) {
                continue;
            }

            if (method_exists($instance, 'initialNotice')) {
                $instance->initialNotice();
            }

            // Check for dispatch errors (loopback failures).
            $dispatchError = method_exists($instance, 'get_dispatch_error') ? $instance->get_dispatch_error() : false;
            $isInitiated   = method_exists($instance, 'isInitiated') ? $instance->isInitiated() : false;
            $isProcessing  = method_exists($instance, 'is_processing') ? $instance->is_processing() : false;

            if ($dispatchError && $isInitiated && !$isProcessing) {
                $this->showDispatchErrorNotice($instance, $key, $dispatchError);
                continue;
            }

            $isActive = method_exists($instance, 'is_active') ? (bool)$instance->is_active() : false;

            if (!$isActive) {
                continue;
            }

            $this->currentProcess = $key;

            $total     = method_exists($instance, 'getTotal') ? $instance->getTotal() : 0;
            $processed = method_exists($instance, 'getProcessed') ? $instance->getProcessed() : 0;

            if ($total <= 0 || $processed >= $total) {
                continue;
            }

            $percent = empty($processed) ? 0 : (int)floor(($processed / $total) * 100);
            if ($percent >= 100) {
                $percent = 99;
            } elseif ($percent < 0) {
                $percent = 0;
            }

            $this->successNotice = $instance->getSuccessNotice();

            $label = $instance->getJobTitle();

            /* translators: 1: job title, 2: percent complete, 3: processed count, 4: total count, 5: appended background-running note */
            $message = sprintf(
                '<div id="wp-sms-async-background-process-notice">%1$s: <span class="percentage">%2$d%%</span> complete (<span class="processed">%3$d</span>/%4$d).<br/> %5$s</div>',
                esc_html($label),
                (int)$percent,
                (int)$processed,
                (int)$total,
                esc_html__('You can continue using the plugin. The process runs safely in the background.', 'wp-sms')
            );

            add_action('admin_notices', function () use ($message) {
                printf('<div class="notice notice-info wpsms-admin-notice">%s</div>', $message);
            });
        }
    }

    /**
     * Display an error notice when background process dispatch fails.
     *
     * @param object $instance      The background process instance.
     * @param string $key           The process key.
     * @param array  $dispatchError The dispatch error data with 'message' and 'time'.
     *
     * @return void
     */
    private function showDispatchErrorNotice($instance, $key, $dispatchError)
    {
        $label    = method_exists($instance, 'getJobTitle') ? $instance->getJobTitle() : $key;
        $retryUrl = wp_nonce_url(
            add_query_arg(
                [
                    'action'  => self::RETRY_PROCESS_ACTION,
                    'job_key' => $key,
                ],
                admin_url('admin-post.php')
            ),
            self::RETRY_PROCESS_ACTION
        );

        $message = sprintf(
            /* translators: 1: Job title, 2: Error message, 3: Retry URL, 4: Retry button text */
            '<p><strong>%1$s:</strong> %2$s</p> <a href="%3$s" class="button-primary">%4$s</a>',
            esc_html($label),
            esc_html__('Background process could not start. Your server may be blocking loopback requests.', 'wp-sms'),
            esc_url($retryUrl),
            esc_html__('Retry', 'wp-sms')
        );

        add_action('admin_notices', function () use ($message) {
            printf('<div class="notice notice-error wpsms-admin-notice" style="display: block;">%s</div>', $message);
        });
    }

    /**
     * Registers JavaScript files required for migration execution.
     *
     * @return void
     */
    public function registerScript()
    {
        if (!$this->isValidContext()) {
            return;
        }

        // Only enqueue if we have active processes
        if (empty($this->currentProcess)) {
            return;
        }

        wp_enqueue_script(
            'wp-sms-async-background-process',
            WP_SMS_URL . 'assets/js/background-process-tracker.js',
            ['jquery'],
            WP_SMS_VERSION,
            ['in_footer' => true]
        );

        wp_localize_script(
            'wp-sms-async-background-process',
            'Wp_Sms_Async_Background_Process_Data',
            [
                'rest_api_nonce'        => wp_create_nonce('wp_rest'),
                'ajax_url'              => admin_url('admin-ajax.php'),
                'interval'              => apply_filters('wp_sms_async_background_process_ajax_interval', 5000),
                'current_process'       => $this->currentProcess,
                'completed_message'     => esc_html__('WP SMS: Background process completed successfully.', 'wp-sms'),
                'job_completed_message' => $this->successNotice,
                'loopback_error_hint'   => esc_html__('Your server may be blocking loopback requests. Check Tools > Site Health for connectivity issues.', 'wp-sms'),
            ]
        );
    }

    /**
     * Admin handler for manually triggering a background migration.
     *
     * Hook: `admin_post_` . self::BACKGROUND_PROCESS_ACTION
     * Steps: verify nonce, check capability, get job via `job_key`,
     * optionally reset when `force=1`, run `$job->process()`, then redirect.
     *
     * Params: `job_key` (string), `redirect` (string), `force` (bool), `nonce` (string).
     *
     * @return void
     */
    public function handleBackgroundProcessAction()
    {
        check_admin_referer(self::BACKGROUND_PROCESS_NONCE, 'nonce');

        if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== self::BACKGROUND_PROCESS_ACTION) {
            return;
        }

        $this->verifyMigrationPermission();

        $jobKey   = isset($_REQUEST['job_key']) ? sanitize_key($_REQUEST['job_key']) : '';
        $isForced = isset($_REQUEST['force']) && $_REQUEST['force'];
        $redirect = isset($_REQUEST['redirect']) ? esc_url_raw($_REQUEST['redirect']) : '';

        $job = $this->getBackgroundProcess($jobKey);

        if (empty($job)) {
            wp_die(
                __('Background job not found.', 'wp-sms'),
                __('Job not found', 'wp-sms'),
                [
                    'response' => 404,
                ]
            );
        }

        if ($isForced) {
            $job->stopProcess();
            $job->setInitiated(false);
        }

        if ($job->isInitiated()) {
            wp_die(
                __('This background job has already been started.', 'wp-sms'),
                __('Job already running', 'wp-sms'),
                [
                    'response' => 409,
                ]
            );
        }

        $job->process();

        if (empty($redirect)) {
            $redirect = admin_url();
        }

        wp_redirect($redirect);
        exit;
    }

    /**
     * Handle retry action for failed background processes.
     *
     * Clears the dispatch error and attempts to re-dispatch the process.
     *
     * @return void
     */
    public function handleRetryAction()
    {
        check_admin_referer(self::RETRY_PROCESS_ACTION);

        $this->verifyMigrationPermission();

        $jobKey = isset($_REQUEST['job_key']) ? sanitize_key($_REQUEST['job_key']) : '';

        if (empty($jobKey)) {
            wp_die(
                __('Invalid job key.', 'wp-sms'),
                __('Invalid request', 'wp-sms'),
                ['response' => 400]
            );
        }

        $job = $this->getBackgroundProcess($jobKey);

        if (empty($job)) {
            wp_die(
                __('Background job not found.', 'wp-sms'),
                __('Job not found', 'wp-sms'),
                ['response' => 404]
            );
        }

        // Clear the dispatch error.
        if (method_exists($job, 'clear_dispatch_error')) {
            $job->clear_dispatch_error();
        }

        // Attempt to dispatch again.
        $result = $job->dispatch();

        // If dispatch still fails, the error will be stored and shown on next page load.
        if (is_wp_error($result)) {
            // Redirect back to referrer so user sees the error notice.
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }

        // Success - redirect back.
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Handle cancel action for background processes.
     *
     * Stops the process and clears all related data.
     *
     * @return void
     */
    public function handleCancelAction()
    {
        check_admin_referer(self::CANCEL_PROCESS_ACTION);

        $this->verifyMigrationPermission();

        $jobKey = isset($_REQUEST['job_key']) ? sanitize_key($_REQUEST['job_key']) : '';

        if (empty($jobKey)) {
            wp_die(
                __('Invalid job key.', 'wp-sms'),
                __('Invalid request', 'wp-sms'),
                ['response' => 400]
            );
        }

        $job = $this->getBackgroundProcess($jobKey);

        if (empty($job)) {
            wp_die(
                __('Background job not found.', 'wp-sms'),
                __('Job not found', 'wp-sms'),
                ['response' => 404]
            );
        }

        // Stop the process and clear all data.
        if (method_exists($job, 'stopProcess')) {
            $job->stopProcess();
        }

        if (method_exists($job, 'setInitiated')) {
            $job->setInitiated(false);
        }

        if (method_exists($job, 'clear_dispatch_error')) {
            $job->clear_dispatch_error();
        }

        // Redirect back.
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Handle AJAX request for background process progress.
     *
     * @return void
     */
    public function handleProgressAjax()
    {
        check_ajax_referer('wp_rest', '_wpnonce');

        $processKey = isset($_POST['process_key']) ? sanitize_key($_POST['process_key']) : '';

        if (empty($processKey)) {
            wp_send_json_success([
                'completed' => true,
            ]);
        }

        $job = $this->getBackgroundProcess($processKey);

        if (empty($job)) {
            wp_send_json_success([
                'completed' => true,
            ]);
        }

        // Check if process is done (initiated but no longer active)
        if (BackgroundProcessFactory::isProcessDone($processKey)) {
            wp_send_json_success([
                'completed' => true,
            ]);
        }

        // Check for dispatch error
        $dispatchError = method_exists($job, 'get_dispatch_error') ? $job->get_dispatch_error() : false;
        if ($dispatchError) {
            wp_send_json_success([
                'has_error'     => true,
                'error_message' => $dispatchError['message'] ?? esc_html__('Background process could not start.', 'wp-sms'),
            ]);
        }

        $total     = $job->getTotal();
        $processed = $job->getProcessed();

        wp_send_json_success([
            'percentage' => empty($processed) ? 0 : (int)floor(($processed / $total) * 100),
            'processed'  => $job->getProcessed(),
        ]);
    }
}
