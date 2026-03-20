<?php

namespace WSms\Messaging\Template\ValueObjects;

defined('ABSPATH') || exit;

class ChannelContent
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $subject = null,
        public readonly ?string $cta = null,
        public readonly ?string $ctaUrl = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'body'    => $this->body,
            'subject' => $this->subject,
            'cta'     => $this->cta,
            'cta_url' => $this->ctaUrl,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            body: $data['body'] ?? '',
            subject: $data['subject'] ?? null,
            cta: $data['cta'] ?? null,
            ctaUrl: $data['cta_url'] ?? null,
        );
    }
}
