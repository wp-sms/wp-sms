<div id="wp-sms-export-from" style="display:none;">
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

    <form class="js-wpSmsExportForm">
        <input type="hidden">
        <input class="wp-sms-export-type" type="hidden" value="outbox">
        <table>
            <tr>
                <td style="padding-top: 10px;">
                    <p> <?php _e('The data will be exported to a <code>*.csv</code> file.', 'wp-sms'); ?> </p>
                </td>
            </tr>

            <tr>
                <td colspan="2" style="padding-top: 10px;">
                    <input type="submit" class="button-primary js-wpSmsExportButton" value="<?php esc_html_e('Export', 'wp-sms'); ?>"/>
                </td>
            </tr>
        </table>
    </form>
</div>