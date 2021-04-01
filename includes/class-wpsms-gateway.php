<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * WP_SMS gateway class
 */
class Gateway
{
    // Set pro gateways
    public static $proGateways = array(
        'global'        => array(
            'twilio'         => 'twilio.com',
            'plivo'          => 'plivo.com',
            'clickatell'     => 'clickatell.com',
            'bulksms'        => 'bulksms.com',
            'infobip'        => 'infobip.com',
            'nexmo'          => 'nexmo.com',
            'clockworksms'   => 'clockworksms.com',
            'messagebird'    => 'messagebird.com',
            'clicksend'      => 'clicksend.com',
            'smsapicom'      => 'smsapi.com',
            'dsms'           => 'dsms.in',
            'esms'           => 'esms.vn',
            'isms'           => 'isms.com.my',
            'alfacell'       => 'alfa-cell.com',
            'moceansms'      => 'moceansms.com',
            'msg91'          => 'msg91.com',
            'msg360'         => 'msg360.in',
            'livesms'        => 'livesms.eu',
            'thesmsworld'    => 'thesmsworld.com',
            'ozioma'         => 'ozioma.net',
            'pswin'          => 'pswin.com',
            'ra'             => 'ra.sa',
            'smsfactor'      => 'smsfactor.com',
            'textmarketer'   => 'textmarketer.co.uk',
            'smslive247'     => 'smslive247.com',
            'sendsms247'     => 'sendsms247.com',
            'ssdindia'       => 'ssdindia.com',
            'jolis'          => 'jolis.net',
            'vsms'           => 'vsms.club',
            'websms'         => 'websms.at',
            'smstrade'       => 'smstrade.de',
            'yamamah'        => 'yamamah.com',
            'cellsynt'       => 'cellsynt.net',
            'cmtelecom'      => 'cmtelecom.com',
            'textlocal'      => 'textlocal.in',
            'ismartsms'      => 'ismartsms.net',
            'ooredoosms'     => 'ooredoo-sms.com',
            'txtlocal'       => 'txtlocal - textlocal.com',
            'qsms'           => 'qsms.com.au',
            'hoiio'          => 'hoiio.com',
            'textmagic'      => 'textmagic.com',
            'smsmisr'        => 'smsmisr.com',
            'smsgateway'     => 'smsgateway.me',
            'bandwidth'      => 'bandwidth.com',
            '_4jawaly'       => '4jawaly.net',
            'tyntec'         => 'tyntec.com',
            'smscountry'     => 'smscountry.com',
            'routesms'       => 'routesms.com',
            'skebby'         => 'skebby.it',
            'sendhub'        => 'sendhub.com',
            'upsidewireless' => 'upsidewireless.com',
            'orange'         => 'orange.com',
            'proovl'         => 'proovl.com',
            'messente'       => 'messente.com',
            'springedge'     => 'springedge.com',
            'bulksmsnigeria' => 'bulksmsnigeria.com',
            'smsru'          => 'sms.ru',
            'aspsms'         => 'aspsms.com',
            'kaleyra'        => 'kaleyra.com',
            'sendpulse'      => 'sendpulse.com',
            'mimsms'         => 'mimsms.com',
            'smsto'          => 'sms.to',
            'tiniyo'         => 'tiniyo.com',
            'vatansms'       => 'vatansms.com',
            'smsmessenger'   => 'smsmessenger.co.za',
            'vfirst'         => 'vfirst.com',
        ),
        'united states' => array(
            'telnyx' => 'telnyx.com',
        ),
        'germany'       => array(
            'gtxmessaging' => 'gtx-messaging.com',
        ),
        'africa'        => array(
            'jusibe' => 'jusibe.com',
        ),
        'arabic'        => array(
            'kwtsms' => 'kwtsms.com',
            'taqnyat' => 'taqnyat.sa',
        ),
        'bangladesh'    => array(
            'dianahost' => 'dianahost.com',
            'bulksmsbd' => 'bulksmsbd.com',
        ),
        'palestine'     => array(
            'htd' => 'htd.ps',
        ),
        'india'         => array(
            'bulksmsgateway'   => 'bulksmsgateway.in',
            'bulksmshyderabad' => 'bulksmshyderabad.co.in',
        ),
    );
    public $username;
    public $password;
    public $has_key = false;
    public $validateNumber = false;
    public $help = false;
    public $bulk_send = true;
    public $from;
    public $to;
    public $msg;
    protected $db;
    protected $tb_prefix;
    public $options;

