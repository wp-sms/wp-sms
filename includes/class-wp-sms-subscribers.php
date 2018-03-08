<?php

/**
 * @category   class
 * @package    WP_SMS
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

		$this->date      = WP_SMS_CURRENT_DATE;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
	}

	/**
	 * Add Subscriber
	 *
	 * @param $name
	 * @param $mobile
	 * @param string $group_id
	 * @param string $status
	 * @param $key
	 *
	 * @return array
	 * @internal param param $Not
	 */
	public function add_subscriber( $name, $mobile, $group_id = '', $status = '1', $key = nul ) {
		if ( $this->is_duplicate( $mobile, $group_id ) ) {
			return array( 'result'  => 'error',
			              'message' => __( 'The mobile numbers has been already duplicate.', 'wp-sms' )
			);
		}

		$result = $this->db->insert(
			$this->tb_prefix . "sms_subscribes",
			array(
				'date'         => $this->date,
				'name'         => $name,
				'mobile'       => $mobile,
				'status'       => $status,
				'activate_key' => $key,
				'group_ID'     => $group_id,
			)
		);

		if ( $result ) {
			/**
			 * Run hook after adding subscribe.
			 *
			 * @since 3.0
			 *
			 * @param string $name name.
			 * @param string $mobile mobile.
			 */
			do_action( 'wp_sms_add_subscriber', $name, $mobile );

			return array( 'result' => 'update', 'message' => __( 'Subscriber successfully added.', 'wp-sms' ) );
		}
	}

	/**
	 * Get Subscriber
	 *
	 * @param  Not param
	 *
	 * @return array|null|object|void
	 */
	public function get_subscriber( $id ) {
		$result = $this->db->get_row( "SELECT * FROM `{$this->tb_prefix}sms_subscribes` WHERE ID = '" . $id . "'" );

		if ( $result ) {
			return $result;
		}
	}

	/**
	 * Delete Subscriber
	 *
	 * @param  Not param
	 *
	 * @return false|int|void
	 */
	public function delete_subscriber( $id ) {
		$result = $this->db->delete(
			$this->tb_prefix . "sms_subscribes",
			array(
				'ID' => $id,
			)
		);

		if ( $result ) {
			/**
			 * Run hook after deleting subscribe.
			 *
			 * @since 3.0
			 *
			 * @param string $result result query.
			 */
			do_action( 'wp_sms_delete_subscriber', $result );

			return $result;
		}
	}

	/**
	 * Delete subscribers by number
	 *
	 * @param $mobile
	 * @param null $group_id
	 *
	 * @return array
	 */
	public function delete_subscriber_by_number( $mobile, $group_id = null ) {
		$result = $this->db->delete(
			$this->tb_prefix . "sms_subscribes",
			array(
				'mobile'   => $mobile,
				'group_id' => $group_id,
			)
		);

		if ( ! $result ) {
			return array( 'result' => 'error', 'message' => __( 'The subscribe does not exist.', 'wp-sms' ) );
		}

		/**
		 * Run hook after deleting subscribe.
		 *
		 * @since 3.0
		 *
		 * @param string $result result query.
		 */
		do_action( 'wp_sms_delete_subscriber', $result );

		return array( 'result' => 'update', 'message' => __( 'Subscribe successfully removed.', 'wp-sms' ) );
	}

	/**
	 * Update Subscriber
	 *
	 * @param $id
	 * @param $name
	 * @param $mobile
	 * @param string $group_id
	 * @param string $status
	 *
	 * @return array|void
	 * @internal param param $Not
	 */
	public function update_subscriber( $id, $name, $mobile, $group_id = '', $status = '1' ) {
		if ( empty( $id ) or empty( $name ) or empty( $mobile ) ) {
			return;
		}

		if ( $this->is_duplicate( $mobile, $group_id, $id ) ) {
			return array( 'result'  => 'error',
			              'message' => __( 'The mobile numbers has been already duplicate.', 'wp-sms' )
			);
		}

		$result = $this->db->update(
			$this->tb_prefix . "sms_subscribes",
			array(
				'name'     => $name,
				'mobile'   => $mobile,
				'group_ID' => $group_id,
				'status'   => $status,
			),
			array(
				'ID' => $id
			)
		);

		if ( $result ) {

			/**
			 * Run hook after updating subscribe.
			 *
			 * @since 3.0
			 *
			 * @param string $result result query.
			 */
			do_action( 'wp_sms_update_subscriber', $result );

			return array( 'result' => 'update', 'message' => __( 'Subscriber successfully updated.', 'wp-sms' ) );
		}
	}

	/**
	 * Get Subscriber
	 *
	 * @param  Not param
	 *
	 * @return array|null|object
	 */
	public function get_groups() {
		$result = $this->db->get_results( "SELECT * FROM `{$this->tb_prefix}sms_subscribes_group`" );

		if ( $result ) {
			return $result;
		}
	}

	/**
	 * Get Group
	 *
	 * @param  Not param
	 *
	 * @return array|null|object|void
	 */
	public function get_group( $group_id ) {
		$result = $this->db->get_row( "SELECT * FROM `{$this->tb_prefix}sms_subscribes_group` WHERE ID = '" . $group_id . "'" );

		if ( $result ) {
			return $result;
		}
	}

	/**
	 * Add Group
	 *
	 * @param  Not param
	 *
	 * @return array
	 */
	public function add_group( $name ) {
		if ( empty( $name ) ) {
			return array( 'result' => 'error', 'message' => __( 'Name is empty!', 'wp-sms' ) );
		}

		$result = $this->db->insert(
			$this->tb_prefix . "sms_subscribes_group",
			array(
				'name' => $name,
			)
		);

		if ( $result ) {

			/**
			 * Run hook after adding group.
			 *
			 * @since 3.0
			 *
			 * @param string $result result query.
			 */
			do_action( 'wp_sms_add_group', $result );

			return array( 'result' => 'update', 'message' => __( 'Group successfully added.', 'wp-sms' ) );
		}

	}

	/**
	 * Delete Group
	 *
	 * @param  Not param
	 *
	 * @return false|int|void
	 */
	public function delete_group( $id ) {

		if ( empty( $id ) ) {
			return;
		}

		$result = $this->db->delete(
			$this->tb_prefix . "sms_subscribes_group",
			array(
				'ID' => $id,
			)
		);

		if ( $result ) {

			/**
			 * Run hook after deleting group.
			 *
			 * @since 3.0
			 *
			 * @param string $result result query.
			 */
			do_action( 'wp_sms_delete_group', $result );

			return $result;
		}
	}

	/**
	 * Update Group
	 *
	 * @param $id
	 * @param $name
	 *
	 * @return array|void
	 * @internal param param $Not
	 */
	public function update_group( $id, $name ) {
		if ( empty( $id ) or empty( $name ) ) {
			return;
		}

		$result = $this->db->update(
			$this->tb_prefix . "sms_subscribes_group",
			array(
				'name' => $name,
			),
			array(
				'ID' => $id
			)
		);

		if ( $result ) {

			/**
			 * Run hook after updating group.
			 *
			 * @since 3.0
			 *
			 * @param string $result result query.
			 */
			do_action( 'wp_sms_update_group', $result );

			return array( 'result' => 'update', 'message' => __( 'Group successfully updated.', 'wp-sms' ) );
		}
	}

	/**
	 * Check the mobile number is duplicate
	 *
	 * @param $mobile_number
	 * @param null $group_id
	 * @param null $id
	 *
	 * @return array|null|object|void
	 */
	private function is_duplicate( $mobile_number, $group_id = null, $id = null ) {
		$sql = "SELECT * FROM `{$this->tb_prefix}sms_subscribes` WHERE mobile = '" . $mobile_number . "'";

		if ( $group_id ) {
			$sql .= " AND group_id = '" . $group_id . "'";
		}

		if ( $id ) {
			$sql .= " AND id != '" . $id . "'";
		}

		$result = $this->db->get_row( $sql );

		return $result;
	}
}
