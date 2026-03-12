<?php
defined('ABSPATH') || exit;

$pageTitle = apply_filters('wsms_auth_page_title', $wsmsPageTitle ?? 'Account');
$siteName  = get_bloginfo('name');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($pageTitle . ' — ' . $siteName); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('wsms-auth-page'); ?> style="margin:0;padding:0;min-height:100vh;">
    <div id="wsms-auth" data-route="<?php echo esc_attr(get_query_var('wsms_auth_route', '')); ?>"></div>
    <?php wp_footer(); ?>
</body>
</html>
