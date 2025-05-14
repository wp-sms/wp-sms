<div id="wp-sms-import-from" style="display:none;">

    <!--Loading Spinner-->
    <div class="js-wpSmsOverlay wpsms-sendsms__overlay">
        <svg class="wpsms-sendsms__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
            <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"></animateTransform>
            </circle>
        </svg>
    </div>

    <!-- Show request message to the client -->
    <div class="wpsms-wrap wpsms-import-popup js-wpSmsMessageModal">
        <div class="wp-sms-popup-messages js-wpSmsErrorMessage wpsms-admin-notice"></div>
        <div class="js-WpSmsImportResult" style="display: none">
            <table>
                <thead>
                <tr>
                    <th>Number</th>
                    <th>Reason for failure</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <form class="js-wpSmsUploadForm wp-sms-upload-form" method="post" enctype="multipart/form-data">
        <div class="js-WpSmsImportStep1">
            <label for="wp-sms-input-file"  class="wp-sms-upload-box">
                <span class="wp-sms-upload-area">
                    <input type="file" accept="text/csv" id="wp-sms-input-file" style="display: none;">
                    <svg width="40" height="39" viewBox="0 0 40 39" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20.6731 8.125H34.625C35.5225 8.125 36.25 8.85255 36.25 9.75V32.5C36.25 33.3975 35.5225 34.125 34.625 34.125H5.375C4.47755 34.125 3.75 33.3975 3.75 32.5V6.5C3.75 5.60254 4.47755 4.875 5.375 4.875H17.4231L20.6731 8.125ZM7 8.125V30.875H33V11.375H19.3269L16.0769 8.125H7ZM21.625 21.125V27.625H18.375V21.125H13.5L20 14.625L26.5 21.125H21.625Z" fill="#C2410C"/>
                    </svg>

                    <span class="wp-sms-choose-file-btn">Choose File</span>
                    <span class="file-name">file-name.csv</span>
                </span>
                <span class="supported-formats">Supported formats: .csv</span>
            </label>
        </div>

        <div class="js-WpSmsImportStep1 wps-has-header">
            <input type="checkbox" id="file-has-header" class="js-wpSmsFileHasHeader">
            <label for="file-has-header"><?php esc_html_e('Check the box if the file includes headers.', 'wp-sms'); ?> </label>
        </div>

        <p id="first-row-label" class="js-WpSmsImportStep2" style="display: none"><?php esc_html_e('Now, please specify data type of each column.', 'wp-sms'); ?></p>

        <table class="js-WpSmsImportStep2">
            <tbody>
            <tr id="wp-sms-group-select" class="js-wpSmsGroupSelect" style="display: none">
                <td colspan="2">
                    <p><?php esc_html_e('Choose or add a group:', 'wp-sms'); ?></p>
                    <select>
                        <option value="0"><?php esc_html_e('Please Select', 'wp-sms'); ?></option>
                        <option value="new_group"><?php esc_html_e('Add a new group', 'wp-sms'); ?></option>
                        <?php
                        if ($groups) :
                            foreach ($groups as $group) :
                                ?>
                                <option value="<?php echo esc_attr($group->ID); ?>"><?php echo esc_attr($group->name); ?></option>
                            <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                </td>
            </tr>
            <tr id="wp-sms-group-name" class="js-wpSmsGroupName" style="display: none">
                <td>
                    <input type="text" id="wp-sms-select-group-name" class="js-wpSmsSelectGroupName">
                </td>
            </tr>
            </tbody>
        </table>

        <div>
            <input type="submit" class="js-wpSmsUploadButton button-primary" value="Upload">
            <input type="submit" class="js-wpSmsImportButton button-primary" style="display: none;" value="Import">
            <input type="submit" class="js-wpSmsRefreshButton button-primary" style="display: none;" value="Refresh">
        </div>
    </form>

</div>