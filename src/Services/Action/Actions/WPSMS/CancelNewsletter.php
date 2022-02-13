<?php

namespace WPSmsTwoWay\Services\Action\Actions\WPSMS;

use WPSmsTwoWay\Services\Action\Actions\AbstractAction;

class CancelNewsletter extends AbstractAction
{
    /**
     * @var string
     */
    protected $description = 'Cancel SMS Newsletter';

    /**
     * @var array
     */
    protected $responseParams;

    /**
     * Action's callback
     *
     * @param WPSmsTwoWay\Models\IncomingMessage $message
     * @return void
     */
    protected function callback($message)
    {
        $senderNumber = $message->sender_number;
        $result = Newsletter::deleteSubscriberByNumber($senderNumber);

        if ($result['result'] == 'error') {
            throw new ActionException($result['message']);
        }
    }
}
