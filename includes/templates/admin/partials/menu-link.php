<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

$class = '';
if (isset($_GET['page']) && $_GET['page'] === $slug) {
    $class = 'active';
}

$href = esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=' . $slug);

$badge_count_html = '';
if ($badge_count !== null) {
    $badge_count_html = sprintf(
            '<span class="badge">%s</span>',
            esc_html($badge_count)
    );
}
?>

<a class="<?php echo esc_attr($icon_class) . ' ' . esc_attr($class); ?>" href="<?php echo esc_url($href); ?>">
    <span class="icon"></span>
    <?php
    echo esc_html($link_text);
    echo ' ' . wp_kses_post($badge_count_html);
    ?>
</a>
