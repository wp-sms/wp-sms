<?php

/**
 * WP SMS Widget widget.
 */
class WPSMS_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		$widget_options = array(
			'classname'   => 'wpsms_widget',
			'description' => __( 'SMS newsletter form', 'wp-sms' ),
		);

		parent::__construct( 'wpsms_widget', __( 'SMS newsletter', 'wp-sms' ), $widget_options );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		global $wpdb, $table_prefix;

		$widget_id = $this->get_numerics( $args['widget_id'] );
		$get_group = $wpdb->get_results( "SELECT * FROM `{$table_prefix}sms_subscribes_group`" );

		include_once dirname( __FILE__ ) . "/templates/wp-sms-subscribe-form.php";

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 *
	 * @return string|void
	 */
	public function form( $instance ) {
		$title                    = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Subscribe SMS', 'wp-sms' );
		$description              = ! empty( $instance['description'] ) ? $instance['description'] : '';
		$show_group               = ! empty( $instance['show_group'] ) ? $instance['show_group'] : '';
		$send_activation_code     = ! empty( $instance['send_activation_code'] ) ? $instance['send_activation_code'] : '';
		$send_welcome_sms         = ! empty( $instance['send_welcome_sms'] ) ? $instance['send_welcome_sms'] : '';
		$welcome_sms_template     = ! empty( $instance['welcome_sms_template'] ) ? $instance['welcome_sms_template'] : '';
		$mobile_number_terms      = ! empty( $instance['mobile_number_terms'] ) ? $instance['mobile_number_terms'] : '';
		$mobile_field_placeholder = ! empty( $instance['mobile_field_placeholder'] ) ? $instance['mobile_field_placeholder'] : '';
		$mobile_field_max         = ! empty( $instance['mobile_field_max'] ) ? $instance['mobile_field_max'] : '';
		$mobile_field_min         = ! empty( $instance['mobile_field_min'] ) ? $instance['mobile_field_min'] : '';

		// Load template
		include dirname( __FILE__ ) . "/templates/wp-sms-widget.php";
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                             = array();
		$instance['title']                    = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['description']              = ( ! empty( $new_instance['description'] ) ) ? $new_instance['description'] : '';
		$instance['show_group']               = ( ! empty( $new_instance['show_group'] ) ) ? $new_instance['show_group'] : '';
		$instance['send_activation_code']     = ( ! empty( $new_instance['send_activation_code'] ) ) ? $new_instance['send_activation_code'] : '';
		$instance['send_welcome_sms']         = ( ! empty( $new_instance['send_welcome_sms'] ) ) ? $new_instance['send_welcome_sms'] : '';
		$instance['welcome_sms_template']     = ( ! empty( $new_instance['welcome_sms_template'] ) ) ? $new_instance['welcome_sms_template'] : '';
		$instance['mobile_number_terms']      = ( ! empty( $new_instance['mobile_number_terms'] ) ) ? $new_instance['mobile_number_terms'] : '';
		$instance['mobile_field_placeholder'] = ( ! empty( $new_instance['mobile_field_placeholder'] ) ) ? $new_instance['mobile_field_placeholder'] : '';
		$instance['mobile_field_max']         = ( ! empty( $new_instance['mobile_field_max'] ) ) ? $new_instance['mobile_field_max'] : '';
		$instance['mobile_field_min']         = ( ! empty( $new_instance['mobile_field_min'] ) ) ? $new_instance['mobile_field_min'] : '';

		return $instance;
	}

	public function get_numerics( $str ) {
		preg_match( '/\d+/', $str, $matches );

		return $matches[0];
	}

}