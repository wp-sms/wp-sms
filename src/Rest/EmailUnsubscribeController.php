<?php

namespace WSms\Rest;

use WSms\Auth\RateLimiter;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactOptedOutEvent;
use WSms\Messaging\Email\UnsubscribeTokenService;
use WSms\Support\IpResolver;

defined('ABSPATH') || exit;

class EmailUnsubscribeController extends Controller
{
    private const RATE_LIMIT = 10;
    private const RATE_WINDOW = 60;

    public function __construct(
        private readonly UnsubscribeTokenService $tokenService,
        private readonly ContactRepositoryInterface $contacts,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/email/unsubscribe', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'showConfirmation'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'token' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'processUnsubscribe'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'token' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);
    }

    public function showConfirmation(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $request->get_param('token');
        $payload = $this->tokenService->validate($token);

        if ($payload === null) {
            return $this->htmlResponse($this->renderErrorPage());
        }

        return $this->htmlResponse($this->renderConfirmationPage($token));
    }

    public function processUnsubscribe(\WP_REST_Request $request): \WP_REST_Response
    {
        $rateCheck = $this->rateLimiter->check('email_unsub_' . IpResolver::resolve(), self::RATE_LIMIT, self::RATE_WINDOW);
        if (!$rateCheck['allowed']) {
            return new \WP_REST_Response(['success' => false, 'error' => 'rate_limited'], 429);
        }

        $token = $request->get_param('token');
        $payload = $this->tokenService->validate($token);

        if ($payload === null) {
            // RFC 8058 one-click: always return 200 even on invalid token
            if ($this->isOneClickPost($request)) {
                return new \WP_REST_Response(['success' => true], 200);
            }
            return $this->htmlResponse($this->renderErrorPage());
        }

        $this->processOptOut($payload->email);

        // RFC 8058 one-click POST from mail clients
        if ($this->isOneClickPost($request)) {
            return new \WP_REST_Response(['success' => true], 200);
        }

        // Browser confirm button POST
        return $this->htmlResponse($this->renderSuccessPage());
    }

    private function processOptOut(string $email): void
    {
        $contact = $this->contacts->findByEmail($email);
        if ($contact === null) {
            return; // Silently succeed (anti-enumeration)
        }

        $this->contacts->setChannelOptOut($contact['id'], 'email');

        $this->eventDispatcher->dispatch(new ContactOptedOutEvent(
            contactIds: [$contact['id']],
            phone: $contact['phone'] ?? '',
            source: 'email_unsubscribe',
            channel: 'email',
        ));
    }

    private function isOneClickPost(\WP_REST_Request $request): bool
    {
        return $request->get_param('List-Unsubscribe') === 'One-Click';
    }

    /**
     * @return never
     */
    private function htmlResponse(string $html): \WP_REST_Response
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    private function renderConfirmationPage(string $token): string
    {
        $siteName = esc_html(get_bloginfo('name'));
        $actionUrl = esc_url(rest_url('wsms/v1/email/unsubscribe') . '?token=' . urlencode($token));

        $body = '<h1>Unsubscribe from ' . $siteName . '</h1>'
            . '<p>Click the button below to confirm you want to unsubscribe from our emails.</p>'
            . '<form method="POST" action="' . $actionUrl . '"><button type="submit">Confirm Unsubscribe</button></form>';

        return $this->renderPage("Unsubscribe - {$siteName}", $body, extraCss: 'button{background:#1a1a1a;color:#fff;border:none;padding:12px 32px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}button:hover{background:#333}');
    }

    private function renderSuccessPage(): string
    {
        $siteName = esc_html(get_bloginfo('name'));

        $body = '<h1 style="color:#059669">You\'ve been unsubscribed</h1>'
            . '<p>You will no longer receive marketing emails from ' . $siteName . '. You may still receive transactional emails (e.g. password resets).</p>';

        return $this->renderPage("Unsubscribed - {$siteName}", $body);
    }

    private function renderErrorPage(): string
    {
        $siteName = esc_html(get_bloginfo('name'));

        $body = '<h1 style="color:#dc2626">Link expired or invalid</h1>'
            . '<p>This unsubscribe link is no longer valid. It may have expired. Please contact us if you need help unsubscribing.</p>';

        return $this->renderPage("Invalid Link - {$siteName}", $body);
    }

    private function renderPage(string $title, string $body, string $extraCss = ''): string
    {
        $css = 'body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f3f4f6;color:#374151}.card{background:#fff;border-radius:12px;padding:40px;max-width:420px;width:90%;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.1)}h1{font-size:20px;margin:0 0 12px}p{color:#6b7280;margin:0 0 24px;line-height:1.5}' . $extraCss;

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $title . '</title><style>' . $css . '</style></head><body><div class="card">' . $body . '</div></body></html>';
    }
}
