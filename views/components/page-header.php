<?php 

?>
<div class="wpsms-wrap__top">
    <?php if (isset($title)): ?>
        <h2 class="wpsms_title <?php echo isset($install_addon_btn_txt) ? 'wpsms_plugins_page-title' : '' ?>">
            <?php echo(isset($title) ? esc_attr($title) : (function_exists('get_admin_page_title') ? get_admin_page_title() : '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	 ?>
            <?php if (!empty($tooltip)) : ?>
                <span class="wpsms-tooltip" title="<?php echo esc_attr($tooltip); ?>"><i class="wpsms-tooltip-icon info"></i></span>
            <?php endif; ?>

            <?php if (isset($install_addon_btn_txt)) : ?>
                <a href="<?php echo esc_attr($install_addon_btn_link); ?>" class="wpsms-install-addon-btn">
                    <span><?php echo esc_attr($install_addon_btn_txt); ?></span>
                </a>
             <?php endif; ?>
        </h2>
    <?php endif ?>

    <?php do_action('wp_sms_after_admin_page_title'); ?>
</div>
<div class="wpsms-wrap__main">
    <div class="wp-header-end"></div>