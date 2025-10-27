<?php

namespace WP_SMS\Services\Database\Migrations\BackgroundProcess\Jobs;

use WP_SMS\Abstracts\BaseBackgroundProcess;
use WP_SMS\Admin\NoticeHandler\Notice;

class TestBackgroundProcess extends BaseBackgroundProcess
{
    /**
     * Unique background process action slug.
     *
     * @var string
     */
    protected $action = 'test_background_process';

    /**
     * Option key used to mark this process as initiated.
     *
     * @var string
     */
    protected $initiatedKey = 'test_background_process_initiated';

    /**
     * Number of fake items to process per batch.
     *
     * @var int
     */
    protected $batchSize = 50;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        add_action('admin_init', [$this, 'localizeJobTexts']);
    }

    /**
     * Localize job texts for display in the admin UI.
     *
     * @return void
     */
    public function localizeJobTexts()
    {
        $this->setSuccessNotice(
            esc_html__('Test background job finished successfully.', 'wp-sms')
        );

        $this->setJobTitle(
            esc_html__('Test Background Process', 'wp-sms')
        );

        $this->setJobDescription(
            esc_html__('A diagnostic job that processes fake IDs in batches to confirm that the background queue and dispatcher are working properly.', 'wp-sms')
        );
    }

    /**
     * Perform the task for a single queue item.
     *
     * @param mixed $item The queue item.
     * @return mixed
     */
    protected function task($item)
    {
        if (empty($item['ids']) || !is_array($item['ids'])) {
            return false;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WP_Sms TestBackgroundProcess] Processing IDs: ' . implode(',', $item['ids']));
        }

        $this->setProcessed($item['ids']);

        return false;
    }

    /**
     * Run when all queue items have been processed.
     *
     * @return void
     */
    protected function complete()
    {
        parent::complete();

        $this->clearTotalAndProcessed();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WP_Sms TestBackgroundProcess] COMPLETE ✅');
        }
    }

    /**
     * Display the initial admin notice to start the background process.
     *
     * @param bool $force Whether to include a force flag to restart the job.
     * @return void
     */
    public function initialNotice($force = false)
    {
        $actionUrl = $this->getActionUrl($force);

        $message = sprintf(
            '<div id="wp-sms-background-process-notice">
                <p><strong>%1$s</strong></p>
                <p>%2$s</p>
                <p><a href="%3$s" class="button-primary">%4$s</a></p>
            </div>',
            esc_html__('WP Sms Test Job', 'wp-sms'),
            esc_html__('Click "Run Test Job" to queue 500 fake items and process them in the background. Check debug.log to verify that batches are running.', 'wp-sms'),
            esc_url($actionUrl),
            esc_html__('Run Test Job', 'wp-sms')
        );

        Notice::addNotice(
            $message,
            'start_test_background_process',
            'info',
            false
        );
    }

    /**
     * Initialize and dispatch the background process.
     *
     * @return void
     */
    public function process()
    {
        if ($this->is_active()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WP_Sms TestBackgroundProcess] process() called but already active.');
            }
            return;
        }

        $allIds = range(1, 500);

        $this->setTotal(count($allIds));

        $chunks = array_chunk($allIds, $this->batchSize);
        foreach ($chunks as $chunk) {
            $this->push_to_queue(['ids' => $chunk]);
        }

        $this->setInitiated();
        $this->save()->dispatch();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WP_Sms TestBackgroundProcess] DISPATCHED with ' . count($allIds) . ' fake IDs.');
        }
    }
}