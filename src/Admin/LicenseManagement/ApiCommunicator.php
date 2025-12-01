<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS\Components\RemoteRequest;

if (!defined('ABSPATH')) exit;

class ApiCommunicator
{
    private $apiUrl = 'https://wp-sms-pro.com' . '/wp-json/wp-license-manager/v1';

    /**
     * Get the list of products (add-ons) from the API and cache it for 1 week.
     *
     * @return array
     * @throws Exception if there is an error with the API call
     */
    public function getProducts()
    {
        try {
            $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/product/list");
            $addons        = $remoteRequest->execute(false, true, WEEK_IN_SECONDS);

            if (empty($addons) || !is_array($addons)) {
                throw new Exception(
                    /* translators: %s: API URL */
                    sprintf(__('No products were found. The API returned an empty response from the following URL: %s', 'wp-sms'), "{$this->apiUrl}/product/list")
                );
            }

        } catch (Exception $e) {
            throw new Exception(
                // translators: %s: Error message.
                sprintf(__('Unable to retrieve product list from the remote server, %s. Please check the remote server connection or your remote work configuration.', 'wp-sms'), $e->getMessage())
            );
        }

        return $addons;
    }
}
