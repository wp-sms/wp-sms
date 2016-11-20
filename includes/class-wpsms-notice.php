<?php
/**
 * Notices displayed near the top of admin pages
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_AdminNotices {

	/**
	 * Success notice
	 * @var string
	 */
	static $success_message;

	/**
	 * Error notice
	 * @var string
	 */
	static $error_message;

	/**
	 * Message notice method
	 * @param  string $message Notice message
	 */
	public static function message($message) {
		self::$success_message = $message;
		add_action( 'admin_notices', array( 'WP_SMS_AdminNotices', 'render_message' ) );
	}

	/**
	 * Error notice method
	 * @param  string $error Notice error
	 */
	public static function error($message) {
		self::$error_message = $message;
		add_action( 'admin_notices', array( 'WP_SMS_AdminNotices', 'render_message' ) );
	}

	/**
	 * Display notice near the top of admin pages
	 * @return string HTML message
	 */
	public static function render_message() {
		if( self::$success_message ) {
			echo '<div class="notice notice-success is-dismissible"><p>'.self::$success_message.'</p></div>';
		} elseif( self::$error_message ) {
			echo '<div class="notice notice-error is-dismissible"><p>'.self::$error_message.'</p></div>';
		}
	}
}