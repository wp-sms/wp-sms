<?php
namespace WP_SMS;
?>

<div class="wrap wpsms-wrap">
    <?php echo Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
    <div class="wpsms-wrap__top">
        <h2><?php esc_html_e('Groups', 'wp-sms'); ?></h2>
        <div class="wpsms-button-group">
            <?php add_thickbox(); ?>
            <a name="<?php esc_html_e('Add Group', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers-group#TB_inline?&width=400&height=125&inlineId=add-group" class="thickbox button"><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Add Group', 'wp-sms'); ?></a>
            <div id="add-group" style="display:none;">
                <?php echo Helper::loadTemplate('admin/group-form.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
    </div>
    <div class="wp-header-end"></div>
    <div class="wpsms-wrap__main">
        <?php echo Helper::loadTemplate('admin/quick-reply.php', ['reload' => true]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <form id="subscribers-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(esc_html__('Search', 'wp-sms'), 'search_id'); ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>
