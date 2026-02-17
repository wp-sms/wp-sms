<?php
/**
 * Minimal header stub for backward compatibility.
 *
 * Old add-on versions (wp-sms-two-way, wp-sms-woocommerce-pro) reference this
 * template via Helper::loadTemplate('header.php') in their CPT managers.
 * Their inline JS expects a .wpsms-header-banner element to exist.
 *
 * The full legacy header has been replaced by the React dashboard.
 */

if (!defined('ABSPATH')) exit;
?>
<div class="wpsms-header-banner">
    <div class="wpsms-header-logo"></div>
</div>
