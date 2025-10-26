<?php

namespace WP_SMS\Services\Database\Migrations\BackgroundProcess;

use WP_SMS\Traits\AjaxUtilityTrait;
use WP_SMS\Components\Ajax;
use WP_SMS\Utils\Request;
use Exception;

class BackgroundProcessActions
{
    use AjaxUtilityTrait;

    /** @var BackgroundProcessManager */
    private $manager;

    public function __construct(BackgroundProcessManager $manager)
    {
        $this->manager = $manager;
    }

    public function register()
    {
        Ajax::register('async_background_process', [$this, 'asyncBackgroundProcess'], false);
    }

    public function asyncBackgroundProcess()
    {
        try {
            $this->verifyAjaxRequest();
            $this->checkAdminReferrer('wp_rest', 'wpsms_nonce');
            $this->checkCapability('manage');

            $currentProcess = Request::get('current_process');

            $currentJob = $this->manager->getBackgroundProcess($currentProcess);

            if (empty($currentProcess)) {
                Ajax::success([
                    'completed' => true,
                ]);
            }

            if (BackgroundProcessFactory::isProcessDone($currentProcess)) {
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
}