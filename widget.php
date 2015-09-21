<?php
/**
 * Adds Foo_Widget widget.
 */
class WPSMS_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'foo_widget',
			__( 'Subscribe SMS Form', 'wp-sms' ),
			array( 'description' => __( 'Subscribe SMS Widget', 'wp-sms' ), )
		);
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
		include dirname( __FILE__ ) . "/includes/templates/wp-sms-widget.php"; 
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

// register WPSMS_Widget widget
function register_wpsms_widget() {
    register_widget( 'WPSMS_Widget' );
}
add_action( 'widgets_init', 'register_wpsms_widget' );