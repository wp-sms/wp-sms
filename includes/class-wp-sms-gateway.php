<?php

/**
 * WP SMS gateway class
 *
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Gateway {

	/**
	 * @var
	 */
	static $get_response;

	/**
	 * @return mixed|void
	 */
	public static function gateway() {
		$gateways = array(
			''               => array(
				'default' => __( 'Please select your gateway', 'wp-sms' ),
			),
			'global'         => array(
				'experttexting' => 'experttexting.com',
				'fortytwo'      => 'fortytwo.com',
				'smsglobal'     => 'smsglobal.com',
				'gatewayapi'    => 'gatewayapi.com',
				'spirius'       => 'spirius.com',
			),
			'united kingdom' => array(
				'_textplode'   => 'textplode.com',
				'textanywhere' => 'textanywhere.net',
			),
			'french'         => array(
				'primotexto' => 'primotexto.com',
			),
			'brazil'         => array(
				'sonoratecnologia' => 'sonoratecnologia.com.br',
			),
			'germany'        => array(
				'sms77' => 'sms77.de',
			),
			'turkey'         => array(
				'bulutfon' => 'bulutfon.com',
				'verimor' => 'verimor.com.tr',
			),
			'austria'        => array(
				'smsgateway' => 'sms-gateway.at',
			),
			'spain'          => array(
				'afilnet'    => 'afilnet.com',
				'labsmobile' => 'labsmobile.com',
				'mensatek'   => 'mensatek.com',
			),
			'new zealand'    => array(
				'unisender' => 'unisender.com',
			),
			'polish'         => array(
				'smsapi' => 'smsapi.pl',
			),
            'denmark'         => array(
				'cpsms'     => 'cpsms.dk',
				'suresms'   => 'suresms.com',
			),
			'italy'          => array(
				'dot4all'    => 'dot4all.it',
				'smshosting' => 'smshosting.it',
				'comilio'    => 'comilio.it',
			),
			'india'          => array(
				'shreesms'         => 'shreesms.net',
				'ozonesmsworld'    => 'ozonesmsworld.com',
				'instantalerts'    => 'springedge.com',
				'smsgatewayhub'    => 'smsgatewayhub.com',
				'smsgatewaycenter' => 'smsgatewaycenter.com',
				'itfisms'          => 'itfisms.com',
				'pridesms'         => 'pridesms.in',
				'smsozone'         => 'ozonesms.com',
				'msgwow'           => 'msgwow.com',
				'mobtexting'       => 'mobtexting.com',
				'tripadasmsbox'    => 'tripadasmsbox.com'
			),
			'iran'           => array(
				'iransmspanel'   => 'iransmspanel.ir',
				'chaparpanel'    => 'chaparpanel.ir',
				'markazpayamak'  => 'markazpayamak.ir',
				'adpdigital'     => 'adpdigital.com',
				'hostiran'       => 'hostiran.net',
				'farapayamak'    => 'farapayamak.com',
				'smsde'          => 'smsde.ir',
				'payamakde'      => 'payamakde.ir',
				'panizsms'       => 'panizsms.com',
				'sepehritc'      => 'sepehritc.com',
				'payameavval'    => 'payameavval.com',
				'smsclick'       => 'smsclick.ir',
				'persiansms'     => 'persiansms.com',
				'ariaideh'       => 'ariaideh.com',
				'sms_s'          => 'modiresms.com',
				'sadat24'        => 'sadat24.ir',
				'smscall'        => 'smscall.ir',
				'tablighsmsi'    => 'tablighsmsi.com',
				'paaz'           => 'paaz.ir',
				'textsms'        => 'textsms.ir',
				'jahanpayamak'   => 'jahanpayamak.info',
				'opilo'          => 'opilo.com',
				'barzinsms'      => 'barzinsms.ir',
				'smsmart'        => 'smsmart.ir',
				'loginpanel'     => 'loginpanel.ir',
				'imencms'        => 'imencms.com',
				'tcisms'         => 'tcisms.com',
				'caffeweb'       => 'caffeweb.com',
				'nasrpayam'      => 'nasrPayam.ir',
				'smsbartar'      => 'sms-bartar.com',
				'fayasms'        => 'fayasms.ir',
				'payamresan'     => 'payam-resan.com',
				'mdpanel'        => 'ippanel.com',
				'payameroz'      => 'payameroz.ir',
				'niazpardaz'     => 'niazpardaz.com',
				'niazpardazcom'  => 'niazpardaz.com - New',
				'hisms'          => 'hi-sms.ir',
				'joghataysms'    => '051sms.ir',
				'mediana'        => 'mediana.ir',
				'aradsms'        => 'arad-sms.ir',
				'asiapayamak'    => 'payamak.asia',
				'sharifpardazan' => '2345.ir',
				'sarabsms'       => 'sarabsms.ir',
				'ponishasms'     => 'ponishasms.ir',
				'payamakalmas'   => 'payamakalmas.ir',
				'sms'            => 'sms.ir - Old',
				'sms_new'        => 'sms.ir - New',
				'popaksms'       => 'popaksms.ir',
				'novin1sms'      => 'novin1sms.ir',
				'_500sms'        => '500sms.ir',
				'matinsms'       => 'smspanel.mat-in.ir',
				'iranspk'        => 'iranspk.ir',
				'freepayamak'    => 'freepayamak.ir',
				'itpayamak'      => 'itpayamak.ir',
				'irsmsland'      => 'irsmsland.ir',
				'avalpayam'      => 'avalpayam.com',
				'smstoos'        => 'smstoos.ir',
				'smsmaster'      => 'smsmaster.ir',
				'ssmss'          => 'ssmss.ir',
				'isun'           => 'isun.company',
				'idehpayam'      => 'idehpayam.com',
				'smsarak'        => 'smsarak.ir',
				'novinpayamak'   => 'novinpayamak.com',
				'melipayamak'    => 'melipayamak.ir',
				'postgah'        => 'postgah.net',
				'smsfa'          => 'smsfa.net',
				'rayanbit'       => 'rayanbit.net',
				'smsmelli'       => 'smsmelli.com',
				'smsban'         => 'smsban.ir',
				'smsroo'         => 'smsroo.ir',
				'navidsoft'      => 'navid-soft.ir',
				'afe'            => 'afe.ir',
				'smshooshmand'   => 'smshooshmand.com',
				'asanak'         => 'asanak.ir',
				'payamakpanel'   => 'payamak-panel.com',
				'barmanpayamak'  => 'barmanpayamak.ir',
				'farazpayam'     => 'farazpayam.com',
				'_0098sms'       => '0098sms.com',
				'amansoft'       => 'amansoft.ir',
				'faraed'         => 'faraed.com',
				'spadbs'         => 'spadsms.ir',
				'bandarsms'      => 'bandarit.ir',
				'tgfsms'         => 'tgfsms.ir',
				'payamgah'       => 'payamgah.net',
				'sabasms'        => 'sabasms.biz',
				'chapargah'      => 'chapargah.ir',
				'yashilsms'      => 'yashil-sms.ir',
				'ismsie'         => 'isms.ir',
				'wifisms'        => 'wifisms.ir',
				'razpayamak'     => 'razpayamak.com',
				'bestit'         => 'bestit.co',
				'pegahpayamak'   => 'pegah-payamak.ir',
				'adspanel'       => 'adspanel.ir',
				'mydnspanel'     => 'mydnspanel.com',
				'esms24'         => 'esms24.ir',
				'payamakaria'    => 'payamakaria.ir',
				'pichakhost'     => 'sitralweb.com',
				'tsms'           => 'tsms.ir',
				'parsasms'       => 'parsasms.com',
				'modiranweb'     => 'modiranweb.net',
				'smsline'        => 'smsline.ir',
				'iransms'        => 'iransms.co',
				'arkapayamak'    => 'arkapayamak.ir',
				'smsservice'     => 'smsservice.ir',
				'parsgreen'      => 'api.ir',
				'firstpayamak'   => 'firstpayamak.ir',
				'kavenegar'      => 'kavenegar.com',
				'_18sms'         => '18sms.ir',
				'eshare'         => 'eshare.com',
				'abrestan'       => 'abrestan.com',
				'sabanovin'      => 'sabanovin.com',
				'candoosms'      => 'candoosms.com',
			),
			'pakistan'       => array(
				'difaan' => 'difaan',
			),
			'africa'         => array(
				'_ebulksms'      => 'ebulksms.com',
				'africastalking' => 'africastalking.com',
			),
			'kenya'          => array(
				'uwaziimobile' => 'uwaziimobile.com',
			),
			'cyprus'         => array(
				'websmscy' => 'websms.com.cy',
			),
			'ukraine'        => array(
				'smsc' => 'smsc.ua',
			),
			'arabic'         => array(
				'gateway'    => 'gateway.sa',
				'resalaty'   => 'resalaty.com',
				'asr3sms'    => 'asr3sms.com',
				'infodomain' => 'infodomain.asia',
			),
			'other'          => array(
				'smss'     => 'smss.co.il',
				'bearsms'  => 'bearsms.com',
				'mtarget'  => 'mtarget.fr',
				'torpedos' => 'torpedos.pro',
			),
		);

		return apply_filters( 'wpsms_gateway_list', $gateways );
	}

	/**
	 * @return string
	 */
	public static function status() {
		global $sms;

		// Get credit
		$result = $sms->GetCredit();

		if ( is_wp_error( $result ) ) {
			// Set error message
			self::$get_response = $result->get_error_message();

			// Update credit
			update_option( 'wp_last_credit', 0 );

			// Return html
			return '<div class="wpsms-no-credit"><span class="dashicons dashicons-no"></span> ' . __( 'Deactive!', 'wp-sms' ) . '</div>';
		} else {
			// Update credit
			update_option( 'wp_last_credit', $result );

			self::$get_response = $result;

			// Return html
			return '<div class="wpsms-has-credit"><span class="dashicons dashicons-yes"></span> ' . __( 'Active!', 'wp-sms' ) . '</div>';
		}
	}

	/**
	 * @return mixed
	 */
	public static function response() {
		return self::$get_response;
	}

	/**
	 * @return mixed
	 */
	public static function help() {
		global $sms;

		// Get gateway help
		return $sms->help;
	}

	/**
	 * @return mixed
	 */
	public static function from() {
		global $sms;

		// Get gateway from
		return $sms->from;
	}

	/**
	 * @return string
	 */
	public static function bulk_status() {
		global $sms;

		// Get bulk status
		if ( $sms->bulk_send == true ) {
			// Return html
			return '<div class="wpsms-has-credit"><span class="dashicons dashicons-yes"></span> ' . __( 'Supported', 'wp-sms' ) . '</div>';
		} else {
			// Return html
			return '<div class="wpsms-no-credit"><span class="dashicons dashicons-no"></span> ' . __( 'Does not support!', 'wp-sms' ) . '</div>';
		}
	}

	/**
	 * @return int
	 */
	public static function credit() {
		global $sms;
		// Get credit
		$result = $sms->GetCredit();

		if ( is_wp_error( $result ) ) {
			return 0;
		} else {
			return $result;
		}
	}

}
