<?php
/*
Plugin Name: WP SMS
Plugin URI: http://wpsms.veronalabs.com/
Description: A complete wordpress plugin to send sms with a high capability.
Version: 4.0.0
Author: Mostafa Soufi
Author URI: http://mostafa-soufi.ir/
Text Domain: wp-sms
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin defines
 */
define('WP_SMS_VERSION', '3.2.3');
define('WP_SMS_PLUGIN_DIR', plugin_dir_url(__FILE__));
define('WP_SMS_ADMIN_URL', get_admin_url());
define('WP_SMS_SITE_URL', 'http://wpsms.veronalabs.com/');
define('WP_SMS_MOBILE_REGEX', '/^[\+|\(|\)|\d|\- ]*$/');

// WP SMS Plugin Class
class WP_SMS {
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
	 * Current date/time
	 *
	 * @var string
	 */
	public $date;
	
	/**
	 * Wordpress Database
	 *
	 * @var string
	 */
	public $db;
	
	/**
	 * Wordpress Table prefix
	 *
	 * @var string
	 */
	public $tb_prefix;

	/**
	 * WP-SMS Options
	 * @var array
	 */
	public $options = array();

	public $gateway;
	
	/**
	 * Constructors plugin
	 *
	 */
	public function __construct() {

		// Global variables
		global $sms, $wpdb, $table_prefix, $date, $wps_options;

		$this->sms = $sms;
		$this->date = $date;
		$this->db = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->date = date('Y-m-d H:i:s' ,current_time('timestamp', 0));
		
		__('WP SMS', 'wp-sms');
		__('A complete wordpress plugin to send sms with a high capability.', 'wp-sms');
		
		$this->init_hooks();
		$this->include_files();
		$this->notifications();

		$this->options = new WP_SMS_Settings();
		$this->gateway = new WP_SMS_Gateway();
		$this->subscribe = new WP_SMS_Subscriptions();

	}

	/**
	 * Includes plugin files
	 *
	 * @param  Not param
	 */
	public function include_files() {
		$files = array(
			'version',
			'features',
			'widget',
			'newslleter',
			'includes/functions',
			'includes/classes/wp-sms-subscribers.class',
			'includes/class-wpsms-settings-api',
			'includes/class-wpsms-gateway',
			'includes/class-wpsms-notice',
			'admin/settings',
		);

		foreach($files as $file) {
			include_once dirname( __FILE__ ) . '/' . $file . '.php';
		}
	}
	
	/**
	 * Notification plugin with another system
	 *
	 * @param  Not param
	 */
	public function notifications() {
		$files = array(
			'wp',
			'cf7',
			'wc',
			'edd',
		);
		
		foreach($files as $file) {
			include_once dirname( __FILE__ ) . '/includes/notifications/' . $file . '/index.php';
		}
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 4.0.0
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( &$this, 'install' ) );
		register_activation_hook( __FILE__, array( &$this, 'add_cap' ) );
		
		load_plugin_textdomain('wp-sms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

		add_action('admin_enqueue_scripts', array(&$this, 'admin_assets'));
		add_action('wp_enqueue_scripts', array(&$this, 'front_assets'));
		
		add_action('admin_bar_menu', array($this, 'adminbar'));
		add_action('dashboard_glance_items', array($this, 'dashboard_glance'));
		add_action('admin_menu', array(&$this, 'menu'));
	}

	/**
	 * Creating plugin tables
	 *
	 * @param  Not param
	 */
	static function install() {
		global $wp_sms_db_version;
		
		include_once dirname( __FILE__ ) . '/install.php';
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		dbDelta($create_sms_subscribes);
		dbDelta($create_sms_subscribes_group);
		dbDelta($create_sms_send);
		
		add_option('wp_sms_db_version', WP_SMS_VERSION);
	}

	/**
	 * Adding new capability in the plugin
	 *
	 * @param  Not param
	 */
	public function add_cap() {
		// gets the administrator role
		$role = get_role( 'administrator' );
		
		$role->add_cap( 'wpsms_sendsms' );
		$role->add_cap( 'wpsms_outbox' );
		$role->add_cap( 'wpsms_subscribers' );
		$role->add_cap( 'wpsms_subscribe_groups' );
		$role->add_cap( 'wpsms_setting' );
	}

