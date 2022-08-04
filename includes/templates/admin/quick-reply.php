<div id="wpsms-quick-reply" style="display: none">
    <div class="wpsms-sendsms__overlay">
        <svg class="wpsms-sendsms__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
            <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"></animateTransform>
            </circle>
        </svg>
    </div>

    <div class="wpsms-wrap wpsms-quick-reply-popup">
        <div class="wpsms-quick-reply-popup-message"></div>
    </div>

    <form method="post" class="js-wpSmsQuickReply" <?php if (isset($reload)) : echo 'data-reload=' . $reload; endif; ?>>
        <table>
            <tr>
                <td style="padding-top: 10px;">
                    <label for="wpsms-quick-reply-to"><?php _e('To', 'wp-sms'); ?></label>
                    <input type="text" id="wpsms-quick-reply-to" class="js-wpSmsQuickReplyTo" name="wpsms_quick_reply_message" value="" readonly style="display: block; width: 100%"/>
                </td>
            </tr>
            <tr>
                <td style="padding-top: 10px;">
                    <label for="wpsms-quick-reply-message"><?php _e('Message', 'wp-sms-two-way'); ?></label>
                    <textarea id="wpsms-quick-reply-message" class="js-wpSmsQuickReplyMessage" name="wpsms_quick_reply_message" cols="60" rows="10" wrap="hard" dir="auto" style="width: 100%"></textarea>
                </td>
            </tr>

        </table>
    </form>
    <div class="quick-reply-submit">
        <p class="submit" style="padding: 0;">
            <input type="submit" class="button-primary" name="SendSMS" value="<?php _e('Reply', 'wp-sms'); ?>"/>
        </p>
    </div>
</div>
