<?php
/**
 * Plugin Name: WP SMS
 * Plugin URI: http://wp-sms-pro.com/
 * Description: A powerful texting plugin for WordPress
 * Version: 4.1.1
 * Author: Verona Labs
 * Author URI: https://veronalabs.com/
 * Text Domain: wp-sms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Plugin defines
 */
define( 'WP_SMS_VERSION', '4.1.1' );
define( 'WP_SMS_DIR_PLUGIN', plugin_dir_url( __FILE__ ) );
define( 'WP_SMS_ADMIN_URL', get_admin_url() );
define( 'WP_SMS_SITE', 'https://wp-sms-pro.com' );
define( 'WP_SMS_MOBILE_REGEX', '/^[\+|\(|\)|\d|\- ]*$/' );
define( 'WP_SMS_CURRENT_DATE', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

/**
 * Get plugin options
 */
$wpsms_option = get_option( 'wpsms_settings' );

/**
 * Initial gateway
 */
include_once dirname( __FILE__ ) . '/includes/functions.php';
$sms = initial_gateway();

/*
 * Plugin Loaded Action
 */
add_action('plugins_loaded', array(WP_SMS_Plugin::get_instance(), 'plugin_setup'));

/**
 * Install plugin
 */
register_activation_hook( __FILE__, array( 'WP_SMS_Plugin', 'install' ) );

/**
 * Class WP_SMS_Plugin
 */
class WP_SMS_Plugin {

    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = NULL;

	/**
	 * Wordpress Admin url
	 *
	 * @var string
	 */
	public $admin_url = WP_SMS_ADMIN_URL;

	/**
	 * WP SMS gateway object
	 *
	 * @var string
	 */
	public $sms;

	/**
	 * WP SMS subscribe object
	 *
	 * @var string
	 */
	public $subscribe;

	/**
	 * Wordpress Database
	 *
	 * @var string
	 */
	protected $db;

	/**
	 * Wordpress Table prefix
	 *
	 * @var string
	 */
	protected $tb_prefix;

	/**
	 * Options
	 *
	 * @var string
	 */
	protected $options;

    /**
     * Access this plugin’s working instance
     *
     * @wp-hook plugins_loaded
     * @return  object of this class
     */
    public static function get_instance()
    {
        if ( NULL === self::$instance )
            self::$instance = new self;
        return self::$instance;
    }

	/**
	 * Constructors plugin Setup
	 *
	 * @param  Not param
	 */
	public function plugin_setup() {
		global $wpdb, $table_prefix, $wpsms_option, $sms;

		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->options   = $wpsms_option;

		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

        $this->includes();
        $this->sms = $sms;
        $this->init();
        $this->subscribe = new WP_SMS_Subscriptions();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ) );

		add_action( 'admin_bar_menu', array( $this, 'adminbar' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'plugin_row_meta', array( $this, 'meta_links' ), 0, 2 );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		//WordPress Multisite
        add_action( 'wpmu_new_blog', array( $this, 'add_table_on_create_blog'), 10, 1 );
        add_filter( 'wpmu_drop_tables', array( $this, 'remove_table_on_delete_blog') );

		add_filter( 'wp_sms_to', array( $this, 'modify_bulk_send' ) );

		//Upgrade Plugin if needed
		include_once dirname( __FILE__ ) . '/upgrade.php';
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-sms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Creating plugin tables
	 *
	 * @param  Not param
	 */
	static function install( $network_wide ) {
		global $wp_sms_db_version;

        include_once dirname( __FILE__ ) . '/install.php';
        $install = new WP_SMS_INSTALL;
        $install->create_table( $network_wide );

		add_option( 'wp_sms_db_version', WP_SMS_VERSION );

		// Delete notification new wp_version option
		delete_option( 'wp_notification_new_wp_version' );
	}

    /**
     * Creating Table for New Blog in wordpress
     */
    public function add_table_on_create_blog($blog_id)
    {
        if ( is_plugin_active_for_network( 'wp-sms/wp-sms.php' ) ) {
            switch_to_blog( $blog_id );
            include_once dirname( __FILE__ ) . '/install.php';
            $install = new WP_SMS_INSTALL;
            $install->table_sql();
            restore_current_blog();
        }
	}

    /**
     * Remove Table On Delete Blog Wordpress
     */
    public function remove_table_on_delete_blog($tables)
    {
        global $wpdb;
        foreach(array('sms_subscribes', 'sms_subscribes_group', 'sms_send') as $tbl){ $tables[] = $wpdb->prefix . $tbl; }
        return $tables;
    }

	/**
	 * Adding new capability in the plugin
	 *
	 * @param  Not param
	 */
	public function add_cap() {
		// get administrator role
		$role = get_role( 'administrator' );

		$role->add_cap( 'wpsms_sendsms' );
		$role->add_cap( 'wpsms_outbox' );
		$role->add_cap( 'wpsms_subscribers' );
		$role->add_cap( 'wpsms_setting' );
	}

	/**
	 * Includes plugin files
	 *
	 * @param  Not param
	 */
	public function includes() {
		$files = array(
			'includes/class-wp-sms-gateway',
			'includes/class-wp-sms-settings',
			'includes/class-wp-sms-settings-pro',
			'includes/class-wp-sms-features',
			'includes/class-wp-sms-notifications',
			'includes/class-wp-sms-integrations',
			'includes/class-wp-sms-gravityforms',
			'includes/class-wp-sms-quform',
			'includes/class-wp-sms-subscribers',
			'includes/class-wp-sms-newsletter',
			'includes/class-wp-sms-widget',
			'includes/class-wp-sms-rest-api',
			'includes/class-wp-sms-version',
			'includes/class-wp-sms-privacy',
		);

		foreach ( $files as $file ) {
			include_once dirname( __FILE__ ) . '/' . $file . '.php';
		}
	}

	/**
	 * Initial plugin
	 *
	 * @param  Not param
	 */
	private function init() {
		if ( isset( $_GET['action'] ) ) {
			if ( $_GET['action'] == 'wpsms-hide-newsletter' ) {
				update_option( 'wpsms_hide_newsletter', true );
			}
		}

		if ( ! get_option( 'wpsms_hide_newsletter' ) ) {
			add_action( 'wp_sms_settings_page', array( $this, 'admin_newsletter' ) );
		}

		// Check exists require function
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		// Add plugin caps to admin role
		if ( is_admin() and is_super_admin() ) {
			$this->add_cap();
		}
	}

	/**
	 * Include admin assets
	 *
	 * @param  Not param
	 */
	public function admin_assets() {
       if (stristr(get_current_screen()->id, "wp-sms")) {
           wp_register_style('wpsms-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin.css', true, '1.3');
           wp_enqueue_style('wpsms-admin-css');

           wp_enqueue_style('wpsms-chosen-css', plugin_dir_url(__FILE__) . 'assets/css/chosen.min.css', true, '1.2.0');
           wp_enqueue_script('wpsms-chosen-js', plugin_dir_url(__FILE__) . 'assets/js/chosen.jquery.min.js', true, '1.2.0');
           wp_enqueue_script('wpsms-word-and-character-counter-js', plugin_dir_url(__FILE__) . 'assets/js/jquery.word-and-character-counter.min.js', true, '2.5.0');
           wp_enqueue_script('wpsms-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin.js', true, '1.2.0');
       }
	}

	/**
	 * Include front table
	 *
	 * @param  Not param
	 */
	public function front_assets() {
		wp_register_style( 'wpsms-subscribe', plugin_dir_url( __FILE__ ) . 'assets/css/subscribe.css', true, '1.1' );
		wp_enqueue_style( 'wpsms-subscribe' );
	}

    /**
     * Show Admin Wordpress Ui Notice
     *
     * @param string   $text  where Show Text Notification
     * @param string  $model  Type Of Model from list : error / warning / success / info
     * @param boolean  $close_button  Check Show close Button Or false for not
     * @param  boolean $echo  Check Echo or return in function
     * @param string  $style_extra  add extra Css Style To Code
     *
     * @author Mehrshad Darzi
     * @return string Wordpress html Notice code
     */
    public static function wp_admin_notice( $text, $model ="info" , $close_button = true, $echo = true, $style_extra = 'padding:12px;' ) {
        $text = '
        <div class="notice notice-'.$model.''.($close_button ===true ? " is-dismissible" : "").'">
           <div style="'.$style_extra.'">'.$text.'</div>
        </div>
        ';
        if($echo)  { echo $text; } else { return $text; }
    }

	/**
	 * Admin bar plugin
	 *
	 * @param  Not param
	 */
	public function adminbar() {
		global $wp_admin_bar, $wpsms_option;

		if ( is_super_admin() && is_admin_bar_showing() ) {
			if ( get_option( 'wp_last_credit' ) && isset( $wpsms_option['account_credit_in_menu'] ) ) {
				$wp_admin_bar->add_menu( array(
					'id'    => 'wp-credit-sms',
					'title' => '<span class="ab-icon"></span>' . get_option( 'wp_last_credit' ),
					'href'  => $this->admin_url . '/admin.php?page=wp-sms-settings',
				) );
			}

			$wp_admin_bar->add_menu( array(
				'id'     => 'wp-send-sms',
				'parent' => 'new-content',
				'title'  => __( 'SMS', 'wp-sms' ),
				'href'   => $this->admin_url . '/admin.php?page=wp-sms'
			) );
		}
	}

	/**
	 * Dashboard glance plugin
	 *
	 * @param  Not param
	 */
	public function dashboard_glance() {
		$subscribe = $this->db->get_var( "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes" );
		echo "<li class='wpsms-subscribe-count'><a href='" . $this->admin_url . "admin.php?page=wp-sms-subscribers'>" . sprintf( __( '%s Subscriber', 'wp-sms' ), $subscribe ) . "</a></li>";
		echo "<li class='wpsms-credit-count'><a href='" . $this->admin_url . "admin.php?page=wp-sms-settings&tab=web-service'>" . sprintf( __( '%s SMS Credit', 'wp-sms' ), get_option( 'wp_last_credit' ) ) . "</a></li>";
	}

	/**
	 * Administrator admin_menu
	 *
	 * @param  Not param
	 */
	public function admin_menu() {
        global $wpsms_option;
		add_menu_page( __( 'SMS', 'wp-sms' ), __( 'SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array(
			$this,
			'send_page'
		), 'dashicons-email-alt' );
		add_submenu_page( 'wp-sms', __( 'Send SMS', 'wp-sms' ), __( 'Send SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array(
			$this,
			'send_page'
		) );
		add_submenu_page( 'wp-sms', __( 'Outbox', 'wp-sms' ), __( 'Outbox', 'wp-sms' ), 'wpsms_outbox', 'wp-sms-outbox', array(
			$this,
			'outbox_page'
		) );
		add_submenu_page( 'wp-sms', __( 'Subscribers', 'wp-sms' ), __( 'Subscribers', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers', array(
			$this,
			'subscribe_page'
		) );
		add_submenu_page( 'wp-sms', __( 'Groups', 'wp-sms' ), __( 'Groups', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers-group', array(
			$this,
			'groups_page'
		) );
		if (isset($wpsms_option['gdpr_compliance']) and $wpsms_option['gdpr_compliance'] ==1) {
            add_submenu_page( 'wp-sms', __( 'Privacy', 'wp-sms' ), __( 'Privacy', 'wp-sms' ), 'manage_options', 'wp-sms-subscribers-privacy', array(
                $this,
                'privacy_page'
            ) );
        }
	}

	public function meta_links( $links, $file ) {
		if ( $file == 'wp-sms/wp-sms.php' ) {
			$rate_url = 'http://wordpress.org/support/view/plugin-reviews/wp-sms?rate=5#postform';
			$links[]  = '<a href="' . $rate_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __( 'Click here to rate and review this plugin on WordPress.org', 'wp-sms' ) . '">' . __( 'Rate this plugin', 'wp-sms' ) . '</a>';

			$newsletter_url = WP_SMS_SITE . '/newsletter';
			$links[]        = '<a href="' . $newsletter_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __( 'Click here to rate and review this plugin on WordPress.org', 'wp-sms' ) . '">' . __( 'Subscribe to our Email Newsletter', 'wp-sms' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Register widget
	 */
	public function register_widget() {
		register_widget( 'WPSMS_Widget' );
	}

	/**
	 * Modify destination number
	 *
	 * @param  array $to
	 *
	 * @return array/string
	 */
	public function modify_bulk_send( $to ) {
		if ( ! $this->sms->bulk_send ) {
			return array( $to[0] );
		}

		return $to;
	}

    /**
     * Custom Query for Get All User Mobile in special Role
     */
    public function get_query_user_mobile($user_query){
        global $wpdb;
        $user_query->query_fields .= ', m1.meta_value AS mobile ';
        $user_query->query_from .= " JOIN {$wpdb->usermeta} m1 ON (m1.user_id = {$wpdb->users}.ID AND m1.meta_key = 'mobile') ";
        return $user_query;
    }

	/**
	 * Sending sms admin page
	 *
	 * @param  Not param
	 */
	public function send_page() {
		global $wpsms_option;

		$get_group_result = $this->db->get_results( "SELECT * FROM `{$this->tb_prefix}sms_subscribes_group`" );
		$get_users_mobile = $this->db->get_col( "SELECT `meta_value` FROM `{$this->tb_prefix}usermeta` WHERE `meta_key` = 'mobile'" );

        //Get User Mobile List by Role
        if(!empty($wpsms_option['add_mobile_field']) and $wpsms_option['add_mobile_field'] ==1) {
            $wpsms_list_of_role = array();
            foreach ( wp_roles()->role_names as $key_item => $val_item ) {
                $wpsms_list_of_role[$key_item] = array(
                    "name" => $val_item,
                    "count" => count( get_users( array('meta_key' => 'mobile', 'meta_value'   => '', 'meta_compare' => '!=', 'role' => $key_item, 'fields' => 'ID')) )
                );
            }
        }

        if ( isset($wpsms_option['gateway_name']) && ! $this->sms->GetCredit() ) {
			echo '<br><div class="update-nag">' . __( 'You should have sufficient funds for sending sms in the account', 'wp-sms' ) . '</div>';

			return;
		} else if ( ! isset($wpsms_option['gateway_name']) ) {
            echo '<br><div class="update-nag">' . __( 'You should choose and configuration your gateway in the Setting page', 'wp-sms' ) . '</div>';

            return;
		}

		if ( isset( $_POST['SendSMS'] ) ) {
			if ( $_POST['wp_get_message'] ) {
				if ( $_POST['wp_send_to'] == "wp_subscribe_username" ) {
					if ( $_POST['wpsms_group_name'] == 'all' ) {
						$this->sms->to = $this->db->get_col( "SELECT mobile FROM {$this->tb_prefix}sms_subscribes WHERE `status` = '1'" );
					} else {
						$this->sms->to = $this->db->get_col( "SELECT mobile FROM {$this->tb_prefix}sms_subscribes WHERE `status` = '1' AND `group_ID` = '" . $_POST['wpsms_group_name'] . "'" );
					}
				} else if ( $_POST['wp_send_to'] == "wp_users" ) {
					$this->sms->to = $get_users_mobile;
				} else if ( $_POST['wp_send_to'] == "wp_tellephone" ) {
					$this->sms->to = explode( ",", $_POST['wp_get_number'] );
				} else if ( $_POST['wp_send_to'] == "wp_role" ) {
				   $to = array();
                   add_action('pre_user_query', array($this, 'get_query_user_mobile'));
				   $list = get_users( array('meta_key' => 'mobile', 'meta_value' => '', 'meta_compare' => '!=', 'role' => $_POST['wpsms_group_role'], 'fields' => 'all'));
                   remove_action('pre_user_query', array($this, 'get_query_user_mobile'));
				   foreach($list as $user) {
                        $to[] = $user->mobile;
                    }
                    $this->sms->to = $to;
                }

				$this->sms->from = $_POST['wp_get_sender'];
				$this->sms->msg  = $_POST['wp_get_message'];

				if ( isset( $_POST['wp_flash'] ) ) {
					$this->sms->isflash = true;
				} else {
					$this->sms->isflash = false;
				}

				// Send sms
				$response = $this->sms->SendSMS();

				if ( is_wp_error( $response ) ) {
					if ( is_array( $response->get_error_message() ) ) {
						$response = print_r( $response->get_error_message(), 1 );
					} else {
						$response = $response->get_error_message();
					}

					echo "<div class='error'><p>" . sprintf( __( '<strong>SMS was not delivered! results received:</strong> %s', 'wp-sms' ), $response ) . "</p></div>";
				} else {
					echo "<div class='updated'><p>" . __( 'SMS was sent with success', 'wp-sms' ) . "</p></div>";
					update_option( 'wp_last_credit', $this->sms->GetCredit() );
				}
			} else {
				echo "<div class='error'><p>" . __( 'Please enter a message', 'wp-sms' ) . "</p></div>";
			}
		}

		include_once dirname( __FILE__ ) . "/includes/templates/send/send-sms.php";
	}

	/**
	 * Outbox sms admin page
	 *
	 * @param  Not param
	 */
	public function outbox_page() {
		include_once dirname( __FILE__ ) . '/includes/class-wp-sms-outbox.php';

		//Create an instance of our package class...
		$list_table = new WP_SMS_Outbox_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once dirname( __FILE__ ) . "/includes/templates/outbox/outbox.php";
	}

	/**
	 * Subscribe admin page
	 *
	 * @param  Not param
	 */
	public function subscribe_page() {

		if ( isset( $_GET['action'] ) ) {
			// Add subscriber page
			if ( $_GET['action'] == 'add' ) {
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/add-subscriber.php";

				if ( isset( $_POST['wp_add_subscribe'] ) ) {
					$result = $this->subscribe->add_subscriber( $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'] );
					echo $this->notice_result( $result['result'], $result['message'] );
				}

				return;
			}

			// Edit subscriber page
			if ( $_GET['action'] == 'edit' ) {
				if ( isset( $_POST['wp_update_subscribe'] ) ) {
					$result = $this->subscribe->update_subscriber( $_GET['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'], $_POST['wpsms_subscribe_status'] );
					echo $this->notice_result( $result['result'], $result['message'] );
				}

				$get_subscribe = $this->subscribe->get_subscriber( $_GET['ID'] );
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/edit-subscriber.php";

				return;
			}

			// Import subscriber page
			if ( $_GET['action'] == 'import' ) {
				include_once dirname( __FILE__ ) . "/import.php";
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/import.php";

				return;
			}

			// Export subscriber page
			if ( $_GET['action'] == 'export' ) {
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/export.php";

				return;
			}
		}

		include_once dirname( __FILE__ ) . '/includes/class-wp-sms-subscribers-table.php';

		//Create an instance of our package class...
		$list_table = new WP_SMS_Subscribers_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once dirname( __FILE__ ) . "/includes/templates/subscribe/subscribes.php";
	}

	/**
	 * Subscribe groups admin page
	 *
	 * @param  Not param
	 */
	public function groups_page() {

		if ( isset( $_GET['action'] ) ) {
			// Add group page
			if ( $_GET['action'] == 'add' ) {
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/add-group.php";
				if ( isset( $_POST['wp_add_group'] ) ) {
					$result = $this->subscribe->add_group( $_POST['wp_group_name'] );
					echo $this->notice_result( $result['result'], $result['message'] );
				}

				return;
			}

			// Manage group page
			if ( $_GET['action'] == 'edit' ) {
				if ( isset( $_POST['wp_update_group'] ) ) {
					$result = $this->subscribe->update_group( $_GET['ID'], $_POST['wp_group_name'] );
					echo $this->notice_result( $result['result'], $result['message'] );
				}

				$get_group = $this->subscribe->get_group( $_GET['ID'] );
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/edit-group.php";

				return;
			}
		}

		include_once dirname( __FILE__ ) . '/includes/class-wp-sms-groups-table.php';

		//Create an instance of our package class...
		$list_table = new WP_SMS_Subscribers_Groups_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once dirname( __FILE__ ) . "/includes/templates/subscribe/groups.php";
	}

	/**
	 * Privacy admin page
	 *
	 * @param  Not param
	 */
	public function privacy_page() {
		WP_SMS_Privacy::get()->show_page_privacy();
	}

	/**
	 * Show message notice in admin
	 *
	 * @param $result
	 * @param $message
	 *
	 * @return string|void
	 * @internal param param $Not
	 */
	public function notice_result( $result, $message ) {
		if ( empty( $result ) ) {
			return;
		}

		if ( $result == 'error' ) {
			return '<div class="updated settings-error notice error is-dismissible"><p><strong>' . $message . '</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . __( 'Close', 'wp-sms' ) . '</span></button></div>';
		}

		if ( $result == 'update' ) {
			return '<div class="updated settings-update notice is-dismissible"><p><strong>' . $message . '</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . __( 'Close', 'wp-sms' ) . '</span></button></div>';
		}
	}

	/**
	 * Admin newsletter
	 *
	 * @param  Not param
	 */
	public function admin_newsletter() {
		include_once dirname( __FILE__ ) . '/includes/templates/wp-sms-admin-newsletter.php';
	}

	/**
	 * Shortcodes plugin
	 *
	 * @param $atts
	 * @param null $content
	 *
	 * @internal param param $Not
	 */
	public function shortcode( $atts, $content = null ) {

	}
}