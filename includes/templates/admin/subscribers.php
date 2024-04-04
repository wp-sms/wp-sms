<?php
namespace WP_SMS;

$groups = Newsletter::getGroups();
?>

<div class="wrap wpsms-wrap">
    <?php echo Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="wpsms-wrap__top">
        <h2><?php esc_html_e('Subscribers', 'wp-sms'); ?></h2>
        <div class="wpsms-button-group">
            <a name="<?php esc_html_e('Add Subscriber', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers#TB_inline?&width=400&height=250&inlineId=add-subscriber" class="thickbox button"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Add Subscriber', 'wp-sms'); ?></a>
            <a title="<?php esc_html_e('Manage Groups', 'wp-sms'); ?>" href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-subscribers-group') ?>" class="button"><span class="dashicons dashicons-category"></span> <?php esc_html_e('Manage Groups', 'wp-sms'); ?></a>
            <a name="<?php esc_html_e('Import', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers#TB_inline?&width=500&height=270&inlineId=wp-sms-import-from" class="thickbox button"><span class="dashicons dashicons-undo"></span> <?php esc_html_e('Import', 'wp-sms'); ?></a>
            <a name="<?php esc_html_e('Export', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers#TB_inline?&width=400&height=150&inlineId=wp-sms-export-from" class="thickbox button"><span class="dashicons dashicons-redo"></span> <?php esc_html_e('Export', 'wp-sms'); ?></a>
        </div>
    </div>
    <div class="wp-header-end"></div>
    <div class="wpsms-wrap__main">
        <?php echo Helper::loadTemplate('admin/quick-reply.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div id="add-subscriber" style="display:none;">
            <?php echo Helper::loadTemplate('admin/subscriber-form.php', ['groups' => $groups]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <?php
            echo Helper::loadTemplate('admin/import-subscriber-form.php', ['groups' => $groups]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Helper::loadTemplate('admin/export-subscriber-form.php', ['groups' => $groups]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>

        <form id="subscribers-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(__('Search', 'wp-sms'), 'search_id'); ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>
