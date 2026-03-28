<?php

namespace WSms\Messaging\Email;

defined('ABSPATH') || exit;

class EmailHeaderComposer
{
    public function __construct(
        private readonly UnsubscribeTokenService $tokenService,
    ) {
    }

    public function getTokenService(): UnsubscribeTokenService
    {
        return $this->tokenService;
    }

    /**
     * Compose RFC 2369 + RFC 8058 compliant unsubscribe headers for campaign emails.
     *
     * @return string[] Headers in "Name: Value" format
     */
    public function composeHeaders(string $email, ?string $campaignId = null): array
    {
        $url = $this->tokenService->getUnsubscribeUrl($email, $campaignId);

        return [
            'List-Unsubscribe: <' . $url . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];
    }
}