    /**
     * @var
     */
    static $get_response;

    public function __construct()
    {
        global $wpdb;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->options   = Option::getOptions();

        // Check option for add country code to prefix numbers
        if (isset($this->options['mobile_county_code']) and $this->options['mobile_county_code']) {
            add_filter('wp_sms_to', array($this, 'applyCountryCode'));
        }

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            //add_filter( 'wp_sms_msg', array( $this, 'applyUnicode' ) );
        }

        if (isset($this->options['clean_numbers']) and $this->options['clean_numbers']) {
            add_filter('wp_sms_to', array($this, 'cleanNumbers'));
        }

        // Add Filters
        add_filter('wp_sms_to', array($this, 'modify_bulk_send'));
    }

    /**
     * Initial Gateway
     *
     * @return mixed
     */
    public static function initial()
    {
        // Set the default_gateway class
        $class_name = '\\WP_SMS\\Gateway\\Default_Gateway';
        // Include default gateway
        include_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';
        include_once WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-default.php';

        $gateway_name = Option::getOption('gateway_name');
        // Using default gateway if does not set gateway in the setting
        if (empty($gateway_name)) {
            return new $class_name();
        }

        if (is_file(WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-' . $gateway_name . '.php')) {
            include_once WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-' . $gateway_name . '.php';
        } else if (is_file(WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/class-wpsms-pro-gateway-' . $gateway_name . '.php')) {
            include_once(WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/class-wpsms-pro-gateway-' . $gateway_name . '.php');
        } else {
            return new $class_name();
        }

        // Create object from the gateway class
        if ($gateway_name == 'default') {
            $sms = new $class_name();
        } else {
            $class_name = '\\WP_SMS\\Gateway\\' . $gateway_name;
            $sms        = new $class_name();
        }

        // Set username and password
        $sms->username = Option::getOption('gateway_username');
        $sms->password = Option::getOption('gateway_password');


        $gateway_key = Option::getOption('gateway_key');

        // Set api key
        if ($sms->has_key && $gateway_key) {
            $sms->has_key = $gateway_key;
        }

        // Show gateway help configuration in gateway page
        if ($sms->help) {
            add_action('wp_sms_after_gateway', function () {
                echo ' < p class="description" > ' . $sms->help . '</p > ';
            });
        }

        // Check unit credit gateway
        if ($sms->unitrial == true) {
            $sms->unit = __('Credit', 'wp - sms');
        } else {
            $sms->unit = __('SMS', 'wp - sms');
        }

        // Set sender id
        if (!$sms->from) {
            $sms->from = Option::getOption('gateway_sender_id');
        }

        // Unset gateway key field if not available in the current gateway class.
        add_filter('wp_sms_gateway_settings', function ($filter) {
            global $sms;

            if (!$sms->has_key) {
                unset($filter['gateway_key']);
            }

            return $filter;
        });

        // Return gateway object
        return $sms;
    }

    /**
     * @param $sender
     * @param $message
     * @param $to
     * @param $response
     * @param string $status
     *
     * @return false|int
     */
    public function log($sender, $message, $to, $response, $status = 'success')
    {
        return $this->db->insert(
            $this->tb_prefix . "sms_send",
            array(
                'date'      => WP_SMS_CURRENT_DATE,
                'sender'    => $sender,
                'message'   => $message,
                'recipient' => implode(',', $to),
                'response'  => var_export($response, true),
                'status'    => $status,
            )
        );
    }

    /**
     * Apply Country code to prefix numbers
     *
     * @param $recipients
     *
     * @return array
     */
    public function applyCountryCode($recipients = array())
    {
        $countryCode = $this->options['mobile_county_code'];

        $numbers = array();
        foreach ($recipients as $number) {
            $numbers[] = preg_replace('/^(?:\\' . $countryCode . '|0)?/', $countryCode, ($number));
        }

        return $numbers;
    }

    /**
     * Apply Unicode for non-English characters
     *
     * @param string $msg
     *
     * @return string
     */
    public function applyUnicode($msg = '')
    {
        return bin2hex(mb_convert_encoding($msg, 'utf-16', 'utf-8'));
    }

    /**
     * Clean the before sending them to API.
     *
     * @param array $recipients
     *
     * @return array
     */
    public function cleanNumbers($recipients = array())
    {
        $numbers = array();
        foreach ($recipients as $recipient) {
            $numbers[] = str_replace(' ', '', $recipient);
        }

        return $numbers;
    }

    /**
     * @return mixed|void
     */
    public static function gateway()
    {
        $gateways = array(
            ''               => array(
                'default' => __('Please select your gateway', 'wp-sms'),
            ),
            'global'         => array(
                'reachinteractive' => 'reach-interactive.com',
                'octopush'         => 'octopush.com',
                'experttexting'    => 'experttexting.com',
                'fortytwo'         => 'fortytwo.com',
                'smsglobal'        => 'smsglobal.com',
                'gatewayapi'       => 'gatewayapi.com',
                'spirius'          => 'spirius.com',
                '_1s2u'            => '1s2u.com',
                'easysendsms'      => 'easysendsms.com',
                'torpedos'         => 'torpedos, smsplus.com.br',
                'smss'             => 'smss.co',
                'bearsms'          => 'bearsms',
                'cheapglobalsms'   => 'cheapglobalsms.com',
                'instantalerts'    => 'instantalerts.co',
                'mobtexting'       => 'mobtexting.com',
                'sms77'            => 'sms77.de',
                'unisender'        => 'unisender.com',
                'uwaziimobile'     => 'uwaziimobile.com',
            ),
            'united kingdom' => array(
                'reachinteractive' => 'reach-interactive.com',
                '_textplode'       => 'textplode.com',
                'textanywhere'     => 'textanywhere.net',
            ),
            'french'         => array(
                'primotexto' => 'primotexto.com',
                'mtarget'    => 'mtarget',
            ),
            'brazil'         => array(
                'sonoratecnologia' => 'sonoratecnologia.com.br',
            ),
            'germany'        => array(
                'engy' => 'engy.solutions',
            ),
            'slovakia'       => array(
                'eurosms' => 'eurosms.com',
            ),
            'turkey'         => array(
                'bulutfon' => 'bulutfon.com',
                'verimor'  => 'verimor.com.tr',
            ),
            'australia'      => array(
                'slinteractive' => 'slinteractive.com.au',
                'smssolutions'  => 'smssolutionsaustralia.com.au',
            ),
            'austria'        => array(
                'smsgatewayat' => 'sms-gateway.at',
            ),
            'spain'          => array(
                'altiria'    => 'altiria.com',
                'afilnet'    => 'afilnet.com',
                'labsmobile' => 'labsmobile.com',
                'mensatek'   => 'mensatek.com',
            ),
            'mexico'         => array(
                'altiria' => 'altiria.com',
            ),
            'colombia'       => array(
                'altiria' => 'altiria.com',
            ),
            'peru'           => array(
                'altiria' => 'altiria.com',
            ),
            'chile'          => array(
                'altiria' => 'altiria.com',
            ),
            'polish'         => array(
                'smsapi' => 'smsapi.pl',
            ),
            'france'         => array(
                'oxemis' => 'oxemis.com',
            ),
            'denmark'        => array(
                'cpsms'   => 'cpsms.dk',
                'suresms' => 'suresms.com',
            ),
            'italy'          => array(
                'smshosting' => 'smshosting.it',
                'dot4all'    => 'sms4marketing.it',
                'comilio'    => 'comilio.it',
                'aruba'      => 'aruba.it',
            ),
            'india'          => array(
                'shreesms'         => 'shreesms.net',
                'ozonesmsworld'    => 'ozonesmsworld.com',
                'smsgatewayhub'    => 'smsgatewayhub.com',
                'smsgatewaycenter' => 'smsgatewaycenter.com',
                'pridesms'         => 'pridesms.in',
                'smsozone'         => 'ozonesms.com',
                'msgwow'           => 'msgwow.com',
                'tripadasmsbox'    => 'tripadasmsbox.com'
            ),
            'iran'           => array(
                'iransmspanel'   => 'iransmspanel.ir',
                'chaparpanel'    => 'chaparpanel.ir',
                'markazpayamak'  => 'markazpayamak.ir',
                'adpdigital'     => 'adpdigital.com',
                'hostiran'       => 'hostiran.net',
                'sunwaysms'      => 'sunwaysms.com',
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
                'asiapayamak'    => 'webdade.com',
                'sharifpardazan' => '2345.ir',
                'aradpayamak'    => 'aradpayamak.net',
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
                'hirosms'        => 'hiro-sms.com',
                'onlinepanel'    => 'onlinepanel.ir',
                'rayansmspanel'  => 'rayansmspanel.ir',
                'farazsms'       => 'farazsms.com',
                'raygansms'      => 'raygansms.com',
            ),
            'arabic'         => array(
                'msegat'       => 'msegat.com',
                'oursms'       => 'oursms.net',
                'gateway'      => 'gateway.sa',
                'resalaty'     => 'resalaty.com',
                'unifonic'     => 'unifonic.com',
                'asr3sms'      => 'asr3sms.com',
                'infodomain'   => 'infodomain.asia',
                'mobiledotnet' => 'mobile.net.sa',
                'zain'         => 'zain.im',
                'malath'       => 'malath.net.sa',
                'safasms'      => 'safa-sms.com',
            ),
            'africa'         => array(
                '_ebulksms'          => 'ebulksms.com',
                'africastalking'     => 'africastalking.com',
                'smsnation'          => 'smsnation.co.rw',
                'alchemymarketinggm' => 'alchemymarketinggm.com',
            ),
            'cyprus'         => array(
                'websmscy' => 'websms.com.cy',
                'smsnetgr' => 'sms.net.gr',
            ),
            'ukraine'        => array(
                'smsc' => 'smsc.ua',
            ),
            'ghana'          => array(
                'eazismspro' => 'eazismspro.com',
            ),
            'greece'         => array(
                'smsnetgr' => 'sms.net.gr',
            ),
            'malaysia'       => array(
                'onewaysms' => 'onewaysms.com',
            ),
        );

        return apply_filters('wpsms_gateway_list', $gateways);
    }

    /**
     * @return string
     */
    public static function status()
    {
        global $sms;

        //Check that, Are we in the Gateway WP_SMS tab setting page or not?
        if (is_admin() and isset($_REQUEST['page']) and isset($_REQUEST['tab']) and $_REQUEST['page'] == 'wp-sms-settings' and $_REQUEST['tab'] == 'gateway') {

            // Get credit
            $result = $sms->GetCredit();

            if (is_wp_error($result)) {
                // Set error message
                self::$get_response = var_export($result->get_error_message(), true);

                // Update credit
                update_option('wpsms_gateway_credit', 0);

                // Return html
                return '<div class="wpsms-no-credit"><span class="dashicons dashicons-no"></span> ' . __('Deactive!', 'wp-sms') . '</div>';
            }
            // Update credit
            if (!is_object($result)) {
                update_option('wpsms_gateway_credit', $result);
            }
            self::$get_response = var_export($result, true);

            // Return html
            return '<div class="wpsms-has-credit"><span class="dashicons dashicons-yes"></span> ' . __('Active!', 'wp-sms') . '</div>';
        }
    }

    /**
     * @return mixed
     */
    public static function response()
    {
        return self::$get_response;
    }

    /**
     * @return mixed
     */
    public static function help()
    {
        global $sms;

        // Get gateway help
        $help = $sms->help;
        $document = isset($sms->documentUrl) ? $sms->documentUrl : false;
        return $document ? sprintf(__('%s <a href="%s" target="_blank">Documentation</a>', 'wp-sms'), $help, $document) : $help;
    }

    /**
     * @return mixed
     */
    public static function from()
    {
        global $sms;

        // Get gateway from
        return $sms->from;
    }

    /**
     * @return string
     */
    public static function bulk_status()
    {
        global $sms;

        // Get bulk status
        if ($sms->bulk_send == true) {
            // Return html
            return '<div class="wpsms-has-credit"><span class="dashicons dashicons-yes"></span> ' . __('Supported', 'wp-sms') . '</div>';
        } else {
            // Return html
            return '<div class="wpsms-no-credit"><span class="dashicons dashicons-no"></span> ' . __('Does not support!', 'wp-sms') . '</div>';
        }
    }

    /**
     * @return int
     */
    public static function credit()
    {
        global $sms;

        // Get credit
        $result = $sms->GetCredit();

        if (is_wp_error($result)) {
            update_option('wpsms_gateway_credit', 0);

            return 0;
        }

        if (!is_object($result)) {
            update_option('wpsms_gateway_credit', $result);
        }

        return $result;
    }

    /**
     * Modify destination number
     *
     * @param array $to
     *
     * @return array/string
     */
    public function modify_bulk_send($to)
    {
        global $sms;
        if (!$sms->bulk_send) {
            return array($to[0]);
        }

        return $to;
    }

}
