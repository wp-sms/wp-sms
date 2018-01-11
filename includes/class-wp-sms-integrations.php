<?php

/**
 * WP SMS integrations class
 *
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Integrations {

	public $sms;
	public $date;
	public $options;

	public function __construct() {
		global $wpsms_option, $sms;

		$this->sms     = $sms;
		$this->date    = WP_SMS_CURRENT_DATE;
		$this->options = $wpsms_option;

		// Contact Form 7
		if ( isset( $this->options['cf7_metabox'] ) ) {
			add_filter( 'wpcf7_editor_panels', array( $this, 'cf7_editor_panels' ) );
			add_action( 'wpcf7_after_save', array( $this, 'wpcf7_save_form' ) );
			add_action( 'wpcf7_before_send_mail', array( $this, 'wpcf7_sms_handler' ) );
		}

		// Woocommerce
		if ( isset( $this->options['wc_notif_new_order'] ) ) {
			add_action( 'woocommerce_new_order', array( $this, 'wc_new_order' ) );
		}

		// EDD
		if ( isset( $this->options['edd_notif_new_order'] ) ) {
			add_action( 'edd_complete_purchase', array( $this, 'edd_new_order' ) );
		}
	}

	public function cf7_editor_panels( $panels ) {
		$new_page = array(
			'wpsms' => array(
				'title'    => __( 'SMS Notification', 'wp-sms' ),
				'callback' => array( $this, 'cf7_setup_form' )
			)
		);

		$panels = array_merge( $panels, $new_page );

		return $panels;
	}

	public function cf7_setup_form( $form ) {
		$cf7_options       = get_option( 'wpcf7_sms_' . $form->id() );
		$cf7_options_field = get_option( 'wpcf7_sms_form' . $form->id() );

		if ( ! isset( $cf7_options['phone'] ) ) {
			$cf7_options['phone'] = '';
		}
		if ( ! isset( $cf7_options['message'] ) ) {
			$cf7_options['message'] = '';
		}
		if ( ! isset( $cf7_options_field['phone'] ) ) {
			$cf7_options_field['phone'] = '';
		}
		if ( ! isset( $cf7_options_field['message'] ) ) {
			$cf7_options_field['message'] = '';
		}

		include_once dirname( __FILE__ ) . "/templates/wp-sms-wpcf7-form.php";
	}

	public function wpcf7_save_form( $form ) {
		update_option( 'wpcf7_sms_' . $form->id(), $_POST['wpcf7-sms'] );
		update_option( 'wpcf7_sms_form' . $form->id(), $_POST['wpcf7-sms-form'] );
	}

	public function wpcf7_sms_handler( $form ) {
		$cf7_options       = get_option( 'wpcf7_sms_' . $form->id() );
		$cf7_options_field = get_option( 'wpcf7_sms_form' . $form->id() );

		foreach ( $_POST as $index => $key ) {
			if ( is_array( $key ) ) {
				$plain_data[ $index ] = implode( ', ', $key );
			} else {
				$plain_data[ $index ] = $key;
			}
		}

		if ( $cf7_options['message'] && $cf7_options['phone'] ) {
			$this->sms->to  = explode( ',', $cf7_options['phone'] );
			$this->sms->msg = @preg_replace( '/%([a-zA-Z0-9._-]+)%/e', '$plain_data["$1"]', $cf7_options['message'] );
			$this->sms->SendSMS();
		}

		if ( $cf7_options_field['message'] && $cf7_options_field['phone'] ) {
			$this->sms->to  = array( @preg_replace( '/%([a-zA-Z0-9._-]+)%/e', '$plain_data["$1"]', $cf7_options_field['phone'] ) );
			$this->sms->msg = @preg_replace( '/%([a-zA-Z0-9._-]+)%/e', '$plain_data["$1"]', $cf7_options_field['message'] );
			$this->sms->SendSMS();
		}
	}

	public function wc_new_order( $order_id ) {
		$order          = new WC_Order( $order_id );
		$this->sms->to  = array( $this->options['admin_mobile_number'] );
		$template_vars  = array(
			'%order_id%'     => $order_id,
			'%status%'       => $order->get_status(),
			'%order_number%' => $order->get_order_number(),
		);
		$message        = str_replace( array_keys( $template_vars ), array_values( $template_vars ), $this->options['wc_notif_new_order_template'] );
		$this->sms->msg = $message;

		$this->sms->SendSMS();
	}

	public function edd_new_order() {
		$this->sms->to  = array( $this->options['admin_mobile_number'] );
		$this->sms->msg = $this->options['edd_notif_new_order_template'];
		$this->sms->SendSMS();
	}

}

new WP_SMS_Integrations();