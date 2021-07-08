<?php
add_action('admin_init', 'wp_sms_export_subscribers');
function wp_sms_export_subscribers()
{
    if (isset($_POST['wps_export_subscribe'])) {
        if (!is_super_admin()) {
            wp_die(__('Access denied!', 'wp-sms'));
        }

        $type = sanitize_text_field($_POST['export-file-type']);

        if ($type) {

            global $wpdb;

            require(WP_SMS_DIR . 'includes/libraries/php-export-data.class.php');

            $file_name = date('Y-m-d_H-i');

            $result = $wpdb->get_results("SELECT `ID`,`date`,`name`,`mobile`,`status`,`group_ID` FROM {$wpdb->prefix}sms_subscribes", ARRAY_A);

            switch ($type) {
                case 'excel':
                    $exporter = new ExportDataExcel('browser', "{$file_name}.xls");
                    break;

                case 'xml':
                    $exporter = new ExportDataExcel('browser', "{$file_name}.xml");
                    break;

                case 'csv':
                    $exporter = new ExportDataCSV('browser', "{$file_name}.csv");
                    break;

                case 'tsv':
                    $exporter = new ExportDataTSV('browser', "{$file_name}.tsv");
                    break;
            }

            $exporter->initialize();

            foreach ($result[0] as $key => $col) {
                $columns[] = $key;
            }
            $exporter->addRow($columns);

            foreach ($result as $row) {
                $exporter->addRow($row);
            }

            $exporter->finalize();
            exit;
        } else {
            wp_die(__('Please select the desired items.', 'wp-sms'), false, array('back_link' => true));
        }
    }
}
