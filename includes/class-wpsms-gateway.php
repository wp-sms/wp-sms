<?php

namespace WP_SMS;

use Exception;
use WP_SMS\Components\Logger;
use WP_SMS\Components\RemoteRequest;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * WP_SMS gateway class
 */
class Gateway
{
    /**
     * Set pro gateways
     */
    public static $proGateways = array(
        'global'         => array(
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
            'whatsappcloud'  => 'business.whatsapp.com',
            'esms'           => 'esms.vn',
            'isms'           => 'isms.com.my',
            'moceansms'      => 'moceansms.com',
            'msg91'          => 'msg91.com',
            'msg360'         => 'msg360.in',
            'ovh'            => 'ovh.com',
            'livesms'        => 'livesms.eu',
            'thesmsworld'    => 'thesmsworld.com',
            'ozioma'         => 'ozioma.net',
            'pswin'          => 'pswin.com',
            'ra'             => 'ra.sa',
            'smsfactor'      => 'smsfactor.com',
            'textmarketer'   => 'textmarketer.co.uk',
            'smslive247'     => 'smslive247.com',
            'ssdindia'       => 'ssdindia.com',
            'jolis'          => 'jolis.net',
            'websms'         => 'websms.at',
            'smstrade'       => 'smstrade.de',
            'yamamah'        => 'yamamah.com',
            'cmtelecom'      => 'cmtelecom.com',
            'textlocal'      => 'textlocal.in',
            'ismartsms'      => 'ismartsms.net',
            'ooredoosms'     => 'ooredoo-sms.com',
            'txtlocal'       => 'txtlocal - textlocal.com',
            'qsms'           => 'qsms.com.au',
            'hoiio'          => 'hoiio.com',
            'textmagic'      => 'textmagic.com',
            'smsmisr'        => 'smsmisr.com',
            'smsbuddy'       => 'thesmsbuddy.com',
            'smsnoc'         => 'smsnoc.com',
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
            'releans'        => 'releans.com',
            'messente'       => 'messente.com',
            'springedge'     => 'springedge.com',
            'bulksmsnigeria' => 'bulksmsnigeria.com',
            'smsru'          => 'sms.ru',
            'kaleyra'        => 'kaleyra.com',
            'sendpulse'      => 'sendpulse.com',
            'mimsms'         => 'mimsms.com',
            'vatansms'       => 'vatansms.com',
            'smsmessenger'   => 'smsmessenger.co.za',
            'zipwhip'        => 'zipwhip.com',
            'teletopiasms'   => 'teletopiasms.no',
            'sinch'          => 'sinch.com',
            'linkmobility'   => 'linkmobility.no',
            'smspoh'         => 'smspoh.com',
            'sendinblue'     => 'sendinblue.com',
            'whatsappapi'    => 'app.whatsapp-api.net',
            'rapidsms'       => 'rapidsms.net',
            'apifon'         => 'apifon.com',
            'awssns'         => 'aws.amazon.com',
            'alphasms'       => 'alphasms.net',
            'smspapa'        => 'smspapa.com.au',
            'gupshup'        => 'gupshup.io',
            'telesign'       => 'telesign.com',
            'mysms'          => 'mysms.com',
            'brevo'          => 'brevo.com',
            'cloudtalk'      => 'cloudtalk.io'
        ),
        'united states'  => array(
            'telnyx' => 'telnyx.com',
        ),
        'germany'        => array(
            'gtxmessaging' => 'gtx-messaging.com',
        ),
        'united kingdom' => array(
            'firetext' => 'firetext.co.uk',
        ),
        'french'         => array(
            'linkmobilityFr' => 'linkmobility.fr',
        ),
        'africa'         => array(
            'jusibe'      => 'jusibe.com',
            'montymobile' => 'montymobile.com',
            'hubtel'      => 'hubtel.com',
        ),
        'romania'        => array(
            'sendsms'  => 'sendsms.ro',
            'smschef'  => 'smschef.com',
            'nobelsms' => 'nobelsms.com',
        ),
        'arabic'         => array(
            'kwtsms'      => 'kwtsms.com',
            'taqnyat'     => 'taqnyat.sa',
            'mobishastra' => 'mobishastra.com',
            'brqsms'      => 'brqsms.com',
        ),
        'bangladesh'     => array(
            'esmsbd'    => 'esms.com.bd',
            'bulksmsbd' => 'bulksmsbd.com',
            'btssms'    => 'btssms.com',
            'greenweb'  => 'greenweb.com.bd',
            'smsdone'   => 'smsd.one',
            'micron'    => 'microntechbd.com',
            'revesms'   => 'smpp.ajuratech.com',
        ),
        'china'          => array(
            'juhe' => 'juhe.cn'
        ),
        'palestine'      => array(
            'htd' => 'htd.ps',
        ),
        'pakistan'       => array(
            'sendpk' => 'sendpk.com',
        ),
        'uzbakistan'     => array(
            'eskiz' => 'eskiz.uz',
        ),
        'india'          => array(
            'bulksmsgateway'   => 'bulksmsgateway.in',
            'bulksmshyderabad' => 'bulksmshyderabad.co.in',
            'smsbharti'        => 'smsbharti.com'
        ),
        'srilanka'       => array(
            'notify' => 'notify.lk'
        ),
        'poland'         => array(
            'smseagle' => 'smseagle.eu'
        ),
        'australia'      => array(
            'smsbroadcast'      => 'smsbroadcast.com.au',
            'textteam'          => 'textteam.com.au',
            'messagemedia'      => 'messagemedia.com/au',
            'smscentral'        => 'smscentral.com.au',
        ),
        'russia'         => array(
            'sigmasms'   => 'sigmasms.ru',
            'turbosms'   => 'turbosms.ua',
            'smstraffic' => 'smstraffic.eu',
        ),
        'malaysia'       => array(
            'klasiksms' => 'klasiksms.com',
        ),
        'mexico'         => array(
            'smsmasivos' => 'smsmasivos.com.mx',
        ),
        'iran'           => array(
            'mehrafraz' => 'mehrafraz.com/fa',
        ),
        'indonesia'      => array(
            'nusasms' => 'nusasms.com',
            'smsviro' => 'smsviro.com',
        ),
        'taiwan'         => array(
            'mitake'  => 'mitake.com.tw',
            'every8d' => 'teamplus.tech',
        ),
        'thailand'       => array(
            'mailbit' => 'mailbit.co.th',
        ),
        'south korea'    => array(
            'nhncloud' => 'nhncloud.com/kr',
        ),
        'morocco'        => array(
            'bulksmsMa' => 'bulksms.ma'
        ),
        'philippine'     => array(
            'semaphore' => 'semaphore.co',
        ),
    );

