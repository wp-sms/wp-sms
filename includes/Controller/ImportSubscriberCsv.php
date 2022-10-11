<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Utils\CsvHelper;

class ImportSubscriberCsv extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_import_subscriber';

    protected function run()
    {
        try {

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), $e->getCode());
        }
    }
}