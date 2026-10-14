<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once __DIR__ . '/class-wpsms-gateway-smsdk.php';

/**
 * Former slug of the SMS.dk gateway (renamed to `smsdk`). Sites that saved `prosmsdk` as their gateway keep working.
 */
class prosmsdk extends smsdk
{
}
