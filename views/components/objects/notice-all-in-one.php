<?php

// Set default values if not provided
$link = $link ?? '#';
$linkText = $linkText ?? $link_text ?? 'Upgrade to unlock all features.';
$title = $title ?? 'Premium features are available in the All-in-One version.';
?>

<div class="wpsms-all-in-one-notice">
    <div class="wpsms-notice-content">
            <p><?php echo wp_kses($title, ['strong' => [], 'b' => []]); ?></p>
            <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($linkText); ?>
            </a>
     </div>
</div>