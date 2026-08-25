<?php
/**
 * Full-flow subscription validation fixture for Playwright.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_sms_mobile_number_validity', function ($validity, $mobileNumber) {
    if (preg_replace('/\D+/', '', $mobileNumber) !== '15555554567') {
        return $validity;
    }

    return new WP_Error(
        'wpsms_e2e_subscription_validation',
        'You are currently unsubscribed.<br><br>' .
        '<a href="sms:+15555554567?body=START" target="_blank" rel="noopener" onclick="window.__wpsmsAttack = true" style="color:red">Text START</a>' .
        '<img src="x" onerror="window.__wpsmsAttack = true">' .
        '<script src="data:text/javascript,window.__wpsmsAttack=true"></script>'
    );
}, 10, 2);

add_action('template_redirect', function () {
    if (!isset($_GET['wpsms_e2e_subscription_validation'])) {
        return;
    }

    status_header(200);
    nocache_headers();

    $subscriptionForm = do_shortcode('[wp_sms_subscriber_form]');

    ?><!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <?php wp_head(); ?>
    </head>
    <body>
        <main><?php echo $subscriptionForm; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></main>
        <?php wp_footer(); ?>
    </body>
    </html><?php
    exit;
});
