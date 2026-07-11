<?php
/**
 * SMSPM gateway for WP-SMS (WSMS).
 *
 * @package WP_SMS\Gateway
 *
 * SMSPM (https://smspm.com) is a pay-per-SMS platform with carrier-specific
 * pricing for the Baltic/EU market. Auth is a hash+token pair (not the more
 * common username+password), so this gateway maps:
 *   - SMSPM "hash"  -> WP-SMS's generic API Key field ($this->has_key)
 *   - SMSPM "token" -> WP-SMS's generic Password field ($this->password),
 *                      falling back to Username ($this->username) so it
 *                      still works if a site owner pastes it into either box
 *
 * API reference: https://app.smspm.com/docs
 *   POST https://api.smspm.com                                  (send)
 *   GET  https://api.smspm.com/balance?hash=...&token=...        (credit)
 *   GET  https://api.smspm.com/sender-ids?hash=...&token=...     (senders)
 *
 * Modeled on class-wpsms-gateway-smshosting.php and
 * class-wpsms-gateway-sms77.php (verified against the live wp-sms/wp-sms
 * repo on 11 July 2026) — same wp_remote_post()/wp_remote_get() usage,
 * same $this->log()/do_action('wp_sms_send', ...) contract, no custom
 * gatewayFields array (WP-SMS's base Gateway class auto-populates
 * has_key/username/password/from from its generic settings fields; no
 * real gateway in the repo declares a custom gatewayFields property).
 */

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;

