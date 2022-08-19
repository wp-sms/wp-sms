<?php
namespace WP_SMS;

$groups = Newsletter::getGroups();
?>

<div class="wrap wpsms-wrap">
    <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
    <div class="wpsms-wrap__main">
        <h2><?php _e('Subscribers', 'wp-sms'); ?></h2>
        <?php echo Helper::loadTemplate('admin/quick-reply.php'); ?>
        <div class="wpsms-button-group">
            <a name="<?php _e('Add Subscriber', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers#TB_inline?&width=400&height=250&inlineId=add-subscriber" class="thickbox button"><span class="dashicons dashicons-admin-users"></span> <?php _e('Add Subscriber', 'wp-sms'); ?></a>
            <a href="admin.php?page=wp-sms-subscribers-group" class="button"><span class="dashicons dashicons-category"></span> <?php _e('Manage Group', 'wp-sms'); ?></a>
            <a name="<?php _e('Import', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers#TB_inline?&width=400&height=270&inlineId=import-subscriber" class="thickbox button"><span class="dashicons dashicons-undo"></span> <?php _e('Import', 'wp-sms'); ?></a>
            <a name="<?php _e('Export', 'wp-sms'); ?>" href="admin.php?page=wp-sms-subscribers#TB_inline?&width=400&height=150&inlineId=export-subscriber" class="thickbox button"><span class="dashicons dashicons-redo"></span> <?php _e('Export', 'wp-sms'); ?></a>
        </div>

        <div id="add-subscriber" style="display:none;">
            <?php echo Helper::loadTemplate('admin/subscriber-edit-form.php', array('groups' => $groups)); ?>
        </div>

        <div id="import-subscriber" style="display:none;">
            <form action="" method="post" enctype="multipart/form-data">
                <table>
                    <tr>
                        <td style="padding-top: 10px;">
                            <input id="async-upload" type="file" name="wps-import-file"/>
                            <p class="upload-html-bypass"><?php echo sprintf(__('<code>Excel 97-2003 Workbook (*.xls)</code> is the only acceptable format. Please see <a href="%s">this image</a> to show a standard xls import file.', 'wp-sms'), plugins_url('wp-sms/assets/images/standard-xml-file.png')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="wpsms_group_name" class="wp_sms_subscribers_label"><?php _e('Group', 'wp-sms'); ?></label>
                            <?php if ($groups): ?>
                            <select name="wpsms_group_name" id="wpsms_group_name" class="wp_sms_subscribers_input_text">
                                <?php
                                foreach ($groups as $items):
                                    ?>
                                    <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
                                <?php endforeach;
                                else: ?><?php echo sprintf(__('There is no group! <a href="%s">Add</a>', 'wp-sms'), 'admin.php?page=wp-sms-subscribers-group'); ?><?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 10px;">
                            <input type="checkbox" name="ignore_duplicate" value="ignore"/> <?php _e('Ignore duplicate subscribers if exist to other group.', 'wp-sms'); ?>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding-top: 20px;">
                            <input type="submit" class="button-primary" name="wps_import" value="<?php _e('Upload', 'wp-sms'); ?>"/>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <div id="export-subscriber" style="display:none;">
            <form action="<?php echo admin_url('admin.php?page=wp-sms-subscribers') ?>" method="post">
                <table>
                    <tr>
                        <td style="padding-top: 10px;">
                            <label for="export-file-type" class="wp_sms_subscribers_label"><?php _e('Export To', 'wp-sms'); ?></label>
                            <select id="export-file-type" name="export-file-type" class="wp_sms_subscribers_input_text">
                                <option value="0"><?php _e('Please select.', 'wp-sms'); ?></option>
                                <option value="excel">Excel</option>
                                <option value="xml">XML</option>
                                <option value="csv">CSV</option>
                                <option value="tsv">TSV</option>
                            </select>
                            <p class="description"><?php _e('Select the output file type.', 'wp-sms'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding-top: 10px;">
                            <input type="submit" class="button-primary" name="wps_export_subscribe" value="<?php _e('Export', 'wp-sms'); ?>"/>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <form id="subscribers-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(__('Search', 'wp-sms'), 'search_id'); ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>
