<?php $option = get_option('wpsms_settings'); ?>
<div class="wpsms-header-banner" style="<?php echo isset($full_width_banner) && $full_width_banner ? 'margin-left: -20px; width: auto; max-width: none;' : ''; ?>">
    <div class="wpsms-header-logo"></div>

    <?php
    // Create tab url and active class for licenses tab
    $tab_url = add_query_arg(array(
        'settings-updated' => false,
        'tab'              => 'licenses',
        'page'             => 'wp-sms-settings'
    ));
    $active  = isset($_GET['tab']) && $_GET['tab'] == 'licenses' ? 'active' : '';

    // Get information about active add-ons
    $addons = is_plugin_active('wp-sms-pro/wp-sms-pro.php') ? array('license_wp-sms-pro_status' => false) : array();

    $get_addons = wp_sms_get_addons();
    foreach ($get_addons as $addOnKey => $addOnName) {
        $addons["license_{$addOnKey}_status"] = false;
    }

    foreach ($addons as $option_key => $status) {
        if (isset($option[$option_key]) && $option[$option_key]) {
            $addons[$option_key] = true;
        }
    }
    ?>

    <!-- Header Items -->
    <div class="wpsms-header-items-flex">

        <?php
        if (!function_exists('generate_menu_link')) {
            function generate_menu_link($page_slug, $link_text, $icon_class, $badge_count = null)
            {
                $class = '';
                if (isset($_GET['page']) && $_GET['page'] === $page_slug) {
                    $class = 'active';
                }

                $href = esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=' . $page_slug);

                $badge = '';
                if ($badge_count !== null) {
                    $badge = '<span class="badge">' . esc_html($badge_count) . '</span>';
                }

                $link = '<a class="' . esc_attr($icon_class) . ' ' . esc_attr($class) . '" href="' . $href . '">';
                $link .= '<span class="icon"></span>' . esc_html($link_text) . ' ' . $badge;
                $link .= '</a>';

                echo $link;
            }
        }
        generate_menu_link('wp-sms', __('Send SMS', 'wp-sms'), 'send-sms');
        generate_menu_link('wp-sms-inbox', __('Inbox', 'wp-sms'), 'inbox', 2);
        generate_menu_link('wp-sms-outbox', __('Outbox', 'wp-sms'), 'outbox');
        generate_menu_link('wp-sms-integrations', __('Integrations', 'wp-sms'), 'integrations');
        ?>
    </div>
    <div class="wpsms-header-items-side">
        <?php if (count($addons) == 0) : ?>
            <div class="license-status license-status--free">
                <a class="upgrade" href="<?php echo esc_url(WP_SMS_SITE . '/buy'); ?>" target="_blank"><span><?php esc_html_e('UPGRADE TO PRO', 'wp-sms'); ?></span></a>
            </div>
        <?php else : ?>
            <div class="license-status license-status--valid">
                <span>
                    <?php
                    // translators: %1$s: Active licenses, %2$s: Total licenses
                    echo sprintf(esc_html__('License: %1$s/%2$s', 'wp-sms'), count(array_filter($addons)), count($addons));
                    ?>
                    <a class="upgrade" target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/buy'); ?>"><?php esc_html_e('UPGRADE', 'wp-sms'); ?></a>
                </span>
            </div>
        <?php endif; ?>
        <a href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-settings'); ?>" title="<?php esc_html_e('setting', 'wp-sms'); ?>" class="setting <?php if (isset($_GET['page']) && $_GET['page'] === 'wp-sms-settings') {
            echo 'active';
        } ?>"></a>
        <a href="<?php echo esc_url(WP_SMS_SITE . '/support'); ?>" target="_blank" title="<?php esc_html_e('support', 'wp-sms'); ?>" class="support"></a>

    </div>

</div>