    /**
     * Gateway fields
     */
    public $gatewayFields = [
        'username' => [
            'id'   => 'gateway_username',
            'name' => 'API username',
            'desc' => 'Enter API username of gateway',
        ],
        'password' => [
            'id'   => 'gateway_password',
            'name' => 'API password',
            'desc' => 'Enter API password of gateway',
        ],
        'from'     => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
        'has_key'  => [
            'id'   => 'gateway_key',
            'name' => 'API key',
            'desc' => 'Enter API key of gateway'
        ]
    ];

    /**
     * Username
     *
     * @var string
     */
    public $username;

    /**
     * Password
     *
     * @var static
     */
    public $password;

    /**
     * Has key
     *
     * @var bool
     */
    public $has_key = false;

    /**
     * Show valid number instruction
     *
     * @var bool
     */
    public $validateNumber = false;

    /**
     * Gateway notice
     *
     * @var bool
     */
    public $help = false;

    /**
     * Gateway document url
     *
     * @var bool
     */
    public $documentUrl = false;

    /**
     * Whether bulk SMS sending is supported
     *
     * @var bool
     */
    public $bulk_send = true;

    /**
     * From/Sender ID
     *
     * @var string
     */
    public $from = '';

    /**
     * Receivers numbers
     *
     * @var array
     */
    public $to;

    /**
     * Message text content
     *
     * @var string
     */
    public $msg;

    /**
     * WordPress DB object
     *
     * @var \wpdb
     */
    protected $db;

    /**
     * WordPress DB prefix
     *
     * @var string
     */
    protected $tb_prefix;

    /**
     * Gateway Option
     *
     * @var mixed|void
     */
    public $options;

    /**
     * Whether the media is supported
     *
     * @var bool
     */
    public $supportMedia = false;

    /**
     * Whether the incoming message is supported
     *
     * @var bool
     */
    public $supportIncoming = false;

    /**
     * Media URLs
     *
     * @var array
     */
    public $media = [];

    /**
     * determine the request is OTP message or standard message
     *
     * @var string
     */
    public $sms_action = '';

    /**
     * @var string
     */
    public $payload = '';

    /**
     * @var bool $isflash
     */
    public $isflash = false;

    /**
     * @var
     */
    public static $get_response;

    public function __construct()
    {
        global $wpdb;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->options   = Option::getOptions();

        if (isset($this->options['clean_numbers']) and $this->options['clean_numbers']) {
            add_filter('wp_sms_to', array($this, 'cleanNumbers'), 10);
        }

        // Check option for add country code to prefix numbers
        if (isset($this->options['mobile_county_code']) and $this->options['mobile_county_code']) {
            add_filter('wp_sms_to', array($this, 'applyCountryCode'), 20);
        }

        // Check option for send only to local numbers
        if (isset($this->options['send_only_local_numbers']) and $this->options['send_only_local_numbers']) {
            add_filter('wp_sms_to', array($this, 'sendOnlyLocalNumbers'), 20);
        }

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            //add_filter( 'wp_sms_msg', array( $this, 'applyUnicode' ) );
        }

        // Add Filters
        add_filter('wp_sms_to', array($this, 'modify_bulk_send'));

