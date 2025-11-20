<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class="wrap wpsms-wrap">

    <?php use WP_SMS\Helper;
    echo(isset($class) ? ' ' . esc_attr($class) : '');
    echo Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
