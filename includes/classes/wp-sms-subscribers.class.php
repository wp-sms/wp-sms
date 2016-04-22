<?php
/**
 * @category   class
 * @package    WP_SMS
 * @author     Mostafa Soufi <info@mostafa-soufi.ir>
 * @copyright  2015 wp-sms-plugin.com
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    1.1
 */
class WP_SMS_Subscriptions {
	/**
	 * Wordpress Dates
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
	 * Constructors
	 */
	public function __construct() {
		global $wpdb, $table_prefix;
		
		$this->date = date('Y-m-d H:i:s' ,current_time('timestamp', 0));
		$this->db = $wpdb;
		$this->tb_prefix = $table_prefix;
	}
	
	/**
	 * Add Subscriber
	 *
	 * @param  Not param
	 */
	public function add_subscriber($name, $mobile, $group_id = '') {
		
		if(empty($name) or empty($mobile))
			return array('result' => 'error', 'message' => __('Name or mobile is empty!', 'wp-sms'));
		
		$result = $this->db->insert(
			$this->tb_prefix . "sms_subscribes",
			array(
				'date'		=>	$this->date,
				'name'		=>	$name,
				'mobile'	=>	$mobile,
				'status'	=>	'1',
				'activate_key'	=>	'',
				'group_ID'	=>	$group_id,
			)
		);
		
		if($result){
			
			/**
			 * Run hook after adding subscribe.
			 *
			 * @since 3.0
			 * @param string $name name.
			 * @param string $mobile mobile.
			 */
			do_action('wp_sms_add_subscriber', $name, $mobile);
			
			return array('result' => 'update', 'message' => __('Subscriber successfully added.', 'wp-sms'));
		}
	}
	
	/**
	 * Get Subscriber
	 *
	 * @param  Not param
	 */
	public function get_subscriber($id) {
		$result = $this->db->get_row("SELECT * FROM `{$this->tb_prefix}sms_subscribes` WHERE ID = '".$id."'");
		
		if($result)
			return $result;
	}
	
	/**
	 * Delete Subscriber
	 *
	 * @param  Not param
	 */
	public function delete_subscriber($id) {
		
		if(empty($id))
			return;
		
		$result = $this->db->delete(
			$this->tb_prefix . "sms_subscribes",
			array(
				'ID'	=>	$id,
			)
		);
		
		if($result){
			
			/**
			 * Run hook after deleting subscribe.
			 *
			 * @since 3.0
			 * @param string $result result query.
			 */
			do_action('wp_sms_delete_subscriber', $result);
			
			return $result;
		}
	}
	
	/**
	 * Update Subscriber
	 *
	 * @param  Not param
	 */
	public function update_subscriber($id, $name, $mobile, $group_id = '', $status = '1') {
		
		if(empty($id) or empty($name) or empty($mobile))
			return;
		
		$result = $this->db->update(
			$this->tb_prefix . "sms_subscribes",
			array(
				'name'		=>	$name,
				'mobile'	=>	$mobile,
				'group_ID'	=>	$group_id,
				'status'	=>	$status,
			),
			array(
				'ID'		=>	$id
			)
		);
		
		if($result){
			
			/**
			 * Run hook after updating subscribe.
			 *
			 * @since 3.0
			 * @param string $result result query.
			 */
			do_action('wp_sms_update_subscriber', $result);
			
			return array('result' => 'update', 'message' => __('Subscriber successfully updated.', 'wp-sms'));
		}
	}
	
	/**
	 * Get Subscriber
	 *
	 * @param  Not param
	 */
	public function get_groups() {
		$result = $this->db->get_results("SELECT * FROM `{$this->tb_prefix}sms_subscribes_group`");
		
		if($result)
			return $result;
	}
	
	/**
	 * Get Group
	 *
	 * @param  Not param
	 */
	public function get_group($group_id) {
		$result = $this->db->get_row("SELECT * FROM `{$this->tb_prefix}sms_subscribes_group` WHERE ID = '".$group_id."'");
		
		if($result)
			return $result;
	}
	
	/**
	 * Add Group
	 *
	 * @param  Not param
	 */
	public function add_group($name) {
		
		if(empty($name))
			return array('result' => 'error', 'message' => __('Name is empty!', 'wp-sms'));
		
		$result = $this->db->insert(
			$this->tb_prefix . "sms_subscribes_group",
			array(
				'name'	=>	$name,
			)
		);
		
		if($result){
			
			/**
			 * Run hook after adding group.
			 *
			 * @since 3.0
			 * @param string $result result query.
			 */
			do_action('wp_sms_add_group', $result);
			
			return array('result' => 'update', 'message' => __('Group successfully added.', 'wp-sms'));
		}
		
	}
	
	/**
	 * Delete Group
	 *
	 * @param  Not param
	 */
	public function delete_group($id) {
		
		if(empty($id))
			return;
		
		$result = $this->db->delete(
			$this->tb_prefix . "sms_subscribes_group",
			array(
				'ID'	=>	$id,
			)
		);
		
		if($result){
			
			/**
			 * Run hook after deleting group.
			 *
			 * @since 3.0
			 * @param string $result result query.
			 */
			do_action('wp_sms_delete_group', $result);
			
			return $result;
		}
	}
	
	/**
	 * Update Group
	 *
	 * @param  Not param
	 */
	public function update_group($id, $name) {
		
		if(empty($id) or empty($name))
			return;
		
		$result = $this->db->update(
			$this->tb_prefix . "sms_subscribes_group",
			array(
				'name'	=>	$name,
			),
			array(
				'ID'	=>	$id
			)
		);
		
		if($result){
			
			/**
			 * Run hook after updating group.
			 *
			 * @since 3.0
			 * @param string $result result query.
			 */
			do_action('wp_sms_update_group', $result);
			
			return array('result' => 'update', 'message' => __('Group successfully updated.', 'wp-sms'));
		}
	}
}