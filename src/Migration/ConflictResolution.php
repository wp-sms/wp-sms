<?php

namespace WSms\Migration;

defined('ABSPATH') || exit;

enum ConflictResolution: string
{
    case Skip = 'skip';
    case Overwrite = 'overwrite';
    case KeepExisting = 'keep_existing';
}
