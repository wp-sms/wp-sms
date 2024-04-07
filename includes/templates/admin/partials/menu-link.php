<?php
$class = '';
if (isset($_GET['page']) && $_GET['page'] === $slug) {
    $class = 'active';
}

$href = esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=' . $slug);

$badge_count_html = '';
if ($badge_count !== null) {
    $badge_count_html = '<span class="badge">' . esc_html($badge_count) . '</span>';
}
?>

<a class="<?php echo esc_attr($icon_class) . ' ' . esc_attr($class); ?>" href="<?php echo esc_url($href); ?>">
    <span class="icon"></span><?php echo esc_html($link_text) . ' ' . $badge_count_html; ?>
</a>
