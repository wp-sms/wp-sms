<?php

namespace WP_SMS\Abstracts;

use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;
use WP_SMS\Services\Database\Migrations\BackgroundProcess\BackgroundProcessFactory;
use WP_SMS\Traits\MigrationAccess;
use WP_SMS\Utils\OptionUtil;

/**
 * Class BaseBackgroundProcess
 *
 * An abstract class for creating background processes using WP_Background_Process.
 * This class provides a structure for defining background tasks.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
abstract class BaseBackgroundProcess extends WP_Background_Process
{
    use MigrationAccess;

    /**
     * Prefix for the process.
     *
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * Initiated key for option storage.
     *
     * @var string
     */
    protected $initiatedKey = '';

    /**
     * Total number of items to process.
     *
     * @var string
     */
    protected $totalOptionKey = '';

    /**
     * Number of processed items.
     *
     * @var int
     */
    protected $processedOptionKey = '';

    /**
     * Human-readable job title (source string; not translated here).
     *
     * @var string
     */
    protected $jobTitle = '';

    /**
     * Short job description for admin UI.
     *
     * @var string
     */
    protected $jobDescription = '';

    /**
     * Short button title for admin UI.
     *
     * @var string
     */
    protected $jobButtonTitle = '';

    /**
     * Success notice message to display in the admin UI when the job finishes.
     *
     * @var string
     */
    protected $successNotice = '';

    /**
     * Whether this job requires user confirmation before starting.
     *
     * @var bool
     */
    protected $confirmation = false;

    /**
     * Set the human-readable job title (source string; not translated here).
     *
     * @param string $title Source title for this background job.
     * @return void
     */
    protected function setJobTitle($title)
    {
        $this->jobTitle = $title;
    }

    /**
     * Get the human-readable job title (source string; not translated here).
     *
     * @return string Source job title string.
     */
    public function getJobTitle()
    {
        return $this->jobTitle;
    }

    /**
     * Set the short job description (source string; not translated here).
     *
     * @param string $description Source description for this job.
     * @return void
     */
    protected function setJobDescription($description)
    {
        $this->jobDescription = $description;
    }

    /**
     * Get the short job description (source string; not translated here).
     *
     * @return string Source job description string.
     */
    public function getJobDescription()
    {
        return $this->jobDescription;
    }

    /**
     * Set the short button title
     *
     * @param string $jobButtonTitle
     * @return void
     */
    protected function setJobButtonTitle($jobButtonTitle)
    {
        $this->jobButtonTitle = $jobButtonTitle;
    }

    /**
     * Get the short button title
     *
     * @return string
     */
    public function getJobButtonTitle()
    {
        return $this->jobButtonTitle;
    }

    /**
     * Checks if user confirmation is required before running the background process.
     *
     * @return bool True if confirmation is required, false otherwise.
     */
    public function isConfirmationRequired()
    {
        return $this->confirmation;
    }

    /**
     * Check if the process has been initiated.
     *
     * @param bool $status Whether the job is marked as initiated. Default true.
     * @return void
     */
    public function setInitiated($status = true)
    {
        OptionUtil::saveOptionGroup($this->initiatedKey, $status, 'jobs');
    }

    /**
     * Check if the process has been initiated.
     *
     * @return bool
     */
    public function isInitiated()
    {
        return OptionUtil::getOptionGroup('jobs', $this->initiatedKey, false);
    }

    /**
     * Get the option key used to store the "initiated" flag for this job.
     *
     * @return string Option key name for the initiated status.
     */
    public function getInitiatedKey()
    {
        return $this->initiatedKey;
    }

    /**
     * Set a success notice to be displayed to the user.
     *
     * @param string $message The success message to display.
     * @return void
     */
    protected function setSuccessNotice($message)
    {
        $this->successNotice = $message;
    }

    /**
     * Get the success notice message for this background job.
     *
     * @return string Success notice text.
     */
    public function getSuccessNotice()
    {
        return $this->successNotice;
    }

    /**
     * Set the total and processed option keys.
     *
     * @return void
     */
    protected function setTotalAndProcessed()
    {
        $this->totalOptionKey     = $this->action . '_total';
        $this->processedOptionKey = $this->action . '_processed';
    }

    /**
     * Set the total number of items to process.
     *
     * @param array|int $items The items to count or count of the items.
     * @return void
     */
    protected function setTotal($items)
    {
        $total = $this->getTotal();

        if (!empty($total) || empty($items)) {
            return;
        }

        $total = is_array($items) ? count($items) : $items;
        OptionUtil::saveOptionGroup($this->totalOptionKey, $total, 'jobs');
    }

    /**
     * Set the number of processed items.
     *
     * @param array $processed The items that have been processed.
     * @return void
     */
    protected function setProcessed($processed)
    {
        if (empty($processed)) {
            return;
        }

        $processedCount   = 0;
        $alreadyProcessed = $this->getProcessed();

        $processedCount = (int)$alreadyProcessed + intval(count($processed));

        OptionUtil::saveOptionGroup($this->processedOptionKey, $processedCount, 'jobs');
    }

    /**
     * Save the start time when the process begins.
     *
     * @return void
     */
    public function saveStartTime()
    {
        OptionUtil::saveOptionGroup($this->action . '_start_time', time(), 'jobs');
    }

    /**
     * Get the start time of the process.
     *
     * @return int Unix timestamp, or 0 if not set.
     */
    public function getStartTime()
    {
        return (int) OptionUtil::getOptionGroup('jobs', $this->action . '_start_time', 0);
    }

    /**
     * Save the end time when the process completes.
     *
     * @return void
     */
    public function saveEndTime()
    {
        OptionUtil::saveOptionGroup($this->action . '_end_time', time(), 'jobs');
    }

    /**
     * Get the end time of the process.
     *
     * @return int Unix timestamp, or 0 if not set.
     */
    public function getEndTime()
    {
        return (int) OptionUtil::getOptionGroup('jobs', $this->action . '_end_time', 0);
    }

    /**
     * Get the last activity timestamp (end time if completed, start time otherwise).
     *
     * @return int Unix timestamp, or 0 if not set.
     */
    public function getLastActivityTime()
    {
        $endTime = $this->getEndTime();
        if ($endTime > 0) {
            return $endTime;
        }

        return $this->getStartTime();
    }

    /**
     * Clear the start and end times.
     *
     * @return void
     */
    protected function clearProcessTimes()
    {
        OptionUtil::deleteOptionGroup($this->action . '_start_time', 'jobs');
        OptionUtil::deleteOptionGroup($this->action . '_end_time', 'jobs');
    }

    /**
     * Clear the total and processed counts.
     *
     * @return void
     */
    protected function clearTotalAndProcessed()
    {
        $this->setTotalAndProcessed();

        OptionUtil::deleteOptionGroup($this->totalOptionKey, 'jobs');
        OptionUtil::deleteOptionGroup($this->processedOptionKey, 'jobs');
    }

    /**
     * Get the total number of items to process.
     *
     * @return int
     */
    public function getTotal()
    {
        if (empty($this->totalOptionKey)) {
            $this->setTotalAndProcessed();
        }

        return (int)OptionUtil::getOptionGroup('jobs', $this->totalOptionKey, 0);
    }

    /**
     * Get the number of processed items.
     *
     * @return int
     */
    public function getProcessed()
    {
        if (empty($this->processedOptionKey)) {
            $this->setTotalAndProcessed();
        }

        return (int)OptionUtil::getOptionGroup('jobs', $this->processedOptionKey, 0);
    }

    /**
     * Build the admin-post URL to trigger this background process from the current admin page.
     *
     * @param bool $force Whether to include the `force` flag to allow restart. Default false.
     * @return string Fully formed admin-post URL, or an empty string when the current page URL is unavailable.
     */
    public function getActionUrl($force = false)
    {
        $currentPage = $this->getCurrentAdminUrl();

        if (empty($currentPage)) {
            return '';
        }

        $args = [
            'action'   => BackgroundProcessFactory::getActionName(),
            'job_key'  => $this->action,
            'nonce'    => BackgroundProcessFactory::getActionNonce(),
            'redirect' => $currentPage,
            'force'    => $force
        ];

        $actionUrl = add_query_arg(
            $args,
            admin_url('admin-post.php')
        );

        return $actionUrl;
    }

    /**
     * Get the current admin page URL.
     *
     * @return string
     */
    private function getCurrentAdminUrl()
    {
        if (!is_admin()) {
            return '';
        }

        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Stop the process completely and clear all related data.
     *
     * @return void
     */
    public function stopProcess()
    {
        $this->cancel();
        $this->delete_all();
        $this->clearTotalAndProcessed();
        $this->clearProcessTimes();
    }

    /**
     * Triggers the background process.
     *
     * @abstract
     * @return bool
     */
    abstract public function process();

    /**
     * Dispatch the background process.
     *
     * Overrides parent to save start time on first dispatch only.
     *
     * @return array|\WP_Error|false
     */
    public function dispatch()
    {
        // Save start time only on first dispatch (when no start time exists)
        if ($this->getStartTime() === 0) {
            $this->saveStartTime();
        }

        return parent::dispatch();
    }

    /**
     * Complete the background process.
     *
     * Overrides parent to save end time.
     */
    protected function complete()
    {
        // Save end time when completing
        $this->saveEndTime();

        parent::complete();
    }
}
