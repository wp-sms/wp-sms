<?php

/**
 * Class Privacy
 */
class WP_SMS_Privacy {


	public $options;
	public $metabox = 'privacy_metabox_general';
	public $pagehook = 'sms_page_wp-sms-subscribers-privacy';
	private $wp_nounce;
    protected static $instance = NULL;

	public function __construct() {
		global $wpsms_option;

		$this->wp_nounce =  wp_create_nonce( 'wp_sms_nonce_privacy' );
        add_filter('screen_layout_columns', array($this, 'on_screen_layout_columns'), 10, 2);
        add_action('admin_post_save_'.$this->metabox, array($this, 'on_save_changes_metabox'));
        add_action('load-'.$this->pagehook, array($this, 'load_page_privacy_metabox'));
        add_action( 'admin_notices', array( $this, 'admin_notification' ) );
        add_action( 'admin_init', array( $this, 'process_form' ) );
	}

    /**
     * Access this plugin’s working instance
     */
    public static function get()
    {
        if ( NULL === self::$instance )
            self::$instance = new self;
        return self::$instance;
    }

	/*
	 * Set Screen layout columns
	 */
    function on_screen_layout_columns($columns, $screen) {
        if ($screen == $this->pagehook) {
            $columns[$this->pagehook] = 2;
        }
        return $columns;
    }

    /*
     * Load MetaBox Js in Page
     */
    public function load_page_privacy_metabox()
    {
        /* Load Js Plugin */
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');

        add_meta_box('privacy-meta-1', esc_html( get_admin_page_title() ) , array($this, 'privacy_meta_html_gdpr'), $this->pagehook, 'side', 'core');
        add_meta_box('privacy-meta-2', __('Export User’s Data related to WP-SMS', 'wp-sms'), array($this, 'privacy_meta_html_export'), $this->pagehook, 'normal', 'core');
        add_meta_box('privacy-meta-3', __('Erase User’s Data related to WP-SMS', 'wp-sms'), array($this, 'privacy_meta_html_delete'), $this->pagehook, 'normal', 'core');
    }

    /*
     * Save Change Meta Box
     */
    function on_save_changes_metabox() {
        //user permission check
        if ( !current_user_can('manage_options') )
            wp_die( __('Cheatin&#8217; uh?') );
        check_admin_referer($this->metabox);
        wp_redirect($_POST['_wp_http_referer']);
    }


    /*
     * Gdpr Text Metabox
     */
    public function privacy_meta_html_gdpr()
    {
        echo '<p style="text-align: center;"><img src="' . WP_SMS_URL . '/assets/images/gdpr.png" alt="GDPR"></p>';
        echo '<p class="text-lead">';
        echo sprintf( __('According to Article 17 GDPR, the user (data subject) shall have the right to obtain his/her data or have them erased and forgotten. In WP-SMS plugin you can export the user\'s data or erase his/her data in the case she/he asks. For more information, read %1$sArticle 17 GDPR%2$s.%3$s Note: In this page you can export or delete only the user data related to WP-SMS plugin. For doing the same for your whole WordPress, see the "Export Personal Data" or "Erase Personal Data" pages.', 'wp-sms') , '<a href="' . esc_url( 'https://gdpr-info.eu/art-17-gdpr/' ) . '" target="_blank" style="text-decoration: none; color:#ff0000;">', '</a>', '<br />' ) . "\n";
        echo '</p>';
    }

