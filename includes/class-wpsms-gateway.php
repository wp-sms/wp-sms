<?php
/**
 * Main gateway class
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Gateway {

	/**
	 * Recipients sms
	 * @var string
	 */
	public $to;

	/**
	 * From number
	 * @var array
	 */
	public $from;

	/**
	 * Message sms
	 * @var string
	 */
	public $message;

	/**
	 * WP-SMS Options
	 * @var array
	 */
	public $options;

	/**
	 * Gateway object
	 * @var object
	 */
	public $gateway;

	/**
	 * Constructor for the gateways class
	 */
	public function __construct() {

		global $wp_sms_options;

		// Set global options
		$this->options = $wp_sms_options;

		// Modify gateways
		add_filter('wpsms_settings_fields', array(&$this, 'modify_gateways'));

		// Load selected gateway
		$gateway_name = $this->load_gateway_class();

		// Create new object from gateway
		if( $gateway_name and class_exists($gateway_name) ) {
			$this->gateway = new $gateway_name();
		}
	}

	/**
	 * Modify gateways
	 * @param  array $options options
	 * @return array          options
	 */
	public function modify_gateways($options) {

		// Get gateway path
		$dir = dirname(__FILE__) . "/gateways";

		// Open a directory, and read its contents
		if ( is_dir($dir) ) {
			if ( $dh = opendir($dir) ) {
				while ( ($file = readdir($dh) ) !== false) {
					// Check file is valid
					if (strpos($file, 'class-gateway-') !== false) {
						$gateway_name = substr($file, 14, -4);
						$files[$gateway_name] = ucfirst($gateway_name);
					}
				}
				closedir($dh);
			}
		}

		// Gateways
		$gateways = array(
			'none'			=>	'None',
			'0098sms'		=> '0098sms',
			'500sms'		=> '500sms',
			'adpdigital'	=> 'Adpdigital',
			'adspanel'		=> 'Adspanel',
			'afe'			=> 'Afe',
			'afilnet'		=> 'Afilnet',
			'amansoft'		=> 'Amansoft',
			'aradsms'		=> 'Aradsms',
			'ariaideh'		=> 'Ariaideh',
			'arkapayamak'	=> 'Arkapayamak',
			'asanak'		=> 'Asanak',
			'asiapayamak'	=> 'Asiapayamak',
			'avalpayam'		=> 'Avalpayam',
			'bandarsms'		=> 'Bandarsms',
			'barmanpayamak'	=> 'Barmanpayamak',
			'barzinsms'		=> 'Barzinsms',
			'bearsms'		=> 'Bearsms',
			'bestit'		=> 'Bestit',
			'bulutfon'		=> 'Bulutfon',
			'caffeweb'		=> 'Caffeweb',
			'chapargah'		=> 'Chapargah',
			'chaparpanel'	=> 'Chaparpanel',
			'difaan'		=> 'Difaan',
			'dot4all'		=> 'Dot4all',
			'esms24'		=> 'Esms24',
			'faraed'		=> 'Faraed',
			'farapayamak'	=> 'Farapayamak',
			'farazpayam'	=> 'Farazpayam',
			'fayasms'		=> 'Fayasms',
			'firstpayamak'	=> 'Firstpayamak',
			'fortytwo'		=> 'Fortytwo',
			'freepayamak'	=> 'Freepayamak',
			'gateway'		=> 'Gateway',
			'hisms'			=> 'Hisms',
			'hostiran'		=> 'Hostiran',
			'idehpayam'		=> 'Idehpayam',
			'imencms'		=> 'Imencms',
			'instantalerts'	=> 'Instantalerts',
			'iransms'		=> 'Iransms',
			'iransmspanel'	=> 'Iransmspanel',
			'iranspk'		=> 'Iranspk',
			'irsmsland'		=> 'Irsmsland',
			'ismsie'		=> 'Ismsie',
			'isun'			=> 'Isun',
			'itpayamak'		=> 'Itpayamak',
			'jahanpayamak'	=> 'Jahanpayamak',
			'joghataysms'	=> 'Joghataysms',
			'labsmobile'	=> 'Labsmobile',
			'loginpanel'	=> 'Loginpanel',
			'markazpayamak'	=> 'Markazpayamak',
			'matinsms'		=> 'Matinsms',
			'mdpanel'		=> 'Mdpanel',
			'mediana'		=> 'Mediana',
			'melipayamak'	=> 'Melipayamak',
			'modiranweb'	=> 'Modiranweb',
			'mtarget'		=> 'Mtarget',
			'mydnspanel'	=> 'Mydnspanel',
			'nasrpayam'		=> 'Nasrpayam',
			'navidsoft'		=> 'Navidsoft',
			'niazpardaz'	=> 'Niazpardaz',
			'niazpardazcom'	=> 'Niazpardazcom',
			'novin1sms'		=> 'Novin1sms',
			'novinpayamak'	=> 'Novinpayamak',
			'opilo'			=> 'Opilo',
			'paaz'			=> 'Paaz',
			'panizsms'		=> 'Panizsms',
			'parandhost'	=> 'Parandhost',
			'parsasms'		=> 'Parsasms',
			'parsgreen'		=> 'Parsgreen',
			'payamakalmas'	=> 'Payamakalmas',
			'payamakaria'	=> 'Payamakaria',
			'payamakde'		=> 'Payamakde',
			'payamakpanel'	=> 'Payamakpanel',
			'payameavval'	=> 'Payameavval',
			'payameroz'		=> 'Payameroz',
			'payamgah'		=> 'Payamgah',
			'payamresan'	=> 'Payamresan',
			'pegahpayamak'	=> 'Pegahpayamak',
			'persiansms'	=> 'Persiansms',
			'pichakhost'	=> 'Pichakhost',
			'ponishasms'	=> 'Ponishasms',
			'popaksms'		=> 'Popaksms',
			'postgah'		=> 'Postgah',
			'rayanbit'		=> 'Rayanbit',
			'razpayamak'	=> 'Razpayamak',
			'sabasms'		=> 'Sabasms',
			'sadat24'		=> 'Sadat24',
			'sarabsms'		=> 'Sarabsms',
			'sarinapayamak'	=> 'Sarinapayamak',
			'sepehritc'		=> 'Sepehritc',
			'sharifpardazan'=> 'Sharifpardazan',
			'shreesms'		=> 'Shreesms',
			'sms'			=> 'Sms',
			'sms77'			=> 'Sms77',
			'smsapi'		=> 'Smsapi',
			'smsarak'		=> 'Smsarak',
			'smsban'		=> 'Smsban',
			'smsbartar'		=> 'Smsbartar',
			'smscall'		=> 'Smscall',
			'smsclick'		=> 'Smsclick',
			'smsde'			=> 'Smsde',
			'smsfa'			=> 'Smsfa',
			'smsgateway'	=> 'Smsgateway',
			'smsgatewayhub'	=> 'Smsgatewayhub',
			'smsglobal'		=> 'Smsglobal',
			'smshooshmand'	=> 'Smshooshmand',
			'smshosting'	=> 'Smshosting',
			'smsline'		=> 'Smsline',
			'smsmart'		=> 'Smsmart',
			'smsmaster'		=> 'Smsmaster',
			'smsmelli'		=> 'Smsmelli',
			'smsroo'		=> 'Smsroo',
			'smss'			=> 'Smss',
			'smsservice'	=> 'Smsservice',
			'smstoos'		=> 'Smstoos',
			'sms_new'		=> 'Sms_new',
			'sms_s'			=> 'Sms_s',
			'sonoratecnologia'=> 'Sonoratecnologia',
			'spadbs'		=> 'Spadbs',
			'ssmss'			=> 'Ssmss',
			'tablighsmsi'	=> 'Tablighsmsi',
			'tcisms'		=> 'Tcisms',
			'textplode'		=> 'Textplode',
			'textsms'		=> 'Textsms',
			'tgfsms'		=> 'Tgfsms',
			'tsms'			=> 'Tsms',
			'unisender'		=> 'Unisender',
			'wifisms'		=> 'Wifisms',
			'yashilsms'		=> 'Yashilsms',
		);

		// Set gateways
		$options['wpsms_gateway']['sms_gateway']['options'] = $files;

		return $options;
	}

	/**
	 * Load gateway class
	 * @return string Gateway class name
	 */
	public function load_gateway_class() {

		// Get options
		$gateway = $this->options['wpsms_gateway'];

		// Check option exists
		if( empty($gateway['gateway']) or $gateway['gateway'] == 'none' )
			return false;

		// Check file exists
		if( !file_exists(dirname( __FILE__ ) . '/gateways/class-gateway-' . $gateway['gateway'] . '.php') )
			return false;

		// Include class
		include_once 'gateways/class-gateway-' . $gateway['gateway'] . '.php';

		$class_name = $gateway['gateway'] . 'Gateway';

		if( class_exists( $class_name ) ) {
			return $class_name;
		} else {
			WP_SMS_AdminNotices::error( sprintf(__('Class <b>%s</b> does not exits and WP-SMS cant load this.', 'wp-sms'), $class_name) );
		}
		
	}

	/**
	 * Send sms
	 * @return string Result of gateway
	 */
	public function send() {

		// Check gateway
		if( !$this->gateway ) {
			return new WP_Error( 'gateway', __( 'Does not gateway to sending sms!', 'wp-sms' ) );
		}

		// Configuration gateway
		$this->gateway->username = $this->options['wpsms_gateway']['gateway_username'];
		$this->gateway->password = $this->options['wpsms_gateway']['gateway_password'];
		$this->gateway->has_key = $this->options['wpsms_gateway']['gateway_api_key'];

		// Get account credit
		$response = $this->gateway->get_credit();

		// Check account credit
		if( $response['status'] == 'error' ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms!', 'wp-sms' ), $response['response'] );
		}

		/**
		 * Modify text message
		 *
		 * @since 3.4
		 * @param string $message text message.
		 */
		$message = apply_filters('wp_sms_msg', $this->message);

		/**
		 * Modify Receiver number
		 *
		 * @since 3.4
		 * @param array $to receiver number
		 */
		$to = apply_filters('wp_sms_to', $this->to);

		// Check sender from
		if( $this->from ) {
			$from = $this->from;
		} else {
			$from = $this->options['wpsms_gateway']['sender_id'];
		}

		/**
		 * Modify sender number
		 *
		 * @since 3.4
		 * @param string $from sender number.
		 */
		$from = apply_filters('wp_sms_from', $from);
		
		// Fired sms!
		$response = $this->gateway->send($message, $to, $from);

		if( $response['status'] == 'success' ) {

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 * @param string $result result output.
			 */
			do_action('wp_sms_sended', $message, $to, $from);

			return true;

		} else {
			return new WP_Error( 'send-sms', __( 'SMS not sent! please read response error!', 'wp-sms' ), $response['response'] );
		}

	}
}