<?php

namespace WP_SMS\Admin\LicenseManagement\Abstracts;

use Exception;
use WP_SMS\Exceptions\SystemErrorException;
use WP_SMS\Notice\NoticeManager;
use WP_SMS\Utils\Request;

abstract class MultiViewPage extends BasePage
{
    protected $defaultView;
    protected $views;

    protected function getViews()
    {
        return apply_filters('wp_sms_' . str_replace('-', '_', $this->pageSlug) . '_views', $this->views);
    }

    protected function getCurrentView()
    {
        $views       = $this->getViews();
        $currentView = $this->defaultView;
        $pageType    = Request::get('type', false);

        if ($pageType && array_key_exists($pageType, $views)) {
            $currentView = $pageType;
        }

        return $currentView;
    }

    public function view()
    {
        try {
            // Get all views
            $views = $this->getViews();

            // Get current view
            $currentView = $this->getCurrentView();

            // Check if the view does not exist, throw exception
            if (!isset($views[$currentView])) {
                throw new SystemErrorException(
                    esc_html__('View is not valid.', 'wp-sms')
                );
            }
            // Check if the class does not have render method, throw exception
            if (!method_exists($views[$currentView], 'render')) {
                throw new SystemErrorException(
                    sprintf(esc_html__('render method is not defined within %s class.', 'wp-sms'), $currentView)
                );
            }

            // Instantiate the view class and render content
            $view = new $views[$currentView];
            $view->render();
        } catch (Exception $e) {
            $noticeManager = NoticeManager::getInstance();

            $noticeManager->registerNotice(
                'wp_sms_license_manager_exception',
                $e->getMessage(),
                false,
                false
            );

            $noticeManager->displayStaticNotices();
        }
    }


}