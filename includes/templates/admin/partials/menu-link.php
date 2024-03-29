<?php
$class = '';
if (isset($_GET['page']) && $_GET['page'] === $slug) {
    $class = 'active';
}

$href = esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=' . $slug);

$badge = '';
if ($badge_count !== null) {
    $badge = '<span class="badge">' . esc_html($badge_count) . '</span>';
}

$link = '<a class="' . esc_attr($icon_class) . ' ' . esc_attr($class) . '" href="' . $href . '">';
$link .= '<span class="icon"></span>' . esc_html($link_text) . ' ' . $badge;
$link .= '</a>';

echo $link;
