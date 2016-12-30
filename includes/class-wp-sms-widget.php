<?php
/**
 * Adds Foo_Widget widget.
 */
class WPSMS_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		$widget_options = array(
			'classname' => 'wpsms_widget',
			'description' => __( 'SMS newsletter form', 'wp-sms' ),
		);

		parent::__construct( 'wpsms_widget', __( 'SMS newsletter', 'wp-sms' ), $widget_options );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}
		
		wp_subscribes($instance['description'], $instance['show_group']);
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Subscribe SMS', 'wp-sms' );
		$description = ! empty( $instance['description'] ) ? $instance['description'] : '';
		$show_group = ! empty( $instance['show_group'] ) ? $instance['show_group'] : '';
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
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['description'] = ( ! empty( $new_instance['description'] ) ) ? $new_instance['description'] : '';
		$instance['show_group'] = ( ! empty( $new_instance['show_group'] ) ) ? $new_instance['show_group'] : '';

		return $instance;
	}

}