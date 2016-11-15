<?php
/**
 * @category   class
 * @package    WP_SMS
 * @author     Mostafa Soufi <info@mostafa-soufi.ir>
 * @copyright  2015 wp-sms-plugin.com
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    1.0
 */
class WP_SMS_Gateways {

	/**
	 * Webservice username
	 *
	 * @var string
	 */
	public $username;
	
	/**
	 * Webservice password
	 *
	 * @var string
	 */
	public $password;
	
	/**
	 * Webservice API/Key
	 *
	 * @var string
	 */
	public $has_key = false;
	
	/**
	 * Validation mobile number
	 *
	 * @var string
	 */
	public $validateNumber = "";
	
	/**
	 * Help to gateway
	 *
	 * @var string
	 */
	public $help = false;
	
	/**
	 * SMsS send from number
	 *
	 * @var string
	 */
	public $from;
	
	/**
	 * Send SMS to number
	 *
	 * @var string
	 */
	public $to;
	
	/**
	 * SMS text
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Constructor for the gateways class
	 */
	public function __construct() {
		add_filter('wpsms_settings_fields', array(&$this, 'modify_gateways'));
	}

	/**
	 * Modify gateways
	 * 
	 * @param  array $options options
	 * @return array          options
	 */
	public function modify_gateways($options) {

		// Get gateway path
		/*$dir = dirname(__FILE__) . "/gateways";

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
		}*/

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
		$options['wpsms_gateway']['sms_gateway']['options'] = $gateways;

		return $options;
	}

	/**
	 * Load gateway class class
	 * 
	 */
	public function load_gateway_class() {

		// Global WP SMS object
		global $wp_sms;

		// Get options
		$gateway = $wp_sms->options['wpsms_gateway'];

		// Check option exists
		if( empty($gateway['gateway']) or $gateway['gateway'] == 'none' )
			return false;

		// Check file exists
		if( !file_exists(dirname( __FILE__ ) . '/gateways/class-gateway-' . $gateway['gateway'] . '.php') )
			return false;

		// Include class
		include_once 'gateways/class-gateway-' . $gateway['gateway'] . '.php';

		return $gateway['gateway'];

	}

	public function send() {

		// Check gateway loaded
		$gateway = $this->load_gateway_class();
		
		if( !$gateway ) {
			return;
		}

		new $gateway;

	}
}
