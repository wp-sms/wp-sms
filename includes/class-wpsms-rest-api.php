<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class RestApi
{
    protected $sms;
    protected $option;
    protected $db;
    protected $tb_prefix;
    protected $namespace;
    protected $options;

    public function __construct()
    {
        global $sms, $wpdb;

        $this->sms       = $sms;
        $this->options   = Option::getOptions();
        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->namespace = 'wpsms';
    }

    /**
     * Handle Response
     *
     * @param $message
     * @param int $status
     * @param array $data
     * @return \WP_REST_Response
     */
    public static function response($message, $status = 200, $data = [])
    {
        if ($status == 200) {
            $output = array(
                'message' => $message,
                'error'   => array(),
                'data'    => $data
            );
        } else {
            $output = array(
                'error' => array(
                    'code'    => $status,
                    'message' => $message
                ),
            );
        }

        return new \WP_REST_Response($output, $status);
    }
}

new RestApi();
