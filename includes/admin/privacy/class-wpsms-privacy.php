<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Class Privacy
 */
class Privacy
{

    public $pagehook;
    public $metabox = 'privacy_metabox_general';

    /*
     * Gdpr Text Metabox
     */
    public static function privacy_meta_html_gdpr()
    {
        echo '<p style="text-align: center;"><img src="' . WP_SMS_URL . '/assets/images/gdpr.png" alt="GDPR"></p>';
        echo '<p class="text-lead">';
        echo sprintf(__('According to Article 17 GDPR, the user (data subject) shall have the right to obtain his/her data or have them erased and forgotten. In WP SMS plugin you can export the user\'s data or erase his/her data in the case she/he asks. For more information, read %1$sArticle 17 GDPR%2$s.%3$s Note: In this page you can export or delete only the user data related to WP SMS plugin. For doing the same for your whole WordPress, see the "Export Personal Data" or "Erase Personal Data" pages.', 'wp-sms'), '<a href="' . esc_url('https://gdpr-info.eu/art-17-gdpr/') . '" target="_blank" style="text-decoration: none; color:#ff0000;">', '</a>', '<br />') . "\n";
        echo '</p>';
    }

    /*
     * export Text Metabox
     */
    public static function privacy_meta_html_export()
    {
        ?>
        <form method="post" action="">
            <div id="universal-message-container">
                <div class="options">
                    <p>
                        <label><?php _e('User’s Mobile Number', 'wp-sms'); ?></label>
                        <br/>
                        <input type="tel" name="mobile-number-export" value=""/>
                    </p>
                </div>
                <?php submit_button(__('Export'), 'primary', 'submit', false); ?>
            </div>
            <input type="hidden" name="wp_sms_nonce_privacy" value="<?php echo wp_create_nonce('wp_sms_nonce_privacy'); ?>">
        </form>
        <div class="clear"></div>
        <?php
    }


    /*
     * delete Text Metabox
     */
    public static function privacy_meta_html_delete()
    {
        ?>
        <form method="post" action="">
            <div id="universal-message-container">
                <div class="options">
                    <p>
                        <label><?php _e('Enter User’s Mobile Number', 'wp-sms'); ?></label>
                        <br/>
                        <input type="tel" name="mobile-number-delete" value=""/>
                        <br/>
                        <span class="description"><?php _e('Note: You cannot undo these actions.', 'wp-sms'); ?></span>
                    </p>
                </div><!-- #universal-message-container -->
                <?php submit_button(__('Delete'), 'primary', 'submit', false); ?>
            </div>
            <input type="hidden" name="wp_sms_nonce_privacy" value="<?php echo wp_create_nonce('wp_sms_nonce_privacy'); ?>">
        </form>
        <div class="clear"></div>
        <?php
    }

    /*
     * Show MetaBox System
     */
    public function render_page()
    {
        ?>
        <div id="<?php echo $this->metabox; ?>" class="wrap wpsms-wrap privacy_page">
	        <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
            <div class="wpsms-wrap__main">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <form action="admin-post.php" method="post">
                    <?php wp_nonce_field($this->metabox); ?>
                    <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
                    <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>
                    <input type="hidden" name="action" value="save_<?php echo $this->metabox; ?>"/>
                </form>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">
                        <div id="postbox-container-1" class="postbox-container">
                            <?php do_meta_boxes($this->pagehook, 'side', ''); ?>
                        </div>

                        <div id="postbox-container-2" class="postbox-container">
                            <?php do_meta_boxes($this->pagehook, 'normal', ''); ?>
                        </div>
                    </div><!-- #post-body --><br class="clear">
                </div><!-- #poststuff -->

                <script type="text/javascript">
                    //<![CDATA[
                    jQuery(document).ready(function ($) {
                        $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
                        postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
                        $('input[type=tel]').bind('keypress', function (e) {
                            var keyCode = (e.which) ? e.which : event.keyCode;
                            return !(keyCode > 31 && (keyCode < 48 || keyCode > 57) && keyCode !== 43);
                        });
                    });
                    //]]>
                </script>
            </div>
        </div>
        <?php
    }

}

new Privacy();
