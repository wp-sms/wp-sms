<div id="wp-sms-import-from" style="display:none;">

    <div class="wpsms-sendsms__overlay">
        <svg class="wpsms-sendsms__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
            <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"></animateTransform>
            </circle>
        </svg>
    </div>

    <!-- Show request message the client -->
    <div class="wpsms-wrap wpsms-export-popup">
        <div class="wp-sms-popup-messages"></div>
    </div>

    <form class="js-wpSmsUploadForm" method="post" enctype="multipart/form-data">
        <p id="first-row-label" style="display: none">Specify data type if each column.</p>
        <table>
            <tr class="js-WpSmsHiddenAfterUpload">
                <td style="padding-top: 10px;">
                    <input type="file" accept="text/csv" id="wp-sms-input-file">
                    <p>The only acceptable format is <code>*.csv.</code></p>
                </td>
            </tr>

            <tr class="js-WpSmsHiddenAfterUpload">
                <td style="padding-top: 10px;">
                    <input type="checkbox" id="file-has-header">
                    <label for="file-has-header">Check the box if the file includes headers. </label>
                </td>
            </tr>

            <tr>
                <td colspan="2" style="padding-top: 20px;">
                    <input type="submit" class="js-wpSmsUploadButton button-primary" value="<?php _e('Upload', 'wp-sms'); ?>"/>
                </td>
            </tr>
        </table>
    </form>
</div>