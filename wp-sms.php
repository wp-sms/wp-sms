<?php
/*
Plugin Name: WP SMS
Plugin URI: http://wp-sms-plugin.com/
Description: A complete wordpress plugin to send sms with a high capability.
Version: 3.1.3
Author: Mostafa Soufi
Author URI: http://mostafa-soufi.ir/
Text Domain: wp-sms
*/
define('WP_SMS_VERSION', '3.1.3');
define('WP_SMS_DIR_PLUGIN', plugin_dir_url(__FILE__));
define('WP_ADMIN_URL', get_admin_url());
define('WP_SMS_SITE', 'http://wp-sms-plugin.com');
define('WP_SMS_MOBILE_REGEX', '/^[\+|\(|\)|\d|\- ]*$/');

$date = date('Y-m-d H:i:s' ,current_time('timestamp', 0));
load_plugin_textdomain('wp-sms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

// Use default gateway class if webservice not active
if(!class_exists('WP_SMS')) {
	include_once dirname( __FILE__ ) . '/includes/classes/webservice/default.class.php';
	$sms = new Default_Gateway;
}

// SMS Gateway plugin
if(get_option('wp_webservice')) {
	$webservice = get_option('wp_webservice');
	
	include_once dirname( __FILE__ ) . '/includes/classes/wp-sms.class.php';
	
	if(is_file(dirname( __FILE__ ) . '/includes/classes/webservice/'.$webservice.'.class.php')) {
		include_once dirname( __FILE__ ) . '/includes/classes/webservice/'.$webservice.'.class.php';
	} else {
		include_once( ABSPATH . 'wp-content/plugins/wp-sms-pro/gateway/gateways/'.$webservice.'.class.php' );
	}
	
	$sms = new $webservice;
	
	$sms->username = get_option('wp_username');
	$sms->password = get_option('wp_password');
	
	if($sms->has_key && get_option('wps_key')) {
		$sms->has_key = get_option('wps_key');
	}
	
	// Added help to gateway if have it.
	if($sms->help) {
		function wps_gateway_help() {
			global $sms;
			echo '<p class="description">'.$sms->help.'</p>';
		}
		add_action('wp_after_sms_gateway', 'wps_gateway_help');
	}
	
	if($sms->unitrial == true) {
		$sms->unit = __('Credit', 'wp-sms');
	} else {
		$sms->unit = __('SMS', 'wp-sms');
	}
	
	$sms->from = get_option('wp_number');
}

// Get WP SMS Option values
$wps_options = get_option('wpsms');

// Create object of plugin
$WP_SMS_Plugin = new WP_SMS_Plugin;
register_activation_hook( __FILE__, array( 'WP_SMS_Plugin', 'install' ) );

// WP SMS Plugin Class
class WP_SMS_Plugin {
	/**
	 * Wordpress Admin url
	 *
	 * @var string
	 */
	public $admin_url = WP_ADMIN_URL;
	
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
	protected $db;
	
	/**
	 * Wordpress Table prefix
	 *
	 * @var string
	 */
	protected $tb_prefix;
	
	/**
	 * Constructors plugin
	 *
	 * @param  Not param
	 */
	public function __construct() {
		global $sms, $wpdb, $table_prefix, $date, $wps_options;
		
		$this->sms = $sms;
		$this->date = $date;
		$this->db = $wpdb;
		$this->tb_prefix = $table_prefix;
		
		__('WP SMS', 'wp-sms');
		__('A complete wordpress plugin to send sms with a high capability.', 'wp-sms');
		
		$this->includes();
		$this->notifications();
		$this->activity();
		
		$this->subscribe = new WP_SMS_Subscriptions();
		
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
		
		// Delete notification new wp_version option
		delete_option('wp_notification_new_wp_version');
		
		// If this is a first time install or an upgrade and we've added options, set some intelligent defaults.
		if( get_option('wps_access_level') === FALSE ) { update_option('wps_access_level', 'manage_options'); }
	}
	
	/**
	 * Includes plugin files
	 *
	 * @param  Not param
	 */
	public function includes() {
		$files = array(
			'version',
			'features',
			'widget',
			'newslleter',
			'includes/functions',
			'includes/classes/wp-sms-subscribers.class',
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
	 * Activity plugin
	 *
	 * @param  Not param
	 */
	private function activity() {
		if(!get_option('wp_username') || !get_option('wp_password'))
			add_action('admin_notices', array($this, 'admin_notices'));
		
		if(!get_option('wp_sms_mcc'))
			update_option('wp_sms_mcc', '09');
		
		if(isset($_GET['action'])) {
			if($_GET['action'] == 'wpsms-hide-newsletter') {
				update_option('wpsms_hide_newsletter', true);
			}
		}
		
		if(!get_option('wpsms_hide_newsletter')) {
			add_action('wp_sms_settings_page', array(&$this, 'admin_newsletter'));
		}
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
	 * Admin notieces plugin
	 *
	 * @param  Not param
	 */
	public function admin_notices() {
		$get_bloginfo_url = get_admin_url() . "admin.php?page=wp-sms-settings&tab=web-service";
		echo '<br><div class="update-nag">'.sprintf(__('For Activate WP SMS Plugin, please enable your <a href="%s">SMS gateway</a>.', 'wp-sms'), $get_bloginfo_url).'</div>';
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
		add_menu_page(__('Wordpress SMS', 'wp-sms'), __('Wordpress SMS', 'wp-sms'), get_option('wps_access_level'), 'wp-sms', array(&$this, 'send_page'), 'dashicons-email-alt');
		add_submenu_page('wp-sms', __('Send SMS', 'wp-sms'), __('Send SMS', 'wp-sms'), get_option('wps_access_level'), 'wp-sms', array(&$this, 'send_page'));
		add_submenu_page('wp-sms', __('Outbox', 'wp-sms'), __('Outbox', 'wp-sms'), 'manage_options', 'wp-sms-outbox', array(&$this, 'outbox_page'));
		add_submenu_page('wp-sms', __('Subscribers', 'wp-sms'), __('Subscribers', 'wp-sms'), 'manage_options', 'wp-sms-subscribers', array(&$this, 'subscribe_page'));
		add_submenu_page('wp-sms', __('Subscribers Group', 'wp-sms'), __('Subscribers Group', 'wp-sms'), 'manage_options', 'wp-sms-subscribers-group', array(&$this, 'groups_page'));
		add_submenu_page('wp-sms', __('Setting', 'wp-sms'), __('Setting', 'wp-sms'), 'manage_options', 'wp-sms-settings', array(&$this, 'setting_page'));
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
			$get_bloginfo_url = $this->get_admin_url . "admin.php?page=wp-sms-settings&tab=web-service";
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
		// Add subscriber page
		if($_GET['action'] == 'add') {
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/add-subscriber.php";
			
			if(isset($_POST['wp_add_subscribe'])) {
				$result = $this->subscribe->add_subscriber($_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name']);
				echo $this->notice_result($result['result'], $result['message']);
			}
		
		// Edit subscriber page
		} else if ($_GET['action'] == 'edit') {
			
			if(isset($_POST['wp_update_subscribe'])) {
				$result = $this->subscribe->update_subscriber($_GET['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'], $_POST['wpsms_subscribe_status']);
				echo $this->notice_result($result['result'], $result['message']);
			}
			
			$get_subscribe = $this->subscribe->get_subscriber($_GET['ID']);
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/edit-subscriber.php";
			
		// Import subscriber page
		} else if ($_GET['action'] == 'import') {
			include_once dirname( __FILE__ ) . "/import.php";
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/import.php";
			
		// Export subscriber page
		} else if ($_GET['action'] == 'export') {
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/export.php";
			
		// Subscriber page
		} else {
			include_once dirname( __FILE__ ) . '/includes/wp-sms-subscribers.php';
			
			//Create an instance of our package class...
			$list_table = new WP_SMS_Subscribers_List_Table();
			
			//Fetch, prepare, sort, and filter our data...
			$list_table->prepare_items();
			
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/subscribes.php";
			
		}
	}
	
	/**
	 * Subscribe groups admin page
	 *
	 * @param  Not param
	 */
	public function groups_page() {
		if($_GET['action'] == 'add') {
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/add-group.php";
			
			if(isset($_POST['wp_add_group'])) {
				$result = $this->subscribe->add_group($_POST['wp_group_name']);
				echo $this->notice_result($result['result'], $result['message']);
			}
			
		// Manage group page
		} else if ($_GET['action'] == 'edit') {
			
			if(isset($_POST['wp_update_group'])) {
				$result = $this->subscribe->update_group($_GET['ID'], $_POST['wp_group_name']);
				echo $this->notice_result($result['result'], $result['message']);
			}
			
			$get_group = $this->subscribe->get_group($_GET['ID']);
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/edit-group.php";
			
		// Subscriber page
		} else {
			include_once dirname( __FILE__ ) . '/includes/wp-sms-subscribers-groups.php';
			
			//Create an instance of our package class...
			$list_table = new WP_SMS_Subscribers_Groups_List_Table();
			
			//Fetch, prepare, sort, and filter our data...
			$list_table->prepare_items();
			
			include_once dirname( __FILE__ ) . "/includes/templates/subscribe/groups.php";
			
		}
	}
	
	/**
	 * Plugin Setting page
	 *
	 * @param  Not param
	 */
	public function setting_page() {
		$sms_page['about'] = get_admin_url() . "admin.php?page=wp-sms-settings&tab=about";
		global $sms;
		
		if(isset($_GET['tab'])) {
			switch($_GET['tab']) {
				case 'web-service':
					if(isset($_GET['action']) == 'reset') {
						delete_option('wp_webservice');
						delete_option('wp_username');
						delete_option('wp_password');
						echo '<meta http-equiv="refresh" content="0; url=admin.php?page=wp-sms-settings&tab=web-service" />';
					}
					
					include_once dirname( __FILE__ ) . "/includes/templates/settings/web-service.php";
					
					if(get_option('wp_webservice'))
						update_option('wp_last_credit', $sms->GetCredit());
					break;
				
				case 'newsletter':
					include_once dirname( __FILE__ ) . "/includes/templates/settings/newsletter.php";
				break;
				
				case 'features':
					include_once dirname( __FILE__ ) . "/includes/templates/settings/features.php";
				break;
				
				case 'notifications':
					include_once dirname( __FILE__ ) . "/includes/templates/settings/notifications.php";
				break;
				
				case 'about':
					$json_url = file_get_contents(WP_SMS_SITE . '/gateway.php?lang=' . get_locale());
					$json = json_decode($json_url);
					include_once dirname( __FILE__ ) . "/includes/templates/settings/about.php";
				break;
			}
		} else {
			include_once dirname( __FILE__ ) . "/includes/templates/settings/setting.php";
		}
	}
	
	/**
	 * Show message notice in admin
	 *
	 * @param  Not param
	 */
	public function notice_result($result, $message) {
		if(empty($result))
			return;
		
		if($result == 'error')
			return '<div class="updated settings-error notice error is-dismissible"><p><strong>'.$message.'</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">'.__('Close', 'wp-sms').'</span></button></div>';
		
		if($result == 'update')
			return '<div class="updated settings-update notice is-dismissible"><p><strong>'.$message.'</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">'.__('Close', 'wp-sms').'</span></button></div>';
	}
}