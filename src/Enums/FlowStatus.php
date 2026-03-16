<?php

namespace WSms\Enums;

defined('ABSPATH') || exit;

enum FlowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
