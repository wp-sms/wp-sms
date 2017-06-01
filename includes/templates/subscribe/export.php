<div class="wrap">
    <h2><?php _e( 'Export', 'wp-sms' ); ?></h2>
    <form id="export-filters" method="post" action="<?php echo plugins_url( 'wp-sms/export.php' ); ?>">
        <table>
            <tr valign="top">
                <th scope="row">
                    <label for="export-file-type"><?php _e( 'Export To', 'wp-sms' ); ?>:</label>
                </th>

                <td>
                    <select id="export-file-type" name="export-file-type">
                        <option value="0"><?php _e( 'Please select.', 'wp-sms' ); ?></option>
                        <option value="excel">Excel</option>
                        <option value="xml">XML</option>
                        <option value="csv">CSV</option>
                        <option value="tsv">TSV</option>
                    </select>
                    <p class="description"><?php _e( 'Select the output file type.', 'wp-sms' ); ?></p>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <a href="admin.php?page=wp-sms-subscribers" class="button"><?php _e( 'Back', 'wp-sms' ); ?></a>
                    <input type="submit" class="button-primary" name="wps_export_subscribe"
                           value="<?php _e( 'Export', 'wp-sms' ); ?>"/>
                </td>
            </tr>
        </table>
    </form>
</div>