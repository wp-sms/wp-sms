<?php
/**
 * Full-flow structured subscription-action fixture for Playwright.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_sms_mobile_number_validity', function ($validity, $mobileNumber) {
    $digits = preg_replace('/\D+/', '', $mobileNumber);

    if ($digits === '15555554567') {
        return new WP_Error(
            'wpsms_e2e_structured_action',
            '<script>window.__wpsmsAttack = true</script>You are currently unsubscribed.',
            array(
                'actions' => array(
                    array(
                        'label'   => '<strong>Text START</strong>',
                        'href'    => 'sms:+155****4567?body=START',
                        'type'    => 'sms',
                        'target'  => '_blank',
                        'rel'     => 'nofollow external',
                        'onclick' => 'window.__wpsmsAttack = true',
                        'onerror' => 'window.__wpsmsAttack = true',
                        'style'   => 'color:red',
                        'script'  => '<script>window.__wpsmsAttack = true</script>',
                        'img'     => '<img src="x" onerror="window.__wpsmsAttack = true">',
                    ),
                    array(
                        'label' => 'Unsafe action',
                        'href'  => 'javascript:window.__wpsmsAttack = true',
                    ),
                ),
            )
        );
    }

    if ($digits === '15555554568') {
        return new WP_Error(
            'wpsms_e2e_plain_error',
            '<img src="x" onerror="window.__wpsmsAttack = true">Plain validation error'
        );
    }

    return $validity;
}, 10, 2);

add_action('template_redirect', function () {
    if (!isset($_GET['wpsms_e2e_structured_actions'])) {
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
