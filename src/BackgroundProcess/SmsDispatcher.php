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
        $remoteRequestQueue = WPSms()->getRemoteRequestQueue();

        foreach ($this->smsArguments['to'] as $number) {
            $singleArgument       = $this->smsArguments;
            $singleArgument['to'] = $number;

            /**
             * Filter to modify the arguments before dispatching the SMS to a single number.
             * This filter can be used to customize the SMS parameters, such as the recipient's number,
             * message content, or any other related argument.
             *
             * @url https://wp-sms-pro.com/resources/filter-wp_sms_single_dispatch_arguments/
             * @param array $singleArgument The arguments for sending an SMS.
             */
            $singleArgument = apply_filters('wp_sms_single_dispatch_arguments', $singleArgument);

            $remoteRequestQueue->push_to_queue(['parameters' => $singleArgument])->save();
        }

        $remoteRequestQueue->dispatch();

        $this->applyResponseFilter();

        return true;
    }

    private function applyResponseFilter()
    {
        add_filter('wp_sms_send_sms_response', function () {
            return __('SMS delivery is in progress as a background task; please review the Outbox for updates.', 'wp-sms');
        });
    }
}