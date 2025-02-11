<?php

namespace WP_SMS\Admin\LicenseManagement\Views;

use Exception;
use WP_SMS\Admin\LicenseManagement\Abstracts\BaseTabView;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\LicenseManagerDataProvider;
use WP_SMS\Admin\NoticeHandler\Notice;
use WP_SMS\Components\View;
use WP_SMS\Exceptions\SystemErrorException;
use WP_SMS\Utils\AdminHelper;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Utils\Request;

class TabsView extends BaseTabView
{
    protected $defaultTab = 'add-ons';
    protected $tabs = [
        'add-ons',
        'add-license',
        'downloads',
        'get-started',
    ];

    private $apiCommunicator;

    public function __construct()
    {
        $this->dataProvider    = new LicenseManagerDataProvider();
        $this->apiCommunicator = new ApiCommunicator();
        $this->checkUserAccess();
        $this->handleUrlLicenseValidation();
        $this->checkLicensesStatus();

        parent::__construct();
    }

    /**
     * Prevent access to certain tabs if the user is not an admin.
     *
     * @throws SystemErrorException if the user does not have permission to access the page.
     */
    private function checkUserAccess()
    {
        if (!is_main_site() && !$this->isTab('add-ons')) {
            throw new SystemErrorException(esc_html__('You do not have permission to access this page.', 'wp-sms'));
        }
    }

    /**
     * Checks the licenses status if the current tab is 'add-ons'.
     */
    private function checkLicensesStatus()
    {
        if ($this->isTab('add-ons')) {
            LicenseHelper::checkLicensesStatus();
        }
    }

    /**
     * Validate the license key sent via URL
     *
     * @return void
     */
    private function handleUrlLicenseValidation()
    {
        $license = Request::get('license_key');

        if (!empty($license)) {
            $this->apiCommunicator->validateLicense($license);
        }
    }

    /**
     * Returns the current tab to be displayed.
     *
     * @return string
     */
    protected function getCurrentTab()
    {
        $currentTab = Request::get('tab', $this->defaultTab);

        // If license key is sent via URL, redirect to "Downloads" tab
        if (in_array($currentTab, ['add-ons', 'add-license']) && Request::has('license_key')) {
            return 'downloads';
        }

        // If license key has not been found, prevent accessing certain tabs
        if (in_array($currentTab, ['downloads', 'get-started']) && !Request::has('license_key')) {
            return 'add-license';
        }

        return $currentTab;
    }

    /**
     * Returns data for "Add-Ons" tab.
     *
     * @return array
     */
    public function getAddOnsData()
    {
        return $this->dataProvider->getAddOnsData();
    }

    /**
     * Returns data for "Download Add-ons" tab.
     *
     * @return array
     */
    public function getDownloadsData()
    {
        return $this->dataProvider->getDownloadsData();
    }

    /**
     * Returns data for "Get Started" tab.
     *
     * @return array
     */
    public function getGetStartedData()
    {
        return $this->dataProvider->getGetStartedData();
    }

    public function render()
    {
        try {
            $currentTab = $this->getCurrentTab();
            $data       = $this->getTabData();
            $urlParams  = [];

            if (Request::get('license_key')) {
                $urlParams['license_key'] = Request::get('license_key');
            }

            $args = [
                'title'      => esc_html__('License Manager', 'wp-sms'),
                'pageName'   => MenuUtil::getPageSlug('plugins'),
                'custom_get' => ['tab' => $currentTab],
                'data'       => $data,
                'tabs'       => [
                    [
                        'link'  => MenuUtil::getAdminUrl('plugins', ['tab' => 'add-ons']),
                        'title' => esc_html__('Add-Ons', 'wp-sms'),
                        'class' => $this->isTab('add-ons') ? 'current' : '',
                    ],
                    [
                        'link'  => MenuUtil::getAdminUrl('plugins', ['tab' => 'add-license']),
                        'title' => esc_html__('Add Your License', 'wp-sms'),
                        'class' => $this->isTab('add-license') ? 'current' : '',
                    ],
                    [
                        'link'  => MenuUtil::getAdminUrl('plugins', array_merge(['tab' => 'downloads'], $urlParams)),
                        'title' => esc_html__('Download Add-Ons', 'wp-sms'),
                        'class' => $this->isTab('downloads') ? 'current' : '',
                    ],
                    [
                        'link'  => MenuUtil::getAdminUrl('plugins', array_merge(['tab' => 'get-started'], $urlParams)),
                        'title' => esc_html__('Get Started', 'wp-sms'),
                        'class' => $this->isTab('get-started') ? 'current' : '',
                    ],
                ]
            ];

            if ($this->isTab('add-ons')) {
                $args['title']                  = esc_html__('Add-Ons', 'wp-sms');

                if (is_main_site()) {
                    $args['install_addon_btn_txt']  = esc_html__('Install Add-On', 'wp-sms');
                    $args['install_addon_btn_link'] = esc_url(MenuUtil::getAdminUrl('plugins', ['tab' => 'add-license']));
                }

                AdminHelper::getTemplate(['layout/header', 'layout/title'], $args);
            } else {
                AdminHelper::getTemplate(['layout/header', 'layout/addon-header-steps'], $args);
            }

            View::load("pages/license-manager/$currentTab", $args);
            AdminHelper::getTemplate(['layout/postbox.hide', 'layout/footer'], $args);
        } catch (Exception $e) {
            Notice::renderNotice($e->getMessage(), $e->getCode(), 'error');
        }
    }
}