	/**
	 * Include admin assets
	 *
	 * @param  Not param
	 */
	public function admin_assets() {
		wp_register_style('wpsms-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', true, '1.1');
		wp_enqueue_style('wpsms-admin');
		
		wp_enqueue_style('chosen', plugin_dir_url(__FILE__) . 'assets/css/chosen.min.css', true, '1.2.0');
		wp_enqueue_script('chosen', plugin_dir_url(__FILE__) . 'assets/js/chosen.jquery.min.js', true, '1.2.0');
		
		if( get_option('wp_call_jquery') )
			wp_enqueue_script('jquery');
	}

	/**
	 * Include front table
	 *
	 * @param  Not param
	 */
	public function front_assets() {
		wp_register_style('wpsms-subscribe', plugin_dir_url(__FILE__) . 'assets/css/subscribe.css', true, '1.1');
		wp_enqueue_style('wpsms-subscribe');
	}
	
	/**
	 * Admin bar plugin
	 *
	 * @param  Not param
	 */
	public function adminbar() {
		global $wp_admin_bar;
		
		if(is_super_admin() && is_admin_bar_showing()) {
			if(get_option('wp_last_credit') && get_option('wp_sms_cam')) {
				$wp_admin_bar->add_menu(array(
					'id'		=>	'wp-credit-sms',
					'title'		=>	'<span class="ab-icon"></span>'.get_option('wp_last_credit'),
					'href'		=>	$this->admin_url.'/admin.php?page=wp-sms-settings',
				));
			}
			
			$wp_admin_bar->add_menu(array(
				'id'		=>	'wp-send-sms',
				'parent'	=>	'new-content',
				'title'		=>	__('SMS', 'wp-sms'),
				'href'		=>	$this->admin_url.'/admin.php?page=wp-sms'
			));
		}
	}
	
