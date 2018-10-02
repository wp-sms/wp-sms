<div class="wrap">

    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p>
    <?php
    $content = sprintf( __('According to Article 17 GDPR, the user  (data subject) shall have the right to obtain his/her data or have them erased and forgotten.  In WP-SMS plugin you can export the user\'s data or erase his/her data in the case she/he asks.  For more information, read %1$sArticle 17 GDPR%2$s.', 'wp-sms') , '<a href="' . esc_url( 'https://gdpr-info.eu/art-17-gdpr/' ) . '" target="_blank">', '</a>' ) . "\n";
    echo wpautop( $content );
    ?></p>

    <form method="post" action="">

        <div id="universal-message-container">
            <h2><?php _e('Export the data', 'wp-sms'); ?></h2>

            <div class="options">
                <p>
                    <label><?php _e('Mobile number', 'wp-sms'); ?></label>
                    <br/>
                    <input type="tel" name="mobile-number-export" value=""/>
                </p>
            </div><!-- #universal-message-container -->

			<?php submit_button( __('Export', 'wp-sms') ); ?>
        </div>

        <div id="universal-message-container">
            <h2><?php _e('Delete the data', 'wp-sms'); ?></h2>

            <div class="options">
                <p>
                    <label><?php _e('What message would you like to be displayed above each post?', 'wp-sms'); ?></label>
                    <br/>
                    <input type="tel" name="mobile-number-delete" value=""/>
                </p>
            </div><!-- #universal-message-container -->

			<?php submit_button( __('Delete', 'wp-sms') ); ?>
        </div>

        <input type="hidden" name="wp_sms_nonce_privacy" value="<?php echo wp_create_nonce( 'wp_sms_nonce_privacy' ); ?>">

    </form>

</div><!-- .wrap -->
<script>
    jQuery(document).ready(function($){
        $('input[type=tel]').bind('keypress', function(e){
            var keyCode = (e.which)?e.which:event.keyCode;
            return !(keyCode>31 && (keyCode<48 || keyCode>57) && keyCode!==43 );
        });
    });
</script>