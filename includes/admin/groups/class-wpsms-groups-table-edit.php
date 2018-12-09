<?php

namespace WP_SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

//Edit Groups Class
class Subscribers_Groups_Table_Edit {

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
	protected $tb_prefix;

	/**
	 * Subscribers_Groups_Table_Edit constructor.
	 */
	public function __construct() {
		global $wpdb, $table_prefix;

		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;

		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_assets' ), 11 );
		add_action( 'wp_ajax_wp_sms_edit_group', array( $this, 'wp_sms_edit_group' ) );

	}

	function wp_sms_edit_group() {
		//set Actiom Values
		$group_id   = isset( $_GET['group_id'] ) ? $_GET['group_id'] : null;
		$group_name = isset( $_GET['group_name'] ) ? $_GET['group_name'] : null;
		$html       = '<form action="" method="post">
					    <table>
					        <tr>
					            <td style="padding-top: 10px;">
					                <label for="wp_group_name"
					                       class="wp_sms_subscribers_label">' . __( 'Name', 'wp-sms' ) . '</label>
					                <input type="text" id="wp_group_name" name="wp_group_name" value="' . $group_name . '"
					                       class="wp_sms_subscribers_input_text"/>
					                <input type="hidden" id="wp_group_name" name="group_id" value="' . $group_id . '"
							class="wp_sms_subscribers_input_text"/>
							</td>
							</tr>
							
							<tr>
							    <td colspan="2" style="padding-top: 20px;">
							        <input type="submit" class="button-primary" name="wp_update_group"
							               value="' . __( 'Edit', 'wp-sms' ) . '"/>
							    </td>
							</tr>
							</table>
						</form>';
		echo $html;
		wp_die(); // this is required to terminate immediately and return a proper response
	}


	public function admin_assets( $hook ) {

		wp_register_script( 'wp-sms-edit-group', WP_SMS_URL . 'assets/js/edit-group.js', array(
			'jquery'
		), null, true );

		//Set Values
		if ( 'sms_page_wp-sms-subscribers-group' != $hook ) {
			// Only applies to WPS-Ar-Log page
			return;
		}
		wp_enqueue_script( 'wp-sms-edit-group' );

		$protocol = isset( $_SERVER["HTTPS"] ) ? 'https://' : 'http://';

		$tb_show_url = add_query_arg(
			array(
				'action' => 'wp_sms_edit_group'
			),
			admin_url( 'admin-ajax.php', $protocol )
		);

		$ajax_vars = array(
			'tb_show_url' => $tb_show_url,
			'tb_show_tag' => __( 'Edit Group', 'wp-sms' )
		);
		wp_localize_script( 'wp-sms-edit-group', 'wp_sms_edit_group_ajax_vars', $ajax_vars );
	}

}

new Subscribers_Groups_Table_Edit();
