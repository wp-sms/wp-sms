<?php

namespace WP_SMS\Services\Database\Migrations\BackgroundProcess;

use WP_SMS\Abstracts\BaseMigrationManager;
use WP_SMS\Admin\NoticeHandler\Notice;
use WP_SMS\Utils\MenuUtil as Menus;
use WP_SMS\Utils\Request;
use WP_SMS\Services\Database\Migrations\BackgroundProcess\Jobs\TestBackgroundProcess;
use WP_SMS\Traits\AjaxUtilityTrait;
use WP_SMS\Components\Ajax;
use Exception;

class BackgroundProcessManager extends BaseMigrationManager
{
    use AjaxUtilityTrait;

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
    private $backgroundProcesses = [
        'test_background_process' => TestBackgroundProcess::class
    ];

    /**
     * List of available data migration keys.
     *
     * @var array<string> Array of migration keys.
     */
    private $dataMirations = [];

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
    public const BACKGROUND_PROCESS_ACTION = 'run_async_background_process';

    /**
     * The nonce name used to secure the manual migration action.
     *
     * @var string
     */
    public const BACKGROUND_PROCESS_NONCE = 'run_ajax_background_process_nonce';

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
        Ajax::register('async_background_process', [$this, 'asyncBackgroundProcess'], false);
        add_action('admin_post_' . self::BACKGROUND_PROCESS_ACTION, [$this, 'handleBackgroundProcessAction']);
    }

    /**
     * Initialize and register background processes.
     *
     * @return void
     */
    private function initializeBackgroundProcess()
    {
        if (!empty($this->backgroundProcess) || empty($this->backgroundProcesses)) {
            return;
        }

        foreach ($this->backgroundProcesses as $key => $className) {
            $this->registerBackgroundProcess($className, $key);
        }
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
        if (!class_exists($className) && !empty($this->backgroundProcess[$processKey])) {
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
    public function getAllDataMirations()
    {
        return $this->dataMirations;
    }

    /**
     * Show progress notices for each registered background process.
     * Displays a notice like: "Calculate Post Words Count: 34% complete (34/100)."
     * Only shows while a process is active and has a non-zero total.
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
                __('<div id="wp-sms-async-background-process-notice">%1$s: <span class="percentage">%2$d%%</span> complete (<span class="processed">%3$d</span>/%4$d).<br/> %5$s</div>', 'wp-sms'),
                esc_html($label),
                (int)$percent,
                (int)$processed,
                (int)$total,
                esc_html__('You can continue using the plugin. The process runs safely in the background.', 'wp-sms')
            );

            Notice::addNotice($message, $instance->getInitiatedKey(), 'info', false);
        }
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

        wp_enqueue_script(
            'wp-sms-async-background-process',
            WP_SMS_URL . 'assets/js/backgroundProcessTracker.min.js',
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
                'job_completed_message' => $this->successNotice
            ]
        );
    }

    /**
     * Handle asynchronous background process status.
     *
     * @return void
     *
     * @throws Exception If verification or processing fails.
     */
    public function asyncBackgroundProcess()
    {
        try {
            $this->verifyAjaxRequest();
            $this->checkAdminReferrer('wp_rest', 'wpsms_nonce');
            $this->checkCapability('manage_options');

            $this->currentProcess = Request::get('current_process');

            $currentJob = $this->getBackgroundProcess($this->currentProcess);

            if (empty($this->currentProcess)) {
                Ajax::success([
                    'completed' => true,
                ]);
            }

            if (BackgroundProcessFactory::isProcessDone($this->currentProcess)) {
                Ajax::success([
                    'completed' => true,
                ]);
            }

            $total     = $currentJob->getTotal();
            $processed = $currentJob->getProcessed();

            Ajax::success([
                'percentage' => empty($processed) ? 0 : (int)floor(($processed / $total) * 100),
                'processed'  => $currentJob->getProcessed(),
            ]);
        } catch (Exception $e) {
            Ajax::error($e->getMessage(), null, $e->getCode());
        }
    }

    /**
     * Admin handler for manually triggering a background migration.
     *
     * Hook: `admin_post_` . self::BACKGROUND_PROCESS_ACTION
     * Steps: verify nonce, check capability, get job via `job_key`,
     * optionally reset when `force=1`, run `$job->process()`, then redirect
     * to `Menus::admin_url($redirect)`.
     *
     * Params: `job_key` (string), `redirect` (string), `force` (bool), `nonce` (string).
     *
     * @return void
     */
    public function handleBackgroundProcessAction()
    {
        check_admin_referer(self::BACKGROUND_PROCESS_NONCE, 'nonce');

        if (!Request::compare('action', self::BACKGROUND_PROCESS_ACTION)) {
            return false;
        }

        $this->verifyMigrationPermission();

        $jobKey   = Request::get('job_key');
        $isForced = Request::get('force', false, 'bool');
        $redirect = Request::get('redirect');
        $tab      = Request::get('tab');

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

        $adminUrlargs = [];

        if (!empty($tab)) {
            $adminUrlargs['tab'] = $tab;
        }

        wp_redirect(Menus::getAdminUrl($redirect, $adminUrlargs));
        exit;
    }
}