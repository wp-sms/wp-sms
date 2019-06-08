<?php

namespace WP_SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Scheduled {

	public function __construct() {
		if ( ! wp_next_scheduled( 'wpsms_send_schedule_sms', array() ) ) {
			add_action( 'init', array( $this, 'schedule_wpsms_cron' ) );
		}

		add_filter( 'cron_schedules', array( $this, 'wp_sms_cron' ) );
		add_action( 'wpsms_send_schedule_sms', array( $this, 'send_sms_scheduled' ) );
	}

	/**
	 * Add an scheduled item
	 *
	 * @param $date
	 * @param $sender
	 * @param $message
	 * @param $recipient
	 * @param int $status | 1 = on queue, 2 = sent
	 *
	 * @return false|int
	 */
	public static function add( $date, $sender, $message, $recipient, $status = 1 ) {
		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . "sms_scheduled",
			array(
				'date'      => $date,
				'sender'    => $sender,
				'message'   => $message,
				'recipient' => implode( ',', $recipient ),
				'status'    => $status,
			)
		);
	}

	/**
	 * Update an scheduled item
	 *
	 * @param $schedule_id
	 * @param $date
	 * @param $message
	 * @param $sender
	 * @param $status
	 *
	 * @return false|int
	 */
	public static function update( $schedule_id, $date, $message, $sender, $status ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . "sms_scheduled",
			array(
				'date'    => $date,
				'sender'  => $sender,
				'message' => $message,
				'status'  => $status,
			),
			array(
				'ID' => $schedule_id
			)
		);
	}

	public function wp_sms_cron( $schedules ) {
		if ( ! isset( $schedules["5min_wpsms"] ) ) {
			$schedules["5min_wpsms"] = array(
				'interval' => 5 * 60,
				'display'  => __( 'WP-SMS Scheduled SMS cron', 'wp-sms' ) );
		}

		return $schedules;
	}

	/**
	 * Add cron event
	 */
	public function schedule_wpsms_cron() {
		wp_schedule_event( time(), '5min_wpsms', 'wpsms_send_schedule_sms', array() );
	}

	/**
	 * Send messages
	 */
	public function send_sms_scheduled() {
		/*
		 * TODO: Get DB rows and send Messages here.
		*/
		file_put_contents( 'log', print_r( $_REQUEST, 1 ), FILE_APPEND );
	}
}

new Scheduled();