class smspm extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.smspm.com";

    public $tariff    = "https://smspm.com/pricing";
    public $unitrial  = false;
    public $unit;
    public $flash     = "disable";
    public $isflash   = false;
    public $bulk_send = true; // SMSPM accepts up to 100 recipients per request

    public function __construct()
    {
        parent::__construct();

        $this->has_key = true; // tells WP-SMS to render the API Key field; holds the real hash value once settings are loaded

        $this->validateNumber = "International format without a leading + (e.g. 37256789045)";

        $this->help = sprintf(
            /* translators: %1$s: URL to the SMSPM API credentials page, %2$s: URL to the SMSPM sender request page */
            __('Paste your SMSPM API hash into the API Key field above, and your API token into the Password field. Find both at <a href="%1$s" target="_blank" rel="noopener">SMSPM App &rarr; API</a>. The Sender Number field must be one of your pre-approved Sender IDs — request or view yours at <a href="%2$s" target="_blank" rel="noopener">SMSPM App &rarr; SMS &rarr; Sender request</a>.', 'wp-sms'),
            'https://app.smspm.com/app/api',
            'https://app.smspm.com/app/sms'
        );
    }

    /**
     * Resolves the SMSPM API token from whichever generic credential field
     * the site owner used. We document "Password" as the intended field
     * (see $this->help above), but fall back to Username so a token pasted
     * into the wrong box still works rather than silently failing.
     */
    private function getToken()
    {
        return $this->password ?: $this->username;
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        if (empty($this->has_key)) {
            return new WP_Error('send-sms', __('SMSPM API hash is not set.', 'wp-sms'));
        }

        $token = $this->getToken();
        if (empty($token)) {
            return new WP_Error('send-sms', __('SMSPM API token is not set.', 'wp-sms'));
        }

        if (empty($this->from)) {
            return new WP_Error('send-sms', __('SMSPM Sender ID is not set.', 'wp-sms'));
        }

        if (empty($this->to) || empty($this->msg)) {
            return new WP_Error('send-sms', __('Recipient and message are required.', 'wp-sms'));
        }

        // SMSPM's /v2 send endpoint accepts a single toNumber or an array of
        // up to 100. WP-SMS's $this->to is already an array by this point.
        $toNumber = count($this->to) === 1 ? $this->to[0] : array_values($this->to);

        $response = wp_remote_post($this->wsdl_link, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'hash'       => $this->has_key,
                'token'      => $token,
                'fromNumber' => $this->from,
                'toNumber'   => $toNumber,
                'text'       => $this->msg,
            ]),
        ]);

        if (is_wp_error($response)) {
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return new WP_Error('send-sms', $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        // SMSPM error responses are shaped { "error": "..." } (see
        // https://app.smspm.com/docs) regardless of HTTP status.
        if (!is_array($body) || isset($body['error'])) {
            $errorMessage = is_array($body) && isset($body['error'])
                ? $body['error']
                : sprintf(__('Unexpected response from SMSPM (HTTP %d).', 'wp-sms'), $status);

            $this->log($this->from, $this->msg, $this->to, $errorMessage, 'error');
            return new WP_Error('send-sms', $errorMessage);
        }

        // Success shape: { "messages": [ { "id", "toNumber", "status" }, ... ] }.
        // Individual recipients can still fail validation (e.g. a malformed
        // number) inside an otherwise-200 batch response — surface that as a
        // WP_Error only when EVERY recipient failed, so a partial failure in
        // a bulk send doesn't mask the successful ones.
        $messages     = $body['messages'] ?? [];
        $queuedCount  = count(array_filter($messages, function ($m) {
            return isset($m['status']) && stripos($m['status'], 'Added to queue') !== false;
        }));

        if (!empty($messages) && $queuedCount === 0) {
            $firstError = $messages[0]['error'] ?? __('SMSPM rejected all recipients in this batch.', 'wp-sms');
            $this->log($this->from, $this->msg, $this->to, $firstError, 'error');
            return new WP_Error('send-sms', $firstError);
        }

        $this->log($this->from, $this->msg, $this->to, $body);

        /**
         * Run hook after send sms.
         *
         * @param array $body result output.
         * @since 2.4
         */
        do_action('wp_sms_send', $body);

        return $body;
    }

    public function GetCredit()
    {
        $token = $this->getToken();

        if (empty($this->has_key) || empty($token)) {
            return new WP_Error('account-credit', __('SMSPM API hash and token are required to check balance.', 'wp-sms'));
        }

        $response = wp_remote_get(add_query_arg([
            'hash'  => rawurlencode($this->has_key),
            'token' => rawurlencode($token),
        ], $this->wsdl_link . '/balance'), [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('account-credit', $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || isset($body['error'])) {
            $errorMessage = is_array($body) && isset($body['error'])
                ? $body['error']
                : sprintf(__('Unexpected response from SMSPM /balance (HTTP %d).', 'wp-sms'), $status);
            return new WP_Error('account-credit', $errorMessage);
        }

        if (!isset($body['balance'])) {
            return new WP_Error('account-credit', __('SMSPM /balance response did not include a balance.', 'wp-sms'));
        }

        // If SMSPM's balance service is briefly unavailable, /balance still
        // returns the last-known balance with "stale": true rather than
        // failing outright (see SMSPM API docs, "Account Balance" section).
        // We surface the same float either way — WP-SMS's credit display
        // has no concept of "stale", and a slightly-behind number here is
        // still more useful than an error.
        return (float) $body['balance'];
    }

    /**
     * Fetches the Sender IDs approved for this SMSPM account. Not called by
     * WP-SMS core today — the Sender Number field on this gateway is
     * free-text, matching how most other WP-SMS gateways work, since the
     * base Gateway class's field renderer doesn't currently support a
     * select-type gatewayField (see PR description / follow-up issue).
     * Kept here so a future WP-SMS core change (or a small filter-based
     * admin JS enhancement) can call it to populate a dropdown without
     * another round of gateway-file changes.
     *
     * @return string[] Approved Sender IDs, or an empty array on any error.
     */
    public function getSenders()
    {
        $token = $this->getToken();
        if (empty($this->has_key) || empty($token)) {
            return [];
        }

        $response = wp_remote_get(add_query_arg([
            'hash'  => rawurlencode($this->has_key),
            'token' => rawurlencode($token),
        ], $this->wsdl_link . '/sender-ids'), [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['senders']) || !is_array($body['senders'])) {
            return [];
        }

        return $body['senders'];
    }
}