	/**
	 * Dashboard glance plugin
	 *
	 * @param  Not param
	 */
	public function dashboard_glance() {
		$subscribe = $this->db->get_var("SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes");
		echo "<li class='wpsms-subscribe-count'><a href='".$this->admin_url."admin.php?page=wp-sms-subscribers'>".sprintf(__('%s Subscriber', 'wp-sms'), $subscribe)."</a></li>";
		echo "<li class='wpsms-credit-count'><a href='".$this->admin_url."admin.php?page=wp-sms-settings&tab=web-service'>".sprintf(__('%s SMS Credit', 'wp-sms'), get_option('wp_last_credit'))."</a></li>";
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
	 * @param  Not param
	 */
	public function shortcode( $atts, $content = null ) {
		
	}
	
	/**
	 * Administrator menu
	 *
	 * @param  Not param
	 */
	public function menu() {
		add_menu_page(__('Wordpress SMS', 'wp-sms'), __('Wordpress SMS', 'wp-sms'), 'wpsms_sendsms', 'wp-sms', array(&$this, 'send_page'), 'dashicons-email-alt');
		add_submenu_page('wp-sms', __('Send SMS', 'wp-sms'), __('Send SMS', 'wp-sms'), 'wpsms_sendsms', 'wp-sms', array(&$this, 'send_page'));
		add_submenu_page('wp-sms', __('Outbox', 'wp-sms'), __('Outbox', 'wp-sms'), 'wpsms_outbox', 'wp-sms-outbox', array(&$this, 'outbox_page'));
		add_submenu_page('wp-sms', __('Subscribers', 'wp-sms'), __('Subscribers', 'wp-sms'), 'wpsms_subscribers', 'wp-sms-subscribers', array(&$this, 'subscribe_page'));
		add_submenu_page('wp-sms', __('Subscribers Group', 'wp-sms'), __('Subscribers Group', 'wp-sms'), 'wpsms_subscribe_groups', 'wp-sms-subscribers-group', array(&$this, 'groups_page'));
	}
	
	/**
	 * Sending sms admin page
	 *
	 * @param  Not param
	 */
	public function send_page() {
		wp_enqueue_script('functions', plugin_dir_url(__FILE__) . 'assets/js/functions.js', true, '1.0');
		
		$get_group_result = $this->db->get_results("SELECT * FROM `{$this->tb_prefix}sms_subscribes_group`");
		$get_users_mobile = $this->db->get_col("SELECT `meta_value` FROM `{$this->tb_prefix}usermeta` WHERE `meta_key` = 'mobile'");
		
		if(get_option('wp_webservice') && !$this->sms->GetCredit()) {
			$get_bloginfo_url = WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-settings&tab=web-service";
			echo '<br><div class="update-nag">'.sprintf(__('Your credit for send sms is low!', 'wp-sms'), $get_bloginfo_url).'</div>';
			return;
		} else if(!get_option('wp_webservice')) {
			return;
		}
		
		if(isset($_POST['SendSMS'])) {
			if($_POST['wp_get_message']) {
				if($_POST['wp_send_to'] == "wp_subscribe_username") {
					if( $_POST['wpsms_group_name'] == 'all' ) {
						$this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->tb_prefix}sms_subscribes WHERE `status` = '1'");
					} else {
						$this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->tb_prefix}sms_subscribes WHERE `status` = '1' AND `group_ID` = '".$_POST['wpsms_group_name']."'");
					}
				} else if($_POST['wp_send_to'] == "wp_users") {
					$this->sms->to = $get_users_mobile;
				} else if($_POST['wp_send_to'] == "wp_tellephone") {
					$this->sms->to = explode(",", $_POST['wp_get_number']);
				}
				
				$this->sms->msg = $_POST['wp_get_message'];

				if($_POST['wp_flash'] == "true") {
					$this->sms->isflash = true;
				}
				elseif($_POST['wp_flash'] == "false") {
					$this->sms->isflash = false;
				}

				if($this->sms->SendSMS()) {
					$to = implode($this->db->get_col("SELECT mobile FROM {$tb_prefix}sms_subscribes"), ",");
					echo "<div class='updated'><p>" . __('SMS was sent with success', 'wp-sms') . "</p></div>";
					update_option('wp_last_credit', $this->sms->GetCredit());
				}
			} else {
				echo "<div class='error'><p>" . __('Please enter a message', 'wp-sms') . "</p></div>";
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
		include_once dirname( __FILE__ ) . '/includes/wp-sms-outbox.php';
		
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
		
		if(isset($_GET['action'])) {
			// Add subscriber page
			if($_GET['action'] == 'add') {
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/add-subscriber.php";
				
				if(isset($_POST['wp_add_subscribe'])) {
					$result = $this->subscribe->add_subscriber($_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name']);
					echo $this->notice_result($result['result'], $result['message']);
				}
				
				return;
			}
			
			// Edit subscriber page
			if($_GET['action'] == 'edit') {
				if(isset($_POST['wp_update_subscribe'])) {
					$result = $this->subscribe->update_subscriber($_GET['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'], $_POST['wpsms_subscribe_status']);
					echo $this->notice_result($result['result'], $result['message']);
				}
				
				$get_subscribe = $this->subscribe->get_subscriber($_GET['ID']);
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/edit-subscriber.php";
				
				return;
			}
			
			// Import subscriber page
			if($_GET['action'] == 'import') {
				include_once dirname( __FILE__ ) . "/import.php";
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/import.php";
				
				return;
			}
			
			// Export subscriber page
			if($_GET['action'] == 'export') {
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/export.php";
				
				return;
			}
		}
		
		include_once dirname( __FILE__ ) . '/includes/wp-sms-subscribers.php';
		
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
		
		if(isset($_GET['action'])) {
			// Add group page
			if($_GET['action'] == 'add') {
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/add-group.php";
				if(isset($_POST['wp_add_group'])) {
					$result = $this->subscribe->add_group($_POST['wp_group_name']);
					echo $this->notice_result($result['result'], $result['message']);
				}
				
				return;
			}
			
			// Manage group page
			if($_GET['action'] == 'edit') {
				if(isset($_POST['wp_update_group'])) {
					$result = $this->subscribe->update_group($_GET['ID'], $_POST['wp_group_name']);
					echo $this->notice_result($result['result'], $result['message']);
				}
				
				$get_group = $this->subscribe->get_group($_GET['ID']);
				include_once dirname( __FILE__ ) . "/includes/templates/subscribe/edit-group.php";
				
				return;
			}
		}
		
		include_once dirname( __FILE__ ) . '/includes/wp-sms-subscribers-groups.php';
		
		//Create an instance of our package class...
		$list_table = new WP_SMS_Subscribers_Groups_List_Table();
		
		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();
		
		include_once dirname( __FILE__ ) . "/includes/templates/subscribe/groups.php";
	}

}

// Create object of plugin
$wp_sms = new WP_SMS;

/*$wp_sms->gateway->to = array('00000000000');
$wp_sms->gateway->from = '982188384690';
$wp_sms->gateway->message = 'Hello';
$result = $wp_sms->gateway->send();

print_r($result);

if( is_wp_error( $result ) ) {
	echo $result->get_error_message();
}*/