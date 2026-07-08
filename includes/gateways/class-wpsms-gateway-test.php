<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Test / mock gateway.
 *
 * Does not talk to any real provider. Instead it can either fabricate a
 * randomized success response (JSON mode) or append each message to a local
 * log file (File mode). Useful for local development, demos, offline work and
 * CI, where you want to exercise the SMS pipeline without spending real credit.
 *
 * The output is chosen with the "Output Type" setting:
 *  - json (default) — build a fake response array and log it to the Outbox.
 *                     This is the historical behaviour of this gateway.
 *  - file          — append one line per message to a configurable log file.
 */
class test extends \WP_SMS\Gateway
{
    private $wsdl_link = '';
    public $tariff = '';
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $options;

    /**
     * Output mode: 'json' (fake response) or 'file' (write to log file).
     * Populated from the gateway settings by Gateway::initial().
     *
     * @var string
     */
    public $output_type = 'json';

    /**
     * Absolute path of the file messages are written to in file mode.
     * Populated from the gateway settings by Gateway::initial().
     *
     * @var string
     */
    public $log_file = '';

    public function __construct()
    {
        parent::__construct();
        $this->help            = "";
        $this->validateNumber  = "09xxxxxxxx";
        $this->has_key         = true;
        $this->bulk_send       = true;
        $this->supportMedia    = true;
        $this->supportIncoming = true;
        $this->gatewayFields   = [
            'from'        => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your sender ID',
            ],
            'output_type' => [
                'id'      => 'gateway_output_type',
                'name'    => esc_html__('Output Type', 'wp-sms'),
                'desc'    => esc_html__('How "sent" messages are handled. JSON fabricates a fake response (no send). File appends each message to a log file.', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'json' => 'JSON (fake response)',
                    'file' => 'File (write to log)',
                ],
            ],
            'log_file'    => [
                'id'        => 'gateway_test_log_file',
                'name'      => esc_html__('Log File Path', 'wp-sms'),
                'desc'      => esc_html__('Absolute path where messages are written in File mode. Leave empty to use wp-content/uploads/wp-sms-mock.log.', 'wp-sms'),
                'type'      => 'text',
                // Only show this field when Output Type is set to "file".
                'className' => 'js-wpsms-show_if_gateway_output_type_equal_file',
            ],
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            // Anything other than an explicit "file" falls back to json, so an
            // empty/unset option keeps the historical behaviour.
            $response = ($this->output_type === 'file')
                ? $this->writeToFile()
                : $this->fakeResponse();

            if (is_wp_error($response)) {
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error', $this->media);

                return $response;
            }

            //log the result
            $this->log($this->from, $this->msg, $this->to, $response, 'success', $this->media);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (\Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error', $this->media);

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * JSON mode: build a randomized fake response (no real send).
     *
     * @return array
     */
    private function fakeResponse()
    {
        $params = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode([
                'action'    => 'send-sms',
                'sender_id' => $this->from,
                'recipient' => implode(',', $this->to),
                'message'   => $this->msg
            ])
        ];

        // generate a randomized fake response
        return [
            'success'    => true,
            'status'     => 'sent',
            'message_id' => uniqid('sms_'),
            'from'       => $this->from,
            'to'         => $this->to,
            'recipients' => is_array($this->to) ? count($this->to) : 1,
            'message'    => $this->msg,
            'flash'      => $this->isflash,
            'media'      => $this->media ?: null,
            'cost'       => sprintf('%.2f USD', wp_rand(5, 500) / 100),
            'sent_at'    => current_time('mysql'),
            'error'      => null,
            'raw'        => [
                'params'   => $params,
                'debug_id' => wp_rand(100000, 999999),
            ],
        ];
    }

    /**
     * File mode: append one line per message to the log file.
     *
     * @return string|\WP_Error Human-readable result string, or WP_Error on write failure.
     */
    private function writeToFile()
    {
        $recipients = is_array($this->to) ? implode(', ', $this->to) : $this->to;

        $line = sprintf(
            "[%s] FROM: %s | TO: %s | MSG: %s%s",
            current_time('mysql'),
            $this->from !== '' ? $this->from : '(none)',
            $recipients,
            str_replace(["\r", "\n"], ' ', (string) $this->msg),
            PHP_EOL
        );

        $file    = $this->getLogFilePath();
        $written = @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            // translators: %s: log file path
            return new \WP_Error('send-sms', sprintf(esc_html__('Test gateway: could not write to log file: %s', 'wp-sms'), $file));
        }

        // translators: %s: log file path
        return sprintf(esc_html__('Message written to %s', 'wp-sms'), $file);
    }

    /**
     * Resolve the target log file, falling back to wp-content/uploads, and
     * make sure its parent directory exists.
     *
     * @return string
     */
    private function getLogFilePath()
    {
        $path = trim((string) $this->log_file);

        if ($path === '') {
            $uploads = wp_upload_dir();
            $path    = trailingslashit($uploads['basedir']) . 'wp-sms-mock.log';
        }

        // e.g. uploads/ may not exist before any media has been added.
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $path;
    }

    public function GetCredit()
    {
        return '143 USD';
    }
}
