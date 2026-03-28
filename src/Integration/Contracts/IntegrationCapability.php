<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

final class IntegrationCapability
{
    public const CONTACT_SYNC        = 'contact_sync';
    public const LIST_MANAGEMENT     = 'list_management';
    public const CAMPAIGNS           = 'campaigns';
    public const AUTOMATIONS         = 'automations';
    public const TRANSACTIONAL_EMAIL = 'transactional_email';
    public const SUPPRESSION_SYNC    = 'suppression_sync';
    public const ENGAGEMENT_DATA     = 'engagement_data';
    public const TAGS                = 'tags';
    public const WEBHOOKS            = 'webhooks';
    public const EMAIL_GATEWAY       = 'email_gateway';
    public const CONTACT_IMPORT      = 'contact_import';

    public const LABELS = [
        self::CONTACT_SYNC        => 'Contact Sync',
        self::LIST_MANAGEMENT     => 'List Management',
        self::CAMPAIGNS           => 'Campaigns',
        self::AUTOMATIONS         => 'Automations',
        self::TRANSACTIONAL_EMAIL => 'Transactional Email',
        self::SUPPRESSION_SYNC    => 'Suppression Sync',
        self::ENGAGEMENT_DATA     => 'Engagement Data',
        self::TAGS                => 'Tags',
        self::WEBHOOKS            => 'Webhooks',
        self::EMAIL_GATEWAY       => 'Email Gateway',
        self::CONTACT_IMPORT      => 'Contact Import',
    ];
}
