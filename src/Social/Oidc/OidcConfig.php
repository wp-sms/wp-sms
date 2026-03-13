<?php

namespace WSms\Social\Oidc;

defined('ABSPATH') || exit;

readonly class OidcConfig
{
    public function __construct(
        public string $id,
        public string $name,
        public string $discoveryUrl,
        public string $clientId,
        public string $clientSecret,
        public array  $scopes = ['openid', 'profile', 'email'],
        public ?string $tokenAuthMethod = null,
        public bool   $isTrustedEmail = false,
        public string $iconSvg = '',
    ) {
    }
}
