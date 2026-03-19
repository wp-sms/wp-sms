<?php

namespace WSms\Social\Oidc;

defined('ABSPATH') || exit;

class OidcConfig
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $discoveryUrl,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly array  $scopes = ['openid', 'profile', 'email'],
        public readonly ?string $tokenAuthMethod = null,
        public readonly bool   $isTrustedEmail = false,
        public readonly string $iconSvg = '',
    ) {
    }
}
