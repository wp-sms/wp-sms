<?php

namespace WP_SMS\BackgroundProcess;

use WP_SMS\Option;
use WP_SMS\Utils\Sms;

class SmsDispatcher
{
    private array $smsArguments;

    /**
     * @param $to
     * @param $msg
     * @param $is_flash
     * @param $from
     * @param $mediaUrls
     */
    public function __construct($to, $msg, $is_flash = false, $from = null, $mediaUrls = [])
    {
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
        $requestType = Option::getOption('sms_delivery_method');

        if ($requestType == 'api_async_send') {

            return WPSms()
                ->getRemoteRequestAsync()
                ->data(['parameters' => $this->smsArguments])
                ->dispatch();

        } elseif ($requestType == 'api_queued_send' or count($this->smsArguments['to']) >= 20) {

            foreach ($this->smsArguments['to'] as $number) {

                $this->smsArguments['to'] = $number;

                WPSms()->getRemoteRequestQueue()
                    ->push_to_queue(['parameters' => $this->smsArguments])
                    ->save()
                    ->dispatch();
            }

            add_filter('wp_sms_send_sms_response', function () {
                return __('SMS delivery is in progress as a background task; please review the Outbox for updates.', 'wp-sms');
            });

            return true;

        } else {
            return Sms::send($this->smsArguments);
        }
    }
}