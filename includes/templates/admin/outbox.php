<?php
namespace WP_SMS;
?>

<div class="wrap wpsms-wrap">
    <?php echo Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="wpsms-wrap__top">
        <h2><?php esc_html_e('Outbox SMS', 'wp-sms'); ?></h2>
        <div class="wpsms-button-group">
            <a name="<?php esc_html_e('Export', 'wp-sms'); ?>" href="admin.php?page=wp-sms-outbox#TB_inline?&width=400&height=150&inlineId=wp-sms-export-from" class="thickbox button"><span class="dashicons dashicons-redo"></span> <?php esc_html_e('Export', 'wp-sms'); ?></a>
        </div>
    </div>
    <div class="wp-header-end"></div>
    <div class="wpsms-wrap__main">
        <?php echo Helper::loadTemplate('admin/quick-reply.php', ['reload' => 'true']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php echo Helper::loadTemplate('admin/export-outbox-form.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <form id="outbox-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(esc_html__('Search', 'wp-sms'), 'search_id'); ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>
