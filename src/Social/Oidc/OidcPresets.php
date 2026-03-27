<?php

namespace WSms\Social\Oidc;

defined('ABSPATH') || exit;

class OidcPresets
{
    public static function telegram(string $clientId, string $clientSecret): OidcConfig
    {
        return new OidcConfig(
            id: 'telegram',
            name: 'Telegram',
            discoveryUrl: 'https://oauth.telegram.org/.well-known/openid-configuration',
            clientId: $clientId,
            clientSecret: $clientSecret,
            scopes: ['openid', 'profile', 'phone', 'telegram:bot_access'],
            tokenAuthMethod: 'basic',
            isTrustedEmail: false,
            iconSvg: '<svg viewBox="0 0 24 24" width="16" height="16" fill="#26A5E4"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.504-1.36 8.629-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
        );
    }

    public static function line(string $clientId, string $clientSecret): OidcConfig
    {
        return new OidcConfig(
            id: 'line',
            name: 'LINE',
            discoveryUrl: 'https://access.line.me/.well-known/openid-configuration',
            clientId: $clientId,
            clientSecret: $clientSecret,
            scopes: ['openid', 'profile', 'email'],
            tokenAuthMethod: 'post',
            isTrustedEmail: false,
            iconSvg: '<svg viewBox="0 0 24 24" width="16" height="16" fill="#06C755"><path d="M12 2C6.48 2 2 5.82 2 10.5c0 4.21 3.74 7.74 8.79 8.41.34.07.81.23.93.52.1.26.07.68.03.94l-.15.93c-.04.27-.21 1.05.92.57s6.13-3.61 8.36-6.18C22.72 13.57 22 12.11 22 10.5 22 5.82 17.52 2 12 2zm-3.07 11.06H6.87a.53.53 0 0 1-.53-.53V8.29a.53.53 0 0 1 1.06 0v3.71h1.53a.53.53 0 0 1 0 1.06zm1.61-.53a.53.53 0 0 1-1.06 0V8.29a.53.53 0 0 1 1.06 0v4.24zm4.42 0a.53.53 0 0 1-.38.51.52.52 0 0 1-.57-.18l-2.17-2.96v2.63a.53.53 0 0 1-1.06 0V8.29a.53.53 0 0 1 .38-.51.53.53 0 0 1 .57.18l2.17 2.96V8.29a.53.53 0 0 1 1.06 0v4.24zm2.9-3.18a.53.53 0 0 1 0 1.06h-1.53v.77h1.53a.53.53 0 0 1 0 1.06h-2.06a.53.53 0 0 1-.53-.53V8.29a.53.53 0 0 1 .53-.53h2.06a.53.53 0 0 1 0 1.06h-1.53v.53h1.53z"/></svg>',
        );
    }
}
