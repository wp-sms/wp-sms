<?php
/**
 * WP SMS notifications class
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */

class WP_SMS_Notifications {

	public $sms;
	public $date;
	public $options;

	public function __construct() {
		global $wpsms_option, $sms, $wp_version;

		$this->sms = $sms;
		$this->date = WP_SMS_CURRENT_DATE;
		$this->options = $wpsms_option;

		if( isset($this->options['notif_publish_new_post']) ) {
			add_action('add_meta_boxes', array(&$this, 'notification_meta_box'));
			add_action('transition_post_status', array(&$this, 'new_post'), 10, 3);
		}

		// Wordpress new version
		if( isset($this->options['notif_publish_new_wpversion']) ) {
			$update = get_site_transient('update_core');
			$update = $update->updates;

			if( isset($update[1])) {
				if($update[1]->current > $wp_version and $this->sms->GetCredit()) {
					if(get_option('wp_last_send_notification') == false) {
						$this->sms->to = array( $this->options['admin_mobile_number'] );
						$this->sms->msg = sprintf(__('WordPress %s is available! Please update now', 'wp-sms'), $update[1]->current);
						$this->sms->SendSMS();
						
						update_option('wp_last_send_notification', true);
					}
				} else {
					update_option('wp_last_send_notification', false);
				}
			}
			
		}

		if( isset($this->options['notif_register_new_user']) ) {
			add_action('user_register', array(&$this, 'new_user'), 10, 1);
		}

		if( isset($this->options['notif_new_comment']) ) {
			add_action('wp_insert_comment', array(&$this, 'new_comment'), 99, 2);
		}

		if( isset($this->options['notif_user_login']) ) {
			add_action('wp_login', array(&$this, 'login_user'), 99, 2);
		}
	}

	public function notification_meta_box() {
		add_meta_box('subscribe-meta-box', __('SMS', 'wp-sms'), array(&$this, 'notification_meta_box_handler'), 'post', 'normal', 'high');
	}

	public function notification_meta_box_handler($post) {
		global $wpdb, $table_prefix;

		$get_group_result = $wpdb->get_results("SELECT * FROM `{$table_prefix}sms_subscribes_group`");
		$username_active = $wpdb->query("SELECT * FROM {$table_prefix}sms_subscribes WHERE status = '1'");
		include_once dirname( __FILE__ ) . "/templates/wp-sms-meta-box.php";
	}

	public function new_post($wp_sms_new_status = NULL, $wp_sms_old_status = NULL, $post = NULL) {
		if($_REQUEST['wps_send_subscribe'] == 'yes') {
			global $wpdb, $table_prefix;
			
			if($_REQUEST['wps_subscribe_group'] == 'all') {
				$this->sms->to = $wpdb->get_col("SELECT mobile FROM {$table_prefix}sms_subscribes");
			} else {
				$this->sms->to = $wpdb->get_col("SELECT mobile FROM {$table_prefix}sms_subscribes WHERE group_ID = '{$_REQUEST['wps_subscribe_group']}'");
			}

			$template_vars = array(
				'%title_post%' => get_the_title($post->ID),
				'%url_post%' => wp_get_shortlink($post->ID),
				'%date_post%' => get_post_time('Y-m-d', true, $post->ID, true),
			);
			
			$message = str_replace(array_keys($template_vars), array_values($template_vars), $_REQUEST['wpsms_text_template']);
			
			$this->sms->msg = $message;
			$this->sms->SendSMS();
		}

		return $post;
	}

	// Register new user
	public function new_user($user_id) {

		$user = get_userdata($user_id);
		$template_vars = array(
			'user_login'	=> $user->user_login,
			'user_email'	=> $user->user_email,
			'date_register'	=> $this->date,
		);
		
		// Send SMS to admin
		$this->sms->to = array( $this->options['admin_mobile_number'] );
		$string = $this->options['notif_register_new_user_admin_template'];
		$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
		$this->sms->msg = $final_message;
		$this->sms->SendSMS();
		
		// Send SMS to user register
		if( isset($user->mobile) ) {
			$this->sms->to = array($user->mobile);
			$string = $this->options['notif_register_new_user_template'];
			$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
			$this->sms->msg = $final_message;
			$this->sms->SendSMS();
		}
	}

	// New Comment
	public function new_comment($comment_id, $comment_smsect){

		if($comment_smsect->comment_type == 'order_note')
			return;
		
		if($comment_smsect->comment_type == 'edd_payment_note')
			return;
		
		$this->sms->to = array( $this->options['admin_mobile_number'] );
		$string = $this->options['notif_new_comment_template'];
		$template_vars = array(
			'comment_author'		=> $comment_smsect->comment_author,
			'comment_author_email'	=> $comment_smsect->comment_author_email,
			'comment_author_url'	=> $comment_smsect->comment_author_url,
			'comment_author_IP'		=> $comment_smsect->comment_author_IP,
			'comment_date'			=> $comment_smsect->comment_date,
			'comment_content'		=> $comment_smsect->comment_content
		);
		$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
		$this->sms->msg = $final_message;
		$this->sms->SendSMS();
	}

	// Login user
	public function login_user($username_login, $username){
		$this->sms->to = array( $this->options['admin_mobile_number'] );
		$string = $this->options['notif_user_login_template'];
		$template_vars = array(
			'username_login'	=> $username->user_login,
			'display_name'	=> $username->display_name
		);
		$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
		$this->sms->msg = $final_message;
		$this->sms->SendSMS();
	}

}

new WP_SMS_Notifications();