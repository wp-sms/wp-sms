<?php 

?>
<div class="wpsms-wrap__top <?php echo isset($real_time_button) ? 'wpsms-wrap__top--has__realtime' : ''; ?>">
    <?php if (isset($backUrl, $backTitle)): ?>
        <a href="<?php echo esc_url($backUrl) ?>" title="<?php echo esc_html($backTitle) ?>" class="wpsms-previous-url"><?php echo esc_html($backTitle) ?></a>
    <?php endif ?>
    
    <?php if (isset($title)): ?>
        <h2 class="wpsms_title <?php echo isset($install_addon_btn_txt) ? 'wpsms_plugins_page-title' : '' ?>">
            <?php if (isset($flagImage)): ?>
                <img class="wpsms-flag" src="<?php echo esc_url($flagImage) ?>" alt="<?php echo esc_attr($title) ?>">
             <?php endif ?>
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

    <?php if (isset($real_time_button)): ?>
        <?php
        $is_realtime_active = Helper::isAddOnActive('realtime-stats');
        ?>

        <?php if ($is_realtime_active): ?>
            <a class="wpsms-realtime-btn" href="<?php echo esc_url(admin_url('admin.php?page=wp_sms_realtime_stats')) ?>" title="<?php echo esc_html_e('Real-time stats are available! Click here to view', 'wp-sms') ?>">
                <?php esc_html_e('Realtime', 'wp-sms'); ?>
            </a>
        <?php else: ?>
            <button class="wpsms-realtime-btn disabled wpsms-tooltip-premium" >
                <?php esc_html_e('Realtime', 'wp-sms'); ?>
                <span class="wpsms-tooltip_templates tooltip-premium tooltip-premium--bottom tooltip-premium--right">
                    <span id="tooltip_realtime">
                        <a data-target="wp-sms-realtime-stats" class="js-wpsms-openPremiumModal"><?php esc_html_e('Learn More', 'wp-sms'); ?></a>
                        <span>
                            <?php esc_html_e('Premium Feature', 'wp-sms'); ?>
                        </span>
                    </span>
                </span>
            </button>
        <?php endif ?>
    <?php endif; ?>
    <?php if (isset($Datepicker)): ?>
        <form class="wpsms-search-date wpsms-today-datepicker" method="get">
            <div>
                <input type="hidden" name="page" value="<?php echo esc_attr($pageName); ?>">
                <input class="wpsms-search-date__input wpsms-js-calendar-field" id="search-date-input" type="text" size="18" name="day" data-wpsms-date-picker="day" readonly value="<?php echo esc_attr($day); ?>" autocomplete="off" placeholder="YYYY-MM-DD" required>
            </div>
        </form>
    <?php endif ?>

    <?php if (isset($hasDateRang) || isset($filters) || isset($searchBoxTitle) || isset($filter)): ?>
        <div class="wpsms-head-filters">
            <?php
            if (!empty($hasDateRang)) {
                include 'date.range.php';
            }

            if (isset($filter) and isset($filter['code'])) {
                echo $filter['code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
                <div class="wp-clearfix"></div>
                <?php
            }

            if (!empty($filters)) {
                foreach ($filters as $filter) {
                    require_once "filters/$filter-filter.php";
                }
            }

            if (isset($searchBoxTitle)): ?>
                <div class="wpsms-filter-visitor wpsms-head-filters__item loading">
                    <div class="wpsms-dropdown">
                        <label for="wpsms-visitor-filter" class="selectedItemLabel"><?php echo esc_attr($searchBoxTitle); ?></label>
                        <select id="wpsms-visitor-filter" class="wpsms-select2" data-type-show="select2"></select>
                    </div>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
<div class="wpsms-wrap__main">
    <div class="wp-header-end"></div>