        // If there is error in sending sms send and email to admin
        add_action('wp_sms_log_after_save', array($this, 'mail_admin_sms_stopped'), 10, 7);
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
        } elseif (is_file(WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/class-wpsms-pro-gateway-' . $gateway_name . '.php')) {
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

        if (!empty($sms->gatewayFields)) {
            foreach ($sms->gatewayFields as $key => $value) {
                if ($sms->{$key} !== false) {
                    $sms->{$key} = trim(Option::getOption($value['id']));
                }
            }
        } else {
            // Set username and password
            $sms->username = trim(Option::getOption('gateway_username'));
            $sms->password = trim(Option::getOption('gateway_password'));
            $gateway_key   = trim(Option::getOption('gateway_key'));

            // Set api key
            if ($sms->has_key && $gateway_key) {
                $sms->has_key = $gateway_key;
            }

            // Set sender id
            if (!$sms->from) {
                $sms->from = trim(Option::getOption('gateway_sender_id'));
            }
        }

        // Show gateway help configuration in gateway page
        if ($sms->help) {
            add_action('wp_sms_after_gateway', function () {
                echo '<p class="description">' . esc_html($sms->help) . '</p>';
            });
        }

        // Check unit credit gateway
        if ($sms->unitrial == true) {
            $sms->unit = esc_html__('Credit', 'wp-sms');
        } else {
            $sms->unit = esc_html__('SMS', 'wp-sms');
        }

        // Unset gateway key field if not available in the current gateway class.
        add_filter('wp_sms_gateway_settings', function ($filter) {
            global $sms;

            if (!empty($sms->gatewayFields)) {
                unset($filter['gateway_username']);
                unset($filter['gateway_password']);
                unset($filter['gateway_sender_id']);
                unset($filter['gateway_key']);

                $gatewayFields = [];
                foreach ($sms->gatewayFields as $key => $value) {
                    if ($sms->{$key} !== false) {
                        $gatewayFields[$value['id']] = [
                            'id'        => $value['id'],
                            'className' => isset($value['className']) ? $value['className'] : '',
                            'name'      => $value['name'],
                            'type'      => isset($value['type']) ? $value['type'] : 'text',
                            'desc'      => $value['desc'],
                            'options'   => isset($value['options']) ? $value['options'] : array()
                        ];
                    }
                }

                $filter = array_merge(
                    array_slice($filter, 0, 3, true),
                    $gatewayFields,
                    array_slice($filter, 3, null, true)
                );
            } else {
                if (!$sms->has_key) {
                    unset($filter['gateway_key']);
                }
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
     * @param array $media
     *
     * @return false|int
     */
    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        return Logger::logOutbox($sender, $message, $to, $response, $status, $media);
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

        if (!$countryCode) {
            return $recipients;
        }

        $finalNumbers = [];

        foreach ($recipients as $recipient) {

            if (substr($recipient, 0, 2) === '00') {
                $reformattedNumber = $countryCode . substr($recipient, 2);
            } elseif (substr($recipient, 0, 1) === '0') {
                $reformattedNumber = $countryCode . substr($recipient, 1);
            } elseif (substr($recipient, 0, 1) === '+') {
                $reformattedNumber = $recipient;
            } else {
                $reformattedNumber = $countryCode . $recipient;
            }

            $finalNumbers[] = $reformattedNumber;
        }

        return $finalNumbers;
    }

    /**
     * Send SMS only to local numbers
     *
     * @param $recipients
     *
     * @return array
     */
    public function sendOnlyLocalNumbers($recipients = array())
    {
        $onlyCountriesOption = Option::getOption('only_local_numbers_countries');

        if (!$onlyCountriesOption) {
            return $recipients;
        }

        $finalNumbers = array_filter($recipients, function ($recipient) use ($onlyCountriesOption) {
            // Check if the recipient's number starts with any of the allowed country codes
            foreach ($onlyCountriesOption as $countryCode) {
                if (strpos($recipient, $countryCode) === 0) {
                    return true;
                }
            }
            return false;
        });

        return $finalNumbers;
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
            $numbers[] = str_replace(array(' ', '-', ','), '', $recipient);
        }

        return $numbers;
    }

    /**
     * @return mixed|void
     */
    public static function gateway()
    {
        $gateways = array(
            ''                     => array(
                'default' => esc_html__('Please select your gateway', 'wp-sms'),
            ),
            'global'               => array(
                'reachinteractive' => 'reach-interactive.com',
                'octopush'         => 'octopush.com',
                'experttexting'    => 'experttexting.com',
                'fortytwo'         => 'fortytwo.com',
                'mitto'            => 'mitto.ch',
                'smsglobal'        => 'smsglobal.com',
                'gatewayapi'       => 'gatewayapi.com',
                'bulkgate'         => 'bulkgate.com',
                'spirius'          => 'spirius.com',
                '_1s2u'            => '1s2u.com',
                'easysendsms'      => 'easysendsms.com',
                'wali'             => 'wali.chat',
                'torpedos'         => 'torpedos, smsplus.com.br',
                'smss'             => 'smss.co',
                'bearsms'          => 'bearsms',
                'cheapglobalsms'   => 'cheapglobalsms.com',
                'instantalerts'    => 'instantalerts.co',
                'mobtexting'       => 'mobtexting.com',
                'sms77'            => 'sms77.de (seven)',
                'unisender'        => 'unisender.com',
                'uwaziimobile'     => 'uwaziimobile.com',
                'waapi'            => 'whatsappmessagesbywaapi.co',
                'dexatel'          => 'dexatel.com',
                'aobox'            => 'aobox.it',
                'sendapp'          => 'Sendapp SMS',
                'sendappWhatsApp'  => 'Sendapp Whathapp',
                'smsto'            => 'sms.to',
            ),
            'united kingdom'       => array(
                'reachinteractive' => 'reach-interactive.com',
                '_textplode'       => 'textplode.com',
                'textanywhere'     => 'textanywhere.net',
            ),
            'french'               => array(
                'primotexto' => 'primotexto.com',
                'mtarget'    => 'mtarget',
            ),
            'brazil'               => array(
                'sonoratecnologia' => 'sonoratecnologia.com.br',
            ),
            'germany'              => array(
                'engy'     => 'engy.solutions',
                'smspoint' => 'smspoint.de'
            ),
            'romania'              => array(
                'globalvoice' => 'global-voice.net',
            ),
            'estonia'              => array(
                'dexatel' => 'dexatel.com',
            ),
            'slovakia'             => array(
                'eurosms' => 'eurosms.com',
            ),
            'switzerland'          => array(
                'aspsms' => 'aspsms.com',
            ),
            'latvia'               => array(
                'nesssolution' => 'ness-solutions.com',
            ),
            'turkey'               => array(
                'bulutfon' => 'bulutfon.com',
                'verimor'  => 'verimor.com.tr',
            ),
            'australia'            => array(
                'mobilemessage'     =>  'mobilemessage.com.au',
                'slinteractive'     => 'slinteractive.com.au',
                'smssolutions'      => 'smssolutionsaustralia.com.au',
                '_160au'            => '160.com.au',
                'gunisms'           => 'gunisms.com.au',
            ),
            'austria'              => array(
                'smsgatewayat' => 'sms-gateway.at',
            ),
            'spain'                => array(
                'altiria'    => 'altiria.com',
                'afilnet'    => 'afilnet.com',
                'labsmobile' => 'labsmobile.com',
                'mensatek'   => 'mensatek.com',
            ),
            'mexico'               => array(
                'altiria' => 'altiria.com',
            ),
            'colombia'             => array(
                'altiria' => 'altiria.com',
            ),
            'peru'                 => array(
                'altiria' => 'altiria.com',
            ),
            'chile'                => array(
                'altiria' => 'altiria.com',
            ),
            'polish'               => array(
                'smsapi' => 'smsapi.pl',
            ),
            'france'               => array(
                'oxemis'  => 'oxemis.com',
                'spothit' => 'spot-hit.fr',
            ),
            'denmark'              => array(
                'cpsms'    => 'cpsms.dk',
                'cellsynt' => 'cellsynt',
                'suresms'  => 'suresms.com',
                'prosmsdk' => 'sms.dk'
            ),
            'finland'              => array(
                'cellsynt' => 'cellsynt',
            ),
            'norway'               => array(
                'cellsynt' => 'cellsynt',
            ),
            'italy'                => array(
                'smshosting' => 'smshosting.it',
                'dot4all'    => 'sms4marketing.it',
                'comilio'    => 'comilio.it',
                'aruba'      => 'aruba.it',
            ),
            'belgium'              => array(
                'smsbox' => 'smsbox.be'
            ),
            'united arab emirates' => array(
                'callifony'       => 'callifony.com',
                'smartsmsgateway' => 'smartsmsgateway.com',
            ),
            'india'                => array(
                'tubelightcommunications' => 'tubelightcommunications.com',
                'shreesms'                => 'shreesms.net',
                'smsgatewayhub'           => 'smsgatewayhub.com',
                'smsgatewaycenter'        => 'smsgatewaycenter.com',
                'tripadasmsbox'           => 'tripadasmsbox.com',
                'callifony'               => 'callifony.com',
                'smsozone'                => 'ozonesms.com',
                'fast2sms'                => 'fast2sms.com'
            ),
            'iran'                 => array(
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
                'sms'            => 'sms.ir',
                'popaksms'       => 'popaksms.ir',
                'novin1sms'      => 'novin1sms.ir',
                '_500sms'        => '500sms.ir',
                'matinsms'       => 'MatinSMS.ir',
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
                'signalads'      => 'signalads.com'
            ),
            'arabic'               => array(
                'msegat'       => 'msegat.com',
                'oursms'       => 'oursms.net',
                'gateway'      => 'gateway.sa',
                'deewan'       => 'deewan.sa',
                'jawalbsms'    => 'jawalbsms.ws',
                'unifonic'     => 'unifonic.com',
                'asr3sms'      => 'asr3sms.com',
                'mobiledotnet' => 'mobile.net.sa',
                'zain'         => 'zain.im',
                'safasms'      => 'safa-sms.com',
                'bareedsms'    => 'bareedsms.com',
            ),
            'africa'               => array(
                '_ebulksms'          => 'ebulksms.com',
                'africastalking'     => 'africastalking.com',
            ),
            'cyprus'               => array(
                'websmscy' => 'websms.com.cy',
            ),
            'ukraine'              => array(
                'smsc' => 'smsc.ua',
            ),
            'ghana'                => array(
                'eazismspro' => 'eazismspro.com',
            ),
            'greece'               => array(
                'liveall'  => 'liveall.eu',
            ),
            'malaysia'             => array(
                'onewaysms' => 'onewaysms.com',
            ),
            'indonesia'            => array(
                'espay' => 'espay.id',
            ),
            'kenya'                => array(
                'hostpinnacle' => 'hostpinnacle.co.ke',
            ),
            'south korea'          => array(
                'directsend' => 'directsend.co.kr',
            ),
            'sweden'               => array(
                'hellosms'      => 'hellosms.se',
                'prosms'        => 'prosms.se',
                'cellsynt'      => 'cellsynt',
            ),
            'development'          => array(
                'custom' => __('Custom Gateway', 'wp-sms')
            )
        );

        if (WP_DEBUG) {
            $gateways['development']['test'] = __('Test Gateway', 'wp-sms');
        }

        return apply_filters('wpsms_gateway_list', $gateways);
    }

    /**
     * @return string
     */
    public static function status()
    {
        try {
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

                    return Helper::loadTemplate('admin/label-button.php', array(
                        'type'  => 'inactive',
                        'label' => esc_html__('Inactive', 'wp-sms')
                    ));
                }
                // Update credit
                if (!is_object($result)) {
                    update_option('wpsms_gateway_credit', $result);
                }
                self::$get_response = var_export($result, true);

                // Return html
                return Helper::loadTemplate('admin/label-button.php', array(
                    'type'  => 'active',
                    'label' => esc_html__('Active', 'wp-sms')
                ));
            }
        } catch (Exception $e) {
            self::$get_response = $e->getMessage();
            return Helper::loadTemplate('admin/label-button.php', array(
                'type'  => 'inactive',
                'label' => esc_html__('Inactive', 'wp-sms')
            ));
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
        $help     = $sms->help;
        $document = isset($sms->documentUrl) ? $sms->documentUrl : false;

        // translators: %1$s: Helpful tip, %2$s: Gateway documentation URL
        return $document ? sprintf(__('%1$s <a href="%2$s" target="_blank">Documentation</a>', 'wp-sms'), $help, $document) : (!empty($help) ? $help : __('N/A', 'wp-sms'));
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
    public static function incoming_message_status()
    {
        global $sms;

        $link = function_exists('WPSmsTwoWay') ? admin_url('admin.php?page=wp-sms-settings&tab=addon_two_way') : WP_SMS_SITE . '/product/wp-sms-two-way';

        if ($sms->supportIncoming === true) {
            return Helper::loadTemplate('admin/label-button.php', array(
                'type'  => 'active',
                'label' => sprintf('<a href="%s" target="_blank">%s</a>', $link, esc_html__('Available', 'wp-sms'))
            ));
        }

        return Helper::loadTemplate('admin/label-button.php', array(
            'type'  => 'inactive',
            'label' => sprintf('<a href="%s" target="_blank">%s</a>', $link, esc_html__('Not Available', 'wp-sms'))
        ));
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
            return Helper::loadTemplate('admin/label-button.php', array(
                'type'  => 'active',
                'label' => esc_html__('Available', 'wp-sms')
            ));
        } else {
            // Return html
            return Helper::loadTemplate('admin/label-button.php', array(
                'type'  => 'inactive',
                'label' => esc_html__('Not Available', 'wp-sms')
            ));
        }
    }

    public static function mms_status()
    {
        global $sms;

        // Get bulk status
        if ($sms->supportMedia == true) {
            // Return html
            return Helper::loadTemplate('admin/label-button.php', array(
                'type'  => 'active',
                'label' => esc_html__('Available', 'wp-sms')
            ));
        } else {
            // Return html
            return Helper::loadTemplate('admin/label-button.php', array(
                'type'  => 'inactive',
                'label' => esc_html__('Not Available', 'wp-sms')
            ));
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

    /**
     * Executes a remote HTTP request.
     *
     * @param string $method The HTTP request method (GET, POST, PUT, PATCH, DELETE, etc.).
     * @param string $url The URL of the remote resource.
     * @param array $arguments Any additional arguments to be passed to the request.
     * @param array $params Any additional parameters to be passed to the request.
     * @param bool $throwFailedHttpCodeResponse Whether or not to throw an exception if the request returns a failed HTTP code.
     * @return object The response body of the remote request.
     * @throws Exception If the request fails and $throwFailedHttpCodeResponse is true.
     */
    protected function request($method, $url, $arguments = [], $params = [], $throwFailedHttpCodeResponse = true)
    {
        $request = new RemoteRequest($method, $url, $arguments, $params);

        return $request->execute($throwFailedHttpCodeResponse);
    }

    /**
     * Fetch the template ID from message body
     *
     *
     * @return array|void
     * @example In the message body "Hello World|1234" It returns array('template_id' => 1234, 'message' => 'Hello World')
     *
     */
    protected function getTemplateIdAndMessageBody()
    {
        $message_body = explode("|", $this->msg);

        if (isset($message_body[1]) && $message_body[1]) {
            return array(
                'template_id' => trim($message_body[1]),
                'message'     => trim($message_body[0])
            );
        }
    }

    /**
     * Convert non-english messages to Unicode
     *
     * @param $message
     *
     * @return string
     */
    protected function convertToUnicode($message)
    {
        $chrArray[0]       = "،";
        $unicodeArray[0]   = "060C";
        $chrArray[1]       = "؛";
        $unicodeArray[1]   = "061B";
        $chrArray[2]       = "؟";
        $unicodeArray[2]   = "061F";
        $chrArray[3]       = "ء";
        $unicodeArray[3]   = "0621";
        $chrArray[4]       = "آ";
        $unicodeArray[4]   = "0622";
        $chrArray[5]       = "أ";
        $unicodeArray[5]   = "0623";
        $chrArray[6]       = "ؤ";
        $unicodeArray[6]   = "0624";
        $chrArray[7]       = "إ";
        $unicodeArray[7]   = "0625";
        $chrArray[8]       = "ئ";
        $unicodeArray[8]   = "0626";
        $chrArray[9]       = "ا";
        $unicodeArray[9]   = "0627";
        $chrArray[10]      = "ب";
        $unicodeArray[10]  = "0628";
        $chrArray[11]      = "ة";
        $unicodeArray[11]  = "0629";
        $chrArray[12]      = "ت";
        $unicodeArray[12]  = "062A";
        $chrArray[13]      = "ث";
        $unicodeArray[13]  = "062B";
        $chrArray[14]      = "ج";
        $unicodeArray[14]  = "062C";
        $chrArray[15]      = "ح";
        $unicodeArray[15]  = "062D";
        $chrArray[16]      = "خ";
        $unicodeArray[16]  = "062E";
        $chrArray[17]      = "د";
        $unicodeArray[17]  = "062F";
        $chrArray[18]      = "ذ";
        $unicodeArray[18]  = "0630";
        $chrArray[19]      = "ر";
        $unicodeArray[19]  = "0631";
        $chrArray[20]      = "ز";
        $unicodeArray[20]  = "0632";
        $chrArray[21]      = "س";
        $unicodeArray[21]  = "0633";
        $chrArray[22]      = "ش";
        $unicodeArray[22]  = "0634";
        $chrArray[23]      = "ص";
        $unicodeArray[23]  = "0635";
        $chrArray[24]      = "ض";
        $unicodeArray[24]  = "0636";
        $chrArray[25]      = "ط";
        $unicodeArray[25]  = "0637";
        $chrArray[26]      = "ظ";
        $unicodeArray[26]  = "0638";
        $chrArray[27]      = "ع";
        $unicodeArray[27]  = "0639";
        $chrArray[28]      = "غ";
        $unicodeArray[28]  = "063A";
        $chrArray[29]      = "ف";
        $unicodeArray[29]  = "0641";
        $chrArray[30]      = "ق";
        $unicodeArray[30]  = "0642";
        $chrArray[31]      = "ك";
        $unicodeArray[31]  = "0643";
        $chrArray[32]      = "ل";
        $unicodeArray[32]  = "0644";
        $chrArray[33]      = "م";
        $unicodeArray[33]  = "0645";
        $chrArray[34]      = "ن";
        $unicodeArray[34]  = "0646";
        $chrArray[35]      = "ه";
        $unicodeArray[35]  = "0647";
        $chrArray[36]      = "و";
        $unicodeArray[36]  = "0648";
        $chrArray[37]      = "ى";
        $unicodeArray[37]  = "0649";
        $chrArray[38]      = "ي";
        $unicodeArray[38]  = "064A";
        $chrArray[39]      = "ـ";
        $unicodeArray[39]  = "0640";
        $chrArray[40]      = "ً";
        $unicodeArray[40]  = "064B";
        $chrArray[41]      = "ٌ";
        $unicodeArray[41]  = "064C";
        $chrArray[42]      = "ٍ";
        $unicodeArray[42]  = "064D";
        $chrArray[43]      = "َ";
        $unicodeArray[43]  = "064E";
        $chrArray[44]      = "ُ";
        $unicodeArray[44]  = "064F";
        $chrArray[45]      = "ِ";
        $unicodeArray[45]  = "0650";
        $chrArray[46]      = "ّ";
        $unicodeArray[46]  = "0651";
        $chrArray[47]      = "ْ";
        $unicodeArray[47]  = "0652";
        $chrArray[48]      = "!";
        $unicodeArray[48]  = "0021";
        $chrArray[49]      = '"';
        $unicodeArray[49]  = "0022";
        $chrArray[50]      = "#";
        $unicodeArray[50]  = "0023";
        $chrArray[51]      = "$";
        $unicodeArray[51]  = "0024";
        $chrArray[52]      = "%";
        $unicodeArray[52]  = "0025";
        $chrArray[53]      = "&";
        $unicodeArray[53]  = "0026";
        $chrArray[54]      = "'";
        $unicodeArray[54]  = "0027";
        $chrArray[55]      = "(";
        $unicodeArray[55]  = "0028";
        $chrArray[56]      = ")";
        $unicodeArray[56]  = "0029";
        $chrArray[57]      = "*";
        $unicodeArray[57]  = "002A";
        $chrArray[58]      = "+";
        $unicodeArray[58]  = "002B";
        $chrArray[59]      = ",";
        $unicodeArray[59]  = "002C";
        $chrArray[60]      = "-";
        $unicodeArray[60]  = "002D";
        $chrArray[61]      = ".";
        $unicodeArray[61]  = "002E";
        $chrArray[62]      = "/";
        $unicodeArray[62]  = "002F";
        $chrArray[63]      = "0";
        $unicodeArray[63]  = "0030";
        $chrArray[64]      = "1";
        $unicodeArray[64]  = "0031";
        $chrArray[65]      = "2";
        $unicodeArray[65]  = "0032";
        $chrArray[66]      = "3";
        $unicodeArray[66]  = "0033";
        $chrArray[67]      = "4";
        $unicodeArray[67]  = "0034";
        $chrArray[68]      = "5";
        $unicodeArray[68]  = "0035";
        $chrArray[69]      = "6";
        $unicodeArray[69]  = "0036";
        $chrArray[70]      = "7";
        $unicodeArray[70]  = "0037";
        $chrArray[71]      = "8";
        $unicodeArray[71]  = "0038";
        $chrArray[72]      = "9";
        $unicodeArray[72]  = "0039";
        $chrArray[73]      = ":";
        $unicodeArray[73]  = "003A";
        $chrArray[74]      = ";";
        $unicodeArray[74]  = "003B";
        $chrArray[75]      = "<";
        $unicodeArray[75]  = "003C";
        $chrArray[76]      = "=";
        $unicodeArray[76]  = "003D";
        $chrArray[77]      = ">";
        $unicodeArray[77]  = "003E";
        $chrArray[78]      = "?";
        $unicodeArray[78]  = "003F";
        $chrArray[79]      = "@";
        $unicodeArray[79]  = "0040";
        $chrArray[80]      = "A";
        $unicodeArray[80]  = "0041";
        $chrArray[81]      = "B";
        $unicodeArray[81]  = "0042";
        $chrArray[82]      = "C";
        $unicodeArray[82]  = "0043";
        $chrArray[83]      = "D";
        $unicodeArray[83]  = "0044";
        $chrArray[84]      = "E";
        $unicodeArray[84]  = "0045";
        $chrArray[85]      = "F";
        $unicodeArray[85]  = "0046";
        $chrArray[86]      = "G";
        $unicodeArray[86]  = "0047";
        $chrArray[87]      = "H";
        $unicodeArray[87]  = "0048";
        $chrArray[88]      = "I";
        $unicodeArray[88]  = "0049";
        $chrArray[89]      = "J";
        $unicodeArray[89]  = "004A";
        $chrArray[90]      = "K";
        $unicodeArray[90]  = "004B";
        $chrArray[91]      = "L";
        $unicodeArray[91]  = "004C";
        $chrArray[92]      = "M";
        $unicodeArray[92]  = "004D";
        $chrArray[93]      = "N";
        $unicodeArray[93]  = "004E";
        $chrArray[94]      = "O";
        $unicodeArray[94]  = "004F";
        $chrArray[95]      = "P";
        $unicodeArray[95]  = "0050";
        $chrArray[96]      = "Q";
        $unicodeArray[96]  = "0051";
        $chrArray[97]      = "R";
        $unicodeArray[97]  = "0052";
        $chrArray[98]      = "S";
        $unicodeArray[98]  = "0053";
        $chrArray[99]      = "T";
        $unicodeArray[99]  = "0054";
        $chrArray[100]     = "U";
        $unicodeArray[100] = "0055";
        $chrArray[101]     = "V";
        $unicodeArray[101] = "0056";
        $chrArray[102]     = "W";
        $unicodeArray[102] = "0057";
        $chrArray[103]     = "X";
        $unicodeArray[103] = "0058";
        $chrArray[104]     = "Y";
        $unicodeArray[104] = "0059";
        $chrArray[105]     = "Z";
        $unicodeArray[105] = "005A";
        $chrArray[106]     = "[";
        $unicodeArray[106] = "005B";
        $char              = "\ ";
        $chrArray[107]     = trim($char);
        $unicodeArray[107] = "005C";
        $chrArray[108]     = "]";
        $unicodeArray[108] = "005D";
        $chrArray[109]     = "^";
        $unicodeArray[109] = "005E";
        $chrArray[110]     = "_";
        $unicodeArray[110] = "005F";
        $chrArray[111]     = "`";
        $unicodeArray[111] = "0060";
        $chrArray[112]     = "a";
        $unicodeArray[112] = "0061";
        $chrArray[113]     = "b";
        $unicodeArray[113] = "0062";
        $chrArray[114]     = "c";
        $unicodeArray[114] = "0063";
        $chrArray[115]     = "d";
        $unicodeArray[115] = "0064";
        $chrArray[116]     = "e";
        $unicodeArray[116] = "0065";
        $chrArray[117]     = "f";
        $unicodeArray[117] = "0066";
        $chrArray[118]     = "g";
        $unicodeArray[118] = "0067";
        $chrArray[119]     = "h";
        $unicodeArray[119] = "0068";
        $chrArray[120]     = "i";
        $unicodeArray[120] = "0069";
        $chrArray[121]     = "j";
        $unicodeArray[121] = "006A";
        $chrArray[122]     = "k";
        $unicodeArray[122] = "006B";
        $chrArray[123]     = "l";
        $unicodeArray[123] = "006C";
        $chrArray[124]     = "m";
        $unicodeArray[124] = "006D";
        $chrArray[125]     = "n";
        $unicodeArray[125] = "006E";
        $chrArray[126]     = "o";
        $unicodeArray[126] = "006F";
        $chrArray[127]     = "p";
        $unicodeArray[127] = "0070";
        $chrArray[128]     = "q";
        $unicodeArray[128] = "0071";
        $chrArray[129]     = "r";
        $unicodeArray[129] = "0072";
        $chrArray[130]     = "s";
        $unicodeArray[130] = "0073";
        $chrArray[131]     = "t";
        $unicodeArray[131] = "0074";
        $chrArray[132]     = "u";
        $unicodeArray[132] = "0075";
        $chrArray[133]     = "v";
        $unicodeArray[133] = "0076";
        $chrArray[134]     = "w";
        $unicodeArray[134] = "0077";
        $chrArray[135]     = "x";
        $unicodeArray[135] = "0078";
        $chrArray[136]     = "y";
        $unicodeArray[136] = "0079";
        $chrArray[137]     = "z";
        $unicodeArray[137] = "007A";
        $chrArray[138]     = "{";
        $unicodeArray[138] = "007B";
        $chrArray[139]     = "|";
        $unicodeArray[139] = "007C";
        $chrArray[140]     = "}";
        $unicodeArray[140] = "007D";
        $chrArray[141]     = "~";
        $unicodeArray[141] = "007E";
        $chrArray[142]     = "©";
        $unicodeArray[142] = "00A9";
        $chrArray[143]     = "®";
        $unicodeArray[143] = "00AE";
        $chrArray[144]     = "÷";
        $unicodeArray[144] = "00F7";
        $chrArray[145]     = "×";
        $unicodeArray[145] = "00F7";
        $chrArray[146]     = "§";
        $unicodeArray[146] = "00A7";
        $chrArray[147]     = " ";
        $unicodeArray[147] = "0020";
        $chrArray[148]     = "\n";
        $unicodeArray[148] = "000D";
        $chrArray[149]     = "\r";
        $unicodeArray[149] = "000A";

        $strResult = "";
        for ($i = 0; $i < strlen($message); $i++) {
            if (in_array(substr($message, $i, 1), $chrArray)) {
                $strResult .= $unicodeArray[array_search(substr($message, $i, 1), $chrArray)];
            }
        }

        return $strResult;
    }

    public function mail_admin_sms_stopped($result, $sender, $message, $to, $response, $status, $media)
    {
        if ($status == 'error' and (isset($this->options['notify_errors_to_admin_email']) && $this->options['notify_errors_to_admin_email'])) {
            $siteName = get_bloginfo('name');
            // translators: %s: Site name
            $subject = sprintf(esc_html__('%s - SMS Sending Alert', 'wp-sms'), $siteName);
            $content = Helper::loadTemplate('email/partials/sms-delivery-issue.php', [
                'message'  => $message,
                'response' => $response,
                'to'       => $to,
            ]);

            Helper::sendMail($subject, [
                'email_title' => esc_html__('SMS Delivery Issue', 'wp-sms'),
                'content'     => $content,
                'site_url'    => home_url(),
                'site_name'   => $siteName,
                'cta_title'   => esc_html__('Check SMS gateway configuration', 'wp-sms'),
                'cta_link'    => admin_url('admin.php?page=wp-sms-settings&tab=gateway'),
            ]);
        }
    }
}
