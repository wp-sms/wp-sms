<?php

namespace WSms\Enums;

defined('ABSPATH') || exit;

enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Waiting = 'waiting';
    case Cancelled = 'cancelled';
}
