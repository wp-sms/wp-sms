<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class="notice notice-<?php echo esc_attr($type); ?> wpsms-admin-notice<?php echo $dismiss ? ' wpsms-admin-notice__dismissible' : ''; ?>">
    <p><?php echo wp_kses_post($message); ?></p>
    <?php if ($dismiss) : ?>
        <a href="<?php echo esc_url($link); ?>" class="notice-dismiss">
            <span class="screen-reader-text"><?php echo esc_html__('Dismiss this notice.', 'wp-sms'); ?></span>
        </a>
    <?php endif; ?>
</div>