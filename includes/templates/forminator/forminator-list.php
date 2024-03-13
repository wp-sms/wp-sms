<?php
namespace WP_SMS;
?>
<div class="wrap wpsms-wrap">
    <div class="wpsms-wrap__main">
        <h2><?php _e('All Forms', 'wp-sms'); ?></h2>
        <hr>
        <form id="outbox-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>

<style>
.addon_forminator_integration_settings_tab{
    background: none;
    padding: 0;
    box-shadow: 0 0;
    border: 0;
}
</style>