    /*
     * export Text Metabox
     */
    public function privacy_meta_html_export()
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
        <?php submit_button( __('Export'), 'primary', 'submit', false ); ?>
        </div>
        <input type="hidden" name="wp_sms_nonce_privacy" value="<?php echo $this->wp_nounce; ?>">
        </form>
        <div class="clear"></div>
        <?php
    }


    /*
     * delete Text Metabox
     */
    public function privacy_meta_html_delete()
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
                <?php submit_button( __('Delete'), 'primary', 'submit', false ); ?>
            </div>
            <input type="hidden" name="wp_sms_nonce_privacy" value="<?php echo $this->wp_nounce; ?>">
        </form>
        <div class="clear"></div>
        <?php
    }

    /*
     * Show MetaBox System
     */
    public function show_page_privacy() {
        ?>
        <div id="<?php echo $this->metabox; ?>" class="wrap privacy_page">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="admin-post.php" method="post">
            <?php wp_nonce_field($this->metabox); ?>
            <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
            <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
            <input type="hidden" name="action" value="save_<?php echo $this->metabox; ?>" />
        </form>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">
                    <div id="postbox-container-1" class="postbox-container">
                        <?php do_meta_boxes($this->pagehook, 'side', ''); ?>
                    </div>

                    <div id="postbox-container-2" class="postbox-container">
                        <?php do_meta_boxes($this->pagehook, 'normal', ''); ?>
                    </div>
                </div><!-- #post-body -->
                <br class="clear">
            </div><!-- #poststuff -->

        <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready( function($) {
                $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
                postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
                $('input[type=tel]').bind('keypress', function(e){
                        var keyCode = (e.which)?e.which:event.keyCode;
                        return !(keyCode>31 && (keyCode<48 || keyCode>57) && keyCode!==43 );
                });
            });
            //]]>
        </script>
        </div>
        <?php
    }

    /**
     * Show Admin Notification
     *
     * @param  Not param
     */
    public function admin_notification()
    {
        global $pagenow;

        /*
         * privacy Page
         */
       if($pagenow =="admin.php" and $_GET['page'] =="wp-sms-subscribers-privacy") {

           if( isset($_GET['error']) ) {
               /*
                *  Empty Mobile Number
                */
               if($_GET['error'] =="empty_number") {
                   WP_SMS_Plugin::wp_admin_notice( __( 'Please enter the mobile number', 'wp-sms' ), "error");
               }

               /*
               *  Not found User
                */
               if($_GET['error'] =="not_found") {
                   WP_SMS_Plugin::wp_admin_notice( __( 'User with this mobile number was not found', 'wp-sms' ), "error");
               }
           }

           /*
            * Success Mobile Number
            */
           if( isset($_GET['delete_mobile']) ) {
               WP_SMS_Plugin::wp_admin_notice( sprintf(__('User with %s mobile number is removed completely', 'wp-sms'), trim($_GET['delete_mobile']) ) , "success");
           }

        }
    }

    /*
     * Process Privacy Form
     *
     */
    public function process_form()
    {
        if(isset($_POST['wp_sms_nonce_privacy']) and isset($_POST['submit']) and (isset($_POST['mobile-number-delete']) || isset($_POST['mobile-number-export']))) {
            if( wp_verify_nonce( $_POST['wp_sms_nonce_privacy'], 'wp_sms_nonce_privacy' ) ) {

                $mobile = ($_POST['submit'] == __('Export') ? sanitize_text_field($_POST['mobile-number-export']) : sanitize_text_field($_POST['mobile-number-delete']));

                //Is Empty Mobile Number
                $this->check_empty_mobile($mobile);

                //Check User Not Exist
                $user_data = $this->check_user_exist_mobile($mobile);

                /*
                 * Export Area
                 */
                if ($_POST['submit'] == __('Export')) {
                    $this->create_csv($user_data, "wp-sms-report-" . $mobile);
                }

                /*
                 * Delete Area
                 */
                if ($_POST['submit'] == __('Delete')) {
                    wp_redirect(admin_url(add_query_arg(array('page' => 'wp-sms-subscribers-privacy', 'delete_mobile' => $mobile), 'admin.php')));
                    exit;
                }
            }
        }
    }


    /**
     * Check Mobile Number is Empty
     *
     * @param $mobile Mobile Number
     */
    public function check_empty_mobile($mobile)
    {
        if(empty($mobile)) {
            wp_redirect( admin_url(add_query_arg( array('page' => 'wp-sms-subscribers-privacy', 'error' => 'empty_number'), 'admin.php' )) );
            exit;
        }
    }


    /**
     * Check Exist User By Mobile A
     */
    public function check_user_exist_mobile($mobile)
    {
        global $wpdb;
        $result = array();

        /*
         * Check in Wordpress User
         */
        $get_user = get_users( array('meta_key' => 'mobile', 'meta_value' => $mobile, 'meta_compare' => '=', 'fields' => 'all_with_meta'));
        if( count($get_user) >0) {
            foreach ( $get_user as $user ) {
                //Get User Data
                $result[] = array("FullName" => $user->first_name." ".$user->last_name, "Mobile" => $user->mobile, "RegisterDate" => $user->user_registered );

                //Remove User data if Delete Request
                if($_POST['submit'] ==__('Delete')) delete_user_meta( $user->ID, 'mobile' );
            }
        }

        /*
         * Check in Subscribes Table
         */
        $get_user = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = '$mobile'", ARRAY_A);
        if( count($get_user) >0) {
            foreach ( $get_user as $user ) {
                //Get User Data
                $result[] = array("FullName" => $user['name'], "Mobile" => $user['mobile'], "RegisterDate" => $user['date']);

                //Remove User data if Delete Request
                if($_POST['submit'] ==__('Delete')) $wpdb->delete( $wpdb->prefix.'sms_subscribes', array( 'ID' => $user['ID'] ), array( '%d' ) );
            }
        }

        if ( empty($result) ) {
            wp_redirect( admin_url(add_query_arg( array('page' => 'wp-sms-subscribers-privacy', 'error' => 'not_found'), 'admin.php' )) );
            exit;
        }

        return $result;
    }


    /**
     * Check Exist User With Mobile Meta data
     *
     * @param array  $data Mobile Number
     * @param string  $filename File Name
     * @return string export Force Download Csv File
     */
    public function create_csv($data, $filename)
    {
        $filepath = $_SERVER["DOCUMENT_ROOT"] . $filename.'.csv';
        $fp = fopen($filepath, 'w+');

        $i = 0;
        foreach ($data as $fields) {
            if($i == 0){
                fputcsv($fp, array_keys($fields));
            }
            fputcsv($fp, array_values($fields));
            $i++;
        }
        header('Content-Type: application/octet-stream; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Content-Length: ' . filesize($filepath));
        echo file_get_contents($filepath);
        exit;
    }


}

new WP_SMS_Privacy();