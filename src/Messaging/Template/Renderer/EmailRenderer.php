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
                $html .= '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#374151;">' . $line . '</p>' . "\n";
            }
        }

        return $html;
    }

    private function renderCtaButton(string $label, string $url): string
    {
        return <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 0 0;">
<tr>
<td style="border-radius:8px;background-color:#1a1a1a;">
<a href="{$url}" target="_blank" style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">{$label}</a>
</td>
</tr>
</table>
HTML;
    }

    private function wrapInLayout(string $bodyHtml, string $ctaHtml, string $siteName, string $logoUrl): string
    {
        $headerCells = '';
        if (!empty($logoUrl)) {
            $headerCells .= '<td style="vertical-align:middle;padding-right:10px;">'
                . '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr($siteName) . '" width="28" height="28" style="width:28px;height:28px;border-radius:6px;display:block;" />'
                . '</td>';
        }
        if (!empty($siteName)) {
            $headerCells .= '<td style="vertical-align:middle;">'
                . '<span style="font-size:15px;font-weight:600;color:#1a1a1a;letter-spacing:-0.01em;">' . esc_html($siteName) . '</span>'
                . '</td>';
        }

        $preheader = esc_html($siteName);
        $wsmsLogoUrl = esc_url(WP_SMS_URL . 'public/images/icon-128x128.png');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f3f4f6;">
<tr><td style="padding:40px 16px;">

<div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{$preheader}</div>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="560" style="margin:0 auto;max-width:560px;background-color:#ffffff;border-radius:12px;">

<tr><td style="padding:28px 36px 24px 36px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
{$headerCells}
</tr></table>
</td></tr>

<tr><td style="padding:0 36px 32px 36px;">
{$bodyHtml}
{$ctaHtml}
</td></tr>

<tr><td style="padding:20px 36px;border-top:1px solid #f0f0f0;">
<p style="margin:0;font-size:12px;line-height:1.5;color:#9ca3af;">If you didn't request this, you can safely ignore this email.</p>
</td></tr>

</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="560" style="margin:16px auto 0 auto;max-width:560px;">
<tr><td style="text-align:center;">
<p style="margin:0;">
<img src="{$wsmsLogoUrl}" width="12" height="12" alt="WSMS" style="vertical-align:middle;display:inline-block;border-radius:2px;" />
<span style="font-size:11px;color:#b0b0b0;vertical-align:middle;padding-left:3px;">Powered by WSMS</span>
</p>
</td></tr>
</table>

</td></tr>
</table>
</body>
</html>
HTML;
    }
}
