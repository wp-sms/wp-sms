<div class="wrap">
    <h2><?php _e( 'Import', 'wp-sms' ); ?></h2>
    <form method="post" action="" enctype="multipart/form-data">
        <div id="html-upload-ui">
            <p id="async-upload-wrap">
                <input id="async-upload" type="file" name="wps-import-file"/>
            <p class="upload-html-bypass"><?php echo sprintf( __( '<code>Excel 97-2003 Workbook (*.xls)</code> is the only acceptable format. Please see <a href="%s">this image</a> to show a standard xls import file.', 'wp-sms' ), plugins_url( 'wp-sms/assets/images/standard-xml-file.png' ) ); ?></p>
            </p>

            <p id="async-upload-wrap">
                <label for="wpsms_group_name"><?php _e( 'Group', 'wp-sms' ); ?>:</label>
                <select name="wpsms_group_name" id="wpsms_group_name">
					<?php foreach ( $this->subscribe->get_groups() as $items ): ?>
                        <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
					<?php endforeach; ?>
                </select>
            </p>

            <a href="admin.php?page=wp-sms-subscribers" class="button"><?php _e( 'Back', 'wp-sms' ); ?></a>
            <input type="submit" class="button-primary" name="wps_import" value="<?php _e( 'Upload', 'wp-sms' ); ?>"/>
        </div>
    </form>
</div>