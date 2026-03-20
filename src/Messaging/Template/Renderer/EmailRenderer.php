<?php

namespace WSms\Messaging\Template\Renderer;

use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;

defined('ABSPATH') || exit;

class EmailRenderer implements ChannelRendererInterface
{
    public function getChannel(): string
    {
        return 'email';
    }

    public function render(ChannelContent $content, array $context): RenderedMessage
    {
        $siteName = $context['site_name'] ?? '';
        $logoUrl = $context['logo_url'] ?? '';

        $bodyHtml = $this->renderBodyHtml($content->body);

        $ctaHtml = '';
        if (!empty($content->cta) && !empty($content->ctaUrl)) {
            $ctaHtml = $this->renderCtaButton($content->cta, $content->ctaUrl);
        }

        $html = $this->wrapInLayout($bodyHtml, $ctaHtml, $siteName, $logoUrl);

        return new RenderedMessage(
            body: $html,
            subject: $content->subject,
            headers: ['Content-Type: text/html; charset=UTF-8'],
        );
    }

    private function renderBodyHtml(string $body): string
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $body)),
            fn (string $line) => $line !== '',
        );

        $html = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '<')) {
                $html .= $line . "\n";
            } else {
                $html .= '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.5;color:#1a1a1a;">' . $line . '</p>' . "\n";
            }
        }

        return $html;
    }

    private function renderCtaButton(string $label, string $url): string
    {
        return <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px auto;">
<tr>
<td style="border-radius:6px;background-color:#2563eb;">
<a href="{$url}" target="_blank" style="display:inline-block;padding:12px 32px;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">{$label}</a>
</td>
</tr>
</table>
HTML;
    }

    private function wrapInLayout(string $bodyHtml, string $ctaHtml, string $siteName, string $logoUrl): string
    {
        $headerContent = '';
        if (!empty($logoUrl)) {
            $headerContent = '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr($siteName) . '" style="max-height:40px;margin-right:12px;" />';
        }
        if (!empty($siteName)) {
            $headerContent .= '<span style="font-size:18px;font-weight:600;color:#1a1a1a;">' . esc_html($siteName) . '</span>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f3f4f6;">
<tr><td style="padding:32px 16px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin:0 auto;max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="padding:24px 32px;background-color:#f8f9fa;border-bottom:1px solid #e5e7eb;">
{$headerContent}
</td></tr>
<tr><td style="padding:32px;">
{$bodyHtml}
{$ctaHtml}
</td></tr>
<tr><td style="padding:16px 32px;background-color:#f8f9fa;border-top:1px solid #e5e7eb;text-align:center;">
<p style="margin:0;font-size:13px;color:#6b7280;">Sent by {$siteName}</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
