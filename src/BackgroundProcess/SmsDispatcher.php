<?php

namespace WP_SMS\BackgroundProcess;

use WP_SMS\Components\Sms;
use WP_SMS\Option;

/**
 * Class SmsDispatcher
 *
 * This class is responsible for dispatching SMS messages based on the configured method.
 */
class SmsDispatcher
{
    private $smsArguments;

    public function __construct($to, $msg, $is_flash = false, $from = null, $mediaUrls = [])
    {
        // Backward compatibility
        if (!is_array($to)) {
            $to = array($to);
        }

        $this->smsArguments = [
            'to'        => $to,
            'msg'       => $msg,
            'is_flash'  => $is_flash,
            'from'      => $from,
            'mediaUrls' => $mediaUrls,
        ];
    }

    public function dispatch()
    {
        $requestType       = Option::getOption('sms_delivery_method');
        $bulkDispatchLimit = apply_filters('wp_sms_bulk_dispatch_limit', 20);

        if ($requestType == 'api_queued_send' or count($this->smsArguments['to']) >= $bulkDispatchLimit) {
            return $this->dispatchQueuedSend();
        }

        if ($requestType == 'api_async_send') {
            return $this->dispatchAsyncSend();
        }

        return Sms::send($this->smsArguments);
    }

    private function dispatchAsyncSend()
    {
        return WPSms()->getRemoteRequestAsync()
            ->data(['parameters' => $this->smsArguments])
            ->dispatch();
    }

    private function dispatchQueuedSend()
    {
        foreach ($this->smsArguments['to'] as $number) {
            $this->sendToSingleNumber($number);
        }

        $this->applyResponseFilter();

        return true;
    }

    private function sendToSingleNumber($number)
    {
        $singleArgument       = $this->smsArguments;
        $singleArgument['to'] = $number;

        WPSms()->getRemoteRequestQueue()
            ->push_to_queue(['parameters' => $singleArgument])
            ->save()
            ->dispatch();
    }

    private function applyResponseFilter()
    {
        add_filter('wp_sms_send_sms_response', function () {
            return __('SMS delivery is in progress as a background task; please review the Outbox for updates.', 'wp-sms');
        });
    }
}