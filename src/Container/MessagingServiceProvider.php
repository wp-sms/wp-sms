<?php

namespace WSms\Container;

use WSms\Contact\StatusPropagator;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Email\EmailHeaderComposer;
use WSms\Messaging\Email\UnsubscribeTokenService;
use WSms\Messaging\Gateway\Email\MailtrapGateway;
use WSms\Messaging\Gateway\Email\WpMailGateway;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Gateway\Provider\AfilnetProvider;
use WSms\Messaging\Gateway\Provider\AfricasTalkingProvider;
use WSms\Messaging\Gateway\Provider\AlphaSmsProvider;
use WSms\Messaging\Gateway\Provider\AltiriaProvider;
use WSms\Messaging\Gateway\Provider\AoboxProvider;
use WSms\Messaging\Gateway\Provider\ApifonProvider;
use WSms\Messaging\Gateway\Provider\AriaCPProvider;
use WSms\Messaging\Gateway\Provider\ArubaProvider;
use WSms\Messaging\Gateway\Provider\AspSmsProvider;
use WSms\Messaging\Gateway\Provider\AwsSnsProvider;
use WSms\Messaging\Gateway\Provider\BareedSmsProvider;
use WSms\Messaging\Gateway\Provider\BandwidthProvider;
use WSms\Messaging\Gateway\Provider\BrevoProvider;
use WSms\Messaging\Gateway\Provider\BrqSmsProvider;
use WSms\Messaging\Gateway\Provider\BtsSmsProvider;
use WSms\Messaging\Gateway\Provider\BulkSMSbdProvider;
use WSms\Messaging\Gateway\Provider\BulkSMSgatewayProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsHyderabadProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsMaProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsNigeriaProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsProvider;
use WSms\Messaging\Gateway\Provider\BulkgateProvider;
use WSms\Messaging\Gateway\Provider\BulutfonProvider;
use WSms\Messaging\Gateway\Provider\CallifonyProvider;
use WSms\Messaging\Gateway\Provider\CellsyntProvider;
use WSms\Messaging\Gateway\Provider\CloudTalkProvider;
use WSms\Messaging\Gateway\Provider\CheapglobalSmsProvider;
use WSms\Messaging\Gateway\Provider\ClickSendProvider;
use WSms\Messaging\Gateway\Provider\ClickatellProvider;
use WSms\Messaging\Gateway\Provider\ComilioProvider;
use WSms\Messaging\Gateway\Provider\CpsmsProvider;
use WSms\Messaging\Gateway\Provider\DeewanProvider;
use WSms\Messaging\Gateway\Provider\DexatelProvider;
use WSms\Messaging\Gateway\Provider\DirectsendProvider;
use WSms\Messaging\Gateway\Provider\EbulkSmsProvider;
use WSms\Messaging\Gateway\Provider\EasySendSmsProvider;
use WSms\Messaging\Gateway\Provider\EaziSMSproProvider;
use WSms\Messaging\Gateway\Provider\EngyProvider;
use WSms\Messaging\Gateway\Provider\EspayProvider;
use WSms\Messaging\Gateway\Provider\EuroSmsProvider;
use WSms\Messaging\Gateway\Provider\ExpertTextingProvider;
use WSms\Messaging\Gateway\Provider\FarapayamakProvider;
use WSms\Messaging\Gateway\Provider\FarazSmsProvider;
use WSms\Messaging\Gateway\Provider\Fast2SmsProvider;
use WSms\Messaging\Gateway\Provider\FortyTwoProvider;
use WSms\Messaging\Gateway\Provider\GatewayApiProvider;
use WSms\Messaging\Gateway\Provider\GatewaySaProvider;
use WSms\Messaging\Gateway\Provider\GlobalVoiceProvider;
use WSms\Messaging\Gateway\Provider\GunismsProvider;
use WSms\Messaging\Gateway\Provider\HelloSmsProvider;
use WSms\Messaging\Gateway\Provider\HostIranProvider;
use WSms\Messaging\Gateway\Provider\HostPinnacleProvider;
use WSms\Messaging\Gateway\Provider\InfobipProvider;
use WSms\Messaging\Gateway\Provider\InstantalertsProvider;
use WSms\Messaging\Gateway\Provider\JawalBSmsProvider;
use WSms\Messaging\Gateway\Provider\KavenegarProvider;
use WSms\Messaging\Gateway\Provider\LabsMobileProvider;
use WSms\Messaging\Gateway\Provider\LiveAllProvider;
use WSms\Messaging\Gateway\Provider\MensatekProvider;
use WSms\Messaging\Gateway\Provider\MittoProvider;
use WSms\Messaging\Gateway\Provider\MobiledotnetProvider;
use WSms\Messaging\Gateway\Provider\MsegatProvider;
use WSms\Messaging\Gateway\Provider\MtargetProvider;
use WSms\Messaging\Gateway\Provider\NesssolutionProvider;
use WSms\Messaging\Gateway\Provider\NetGsmProvider;
use WSms\Messaging\Gateway\Provider\OctopushProvider;
use WSms\Messaging\Gateway\Provider\_160auProvider;
use WSms\Messaging\Gateway\Provider\_1s2uProvider;
use WSms\Messaging\Gateway\Provider\_4jawalyProvider;
use WSms\Messaging\Gateway\Provider\OneWaySmsProvider;
use WSms\Messaging\Gateway\Provider\OurSmsProvider;
use WSms\Messaging\Gateway\Provider\OvhProvider;
use WSms\Messaging\Gateway\Provider\OxemisProvider;
use WSms\Messaging\Gateway\Provider\PayamresanProvider;
use WSms\Messaging\Gateway\Provider\PlivoProvider;
use WSms\Messaging\Gateway\Provider\PrimotextoProvider;
use WSms\Messaging\Gateway\Provider\ProSmsProvider;
use WSms\Messaging\Gateway\Provider\ProsmsdkProvider;
use WSms\Messaging\Gateway\Provider\RazpayamakProvider;
use WSms\Messaging\Gateway\Provider\SafaSmsProvider;
use WSms\Messaging\Gateway\Provider\SendappProvider;
use WSms\Messaging\Gateway\Provider\SendappWhatsappProvider;
use WSms\Messaging\Gateway\Provider\SevenProvider;
use WSms\Messaging\Gateway\Provider\SinchProvider;
use WSms\Messaging\Gateway\Provider\SlinteractiveProvider;
use WSms\Messaging\Gateway\Provider\SmsApiProvider;
use WSms\Messaging\Gateway\Provider\SmsboxProvider;
use WSms\Messaging\Gateway\Provider\SmscProvider;
use WSms\Messaging\Gateway\Provider\SmsesProvider;
use WSms\Messaging\Gateway\Provider\SmsgatewayatProvider;
use WSms\Messaging\Gateway\Provider\SmsGatewayCenterProvider;
use WSms\Messaging\Gateway\Provider\SmsGatewayHubProvider;
use WSms\Messaging\Gateway\Provider\SmsGlobalProvider;
use WSms\Messaging\Gateway\Provider\SmshostingProvider;
use WSms\Messaging\Gateway\Provider\SmspointProvider;
use WSms\Messaging\Gateway\Provider\SmssolutionsProvider;
use WSms\Messaging\Gateway\Provider\SmstoProvider;
use WSms\Messaging\Gateway\Provider\SpiriusProvider;
use WSms\Messaging\Gateway\Provider\SpotHitProvider;
use WSms\Messaging\Gateway\Provider\SureSmsProvider;
use WSms\Messaging\Gateway\Provider\TaqnyatProvider;
use WSms\Messaging\Gateway\Provider\TelnyxProvider;
use WSms\Messaging\Gateway\Provider\TextAnywhereProvider;
use WSms\Messaging\Gateway\Provider\TextplodeProvider;
use WSms\Messaging\Gateway\Provider\TubelightCommunicationsProvider;
use WSms\Messaging\Gateway\Provider\TwilioProvider;
use WSms\Messaging\Gateway\Provider\UwaziimobileProvider;
use WSms\Messaging\Gateway\Provider\SmsIrProvider;
use WSms\Messaging\Gateway\Provider\UnifonicProvider;
use WSms\Messaging\Gateway\Provider\UnisenderProvider;
use WSms\Messaging\Gateway\Provider\VerimorProvider;
use WSms\Messaging\Gateway\Provider\VonageProvider;
use WSms\Messaging\Gateway\Provider\WaliProvider;
use WSms\Messaging\Gateway\Provider\ZainProvider;
use WSms\Messaging\Gateway\Provider\_0098smsProvider;
use WSms\Messaging\Gateway\Provider\_18smsProvider;
use WSms\Messaging\Gateway\Provider\AdpDigitalProvider;
use WSms\Messaging\Gateway\Provider\AradSmsProvider;
use WSms\Messaging\Gateway\Provider\AsanakProvider;
use WSms\Messaging\Gateway\Provider\HiroSmsProvider;
use WSms\Messaging\Gateway\Provider\IdehPayamProvider;
use WSms\Messaging\Gateway\Provider\IranSmsPanelProvider;
use WSms\Messaging\Gateway\Provider\IranspkProvider;
use WSms\Messaging\Gateway\Provider\IsmsieProvider;
use WSms\Messaging\Gateway\Provider\JahanPayamakProvider;
use WSms\Messaging\Gateway\Provider\LoginPanelProvider;
use WSms\Messaging\Gateway\Provider\MarkazPayamakProvider;
use WSms\Messaging\Gateway\Provider\MatinSmsProvider;
use WSms\Messaging\Gateway\Provider\MdPanelProvider;
use WSms\Messaging\Gateway\Provider\MedianaProvider;
use WSms\Messaging\Gateway\Provider\MeliPayamakProvider;
use WSms\Messaging\Gateway\Provider\MessageBirdProvider;
use WSms\Messaging\Gateway\Provider\NasrPayamProvider;
use WSms\Messaging\Gateway\Provider\Novin1SmsProvider;
use WSms\Messaging\Gateway\Provider\PayamakPanelProvider;
use WSms\Messaging\Gateway\Provider\PayameRozProvider;
use WSms\Messaging\Gateway\Provider\RayganSmsProvider;
use WSms\Messaging\Gateway\Provider\SignalAdsProvider;
use WSms\Messaging\Gateway\Provider\SmsBanProvider;
use WSms\Messaging\Gateway\Provider\SmsBartarProvider;
use WSms\Messaging\Gateway\Provider\SmsCallProvider;
use WSms\Messaging\Gateway\Provider\SmsClickProvider;
use WSms\Messaging\Gateway\Provider\SmsHooshmandProvider;
use WSms\Messaging\Gateway\Provider\SmsLineProvider;
use WSms\Messaging\Gateway\Provider\SmsMelliProvider;
use WSms\Messaging\Gateway\Provider\SmsServiceProvider;
use WSms\Messaging\Gateway\Provider\SmsToosProvider;
use WSms\Messaging\Gateway\Provider\SsmssProvider;
use WSms\Messaging\Gateway\Provider\TextSmsProvider;
use WSms\Messaging\Gateway\Provider\AdsPanelProvider;
use WSms\Messaging\Gateway\Provider\AfeProvider;
use WSms\Messaging\Gateway\Provider\ArkaPayamakProvider;
use WSms\Messaging\Gateway\Provider\AvalPayamProvider;
use WSms\Messaging\Gateway\Provider\BandarSmsProvider;
use WSms\Messaging\Gateway\Provider\BestitProvider;
use WSms\Messaging\Gateway\Provider\CandooSmsProvider;
use WSms\Messaging\Gateway\Provider\ChapargahProvider;
use WSms\Messaging\Gateway\Provider\FirstPayamakProvider;
use WSms\Messaging\Gateway\Provider\GhasedakProvider;
use WSms\Messaging\Gateway\Provider\ImenCmsProvider;
use WSms\Messaging\Gateway\Provider\MyDnsPanelProvider;
use WSms\Messaging\Gateway\Provider\OnlinePanelProvider;
use WSms\Messaging\Gateway\Provider\PaazProvider;
use WSms\Messaging\Gateway\Provider\ParsaSmsProvider;
use WSms\Messaging\Gateway\Provider\ParsGreenProvider;
use WSms\Messaging\Gateway\Provider\PersianSmsProvider;
use WSms\Messaging\Gateway\Provider\RayanSmsPanelProvider;
use WSms\Messaging\Gateway\Provider\SabanovinProvider;
use WSms\Messaging\Gateway\Provider\SunwaySmsProvider;
use WSms\Messaging\Gateway\Provider\TsmsProvider;
use WSms\Messaging\Gateway\Line\LineGateway;
use WSms\Messaging\Gateway\Telegram\TelegramGateway;
use WSms\Messaging\Gateway\TestGateway;
use WSms\Messaging\Gateway\Webhook\HttpWebhookGateway;
use WSms\Messaging\Inbound\KeywordMatcher;
use WSms\Messaging\Inbound\OptOutManager;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\SuppressionGuard;
use WSms\Messaging\Template\MustacheEngine;

defined('ABSPATH') || exit;

class MessagingServiceProvider implements ServiceProvider
{
    /** @var array<string, class-string> Provider ID => class name for deferred registration */
    private const PROVIDERS = [
        'twilio'         => TwilioProvider::class,
        'uwaziimobile'   => UwaziimobileProvider::class,
        'vonage'         => VonageProvider::class,
        'kavenegar'      => KavenegarProvider::class,
        'razpayamak'     => RazpayamakProvider::class,
        'farapayamak'    => FarapayamakProvider::class,
        'payamakaria'    => AriaCPProvider::class,
        'farazsms'       => FarazSmsProvider::class,
        'hostiran'       => HostIranProvider::class,
        'octopush'       => OctopushProvider::class,
        'ovh'            => OvhProvider::class,
        'netgsm'         => NetGsmProvider::class,
        'smsir'          => SmsIrProvider::class,
        'payamresan'     => PayamresanProvider::class,
        'plivo'          => PlivoProvider::class,
        'primotexto'     => PrimotextoProvider::class,
        'prosms'         => ProSmsProvider::class,
        'africastalking' => AfricasTalkingProvider::class,
        'apifon'         => ApifonProvider::class,
        'sinch'          => SinchProvider::class,
        'labsmobile'     => LabsMobileProvider::class,
        'liveall'        => LiveAllProvider::class,
        'infobip'        => InfobipProvider::class,
        'instantalerts'  => InstantalertsProvider::class,
        'messagebird'    => MessageBirdProvider::class,
        'jawalbsms'      => JawalBSmsProvider::class,
        'gatewayapi'     => GatewayApiProvider::class,
        'gateway'        => GatewaySaProvider::class,
        'gunisms'        => GunismsProvider::class,
        'globalvoice'    => GlobalVoiceProvider::class,
        'aspsms'         => AspSmsProvider::class,
        'smsglobal'      => SmsGlobalProvider::class,
        'smsapi'         => SmsApiProvider::class,
        'smsbox'         => SmsboxProvider::class,
        'easysendsms'    => EasySendSmsProvider::class,
        'espay'          => EspayProvider::class,
        'eurosms'        => EuroSmsProvider::class,
        'engy'           => EngyProvider::class,
        'mitto'          => MittoProvider::class,
        'smsto'          => SmstoProvider::class,
        'bareedsms'      => BareedSmsProvider::class,
        'bandwidth'      => BandwidthProvider::class,
        'brevo'          => BrevoProvider::class,
        'brqsms'         => BrqSmsProvider::class,
        'btssms'         => BtsSmsProvider::class,
        'bulksms'        => BulkSmsProvider::class,
        'bulksmsbd'      => BulkSMSbdProvider::class,
        'bulksmsgateway' => BulkSMSgatewayProvider::class,
        'bulksmshyderabad' => BulkSmsHyderabadProvider::class,
        'bulksmsma'      => BulkSmsMaProvider::class,
        'bulksmsnigeria' => BulkSmsNigeriaProvider::class,
        'bulkgate'       => BulkgateProvider::class,
        'cellsynt'       => CellsyntProvider::class,
        'cloudtalk'      => CloudTalkProvider::class,
        'comilio'        => ComilioProvider::class,
        'dexatel'        => DexatelProvider::class,
        'directsend'     => DirectsendProvider::class,
        'hellosms'       => HelloSmsProvider::class,
        'hostpinnacle'   => HostPinnacleProvider::class,
        'unifonic'       => UnifonicProvider::class,
        'deewan'         => DeewanProvider::class,
        'ebulksms'       => EbulkSmsProvider::class,
        'msegat'         => MsegatProvider::class,
        'mobiledotnet'   => MobiledotnetProvider::class,
        'wali'           => WaliProvider::class,
        'zain'           => ZainProvider::class,
        'mtarget'        => MtargetProvider::class,
        'fast2sms'       => Fast2SmsProvider::class,
        'smsc'           => SmscProvider::class,
        'spothit'        => SpotHitProvider::class,
        'smshosting'     => SmshostingProvider::class,
        'textanywhere'   => TextAnywhereProvider::class,
        'suresms'        => SureSmsProvider::class,
        'oxemis'         => OxemisProvider::class,
        'verimor'        => VerimorProvider::class,
        'smsgatewayhub'    => SmsGatewayHubProvider::class,
        'smsgatewaycenter' => SmsGatewayCenterProvider::class,
        'oursms'         => OurSmsProvider::class,
        'fortytwo'       => FortyTwoProvider::class,
        'textplode'      => TextplodeProvider::class,
        'smses'          => SmsesProvider::class,
        'smspoint'       => SmspointProvider::class,
        'smssolutions'   => SmssolutionsProvider::class,
        'spirius'        => SpiriusProvider::class,
        'seven'          => SevenProvider::class,
        'mensatek'       => MensatekProvider::class,
        'unisender'      => UnisenderProvider::class,
        'afilnet'        => AfilnetProvider::class,
        'tubelightcommunications' => TubelightCommunicationsProvider::class,
        'taqnyat'        => TaqnyatProvider::class,
        'altiria'        => AltiriaProvider::class,
        'aruba'          => ArubaProvider::class,
        'aobox'          => AoboxProvider::class,
        'awssns'         => AwsSnsProvider::class,
        '160au'          => _160auProvider::class,
        '1s2u'           => _1s2uProvider::class,
        '4jawaly'        => _4jawalyProvider::class,
        'bulutfon'       => BulutfonProvider::class,
        'cpsms'          => CpsmsProvider::class,
        'callifony'      => CallifonyProvider::class,
        'eazismspro'     => EaziSMSproProvider::class,
        'experttexting'  => ExpertTextingProvider::class,
        'onewaysms'      => OneWaySmsProvider::class,
        'nesssolution'   => NesssolutionProvider::class,
        'smsgatewayat'   => SmsgatewayatProvider::class,
        'slinteractive'  => SlinteractiveProvider::class,
        'sendapp'        => SendappProvider::class,
        'sendappwhatsapp' => SendappWhatsappProvider::class,
        'safasms'        => SafaSmsProvider::class,
        'prosmsdk'       => ProsmsdkProvider::class,
        'cheapglobalsms' => CheapglobalSmsProvider::class,
        'clicksend'      => ClickSendProvider::class,
        'clickatell'     => ClickatellProvider::class,
        'alphasms'       => AlphaSmsProvider::class,
        'telnyx'         => TelnyxProvider::class,

        // Iranian gateways ported from v7. APIs are reachable only from inside Iran;
        // every provider in this block ships with `TESTED = false` until manually verified.
        '0098sms'        => _0098smsProvider::class,
        '18sms'          => _18smsProvider::class,
        'adpdigital'     => AdpDigitalProvider::class,
        'aradsms'        => AradSmsProvider::class,
        'asanak'         => AsanakProvider::class,
        'hirosms'        => HiroSmsProvider::class,
        'idehpayam'      => IdehPayamProvider::class,
        'iransmspanel'   => IranSmsPanelProvider::class,
        'iranspk'        => IranspkProvider::class,
        'ismsie'         => IsmsieProvider::class,
        'jahanpayamak'   => JahanPayamakProvider::class,
        'loginpanel'     => LoginPanelProvider::class,
        'markazpayamak'  => MarkazPayamakProvider::class,
        'matinsms'       => MatinSmsProvider::class,
        'mdpanel'        => MdPanelProvider::class,
        'mediana'        => MedianaProvider::class,
        'melipayamak'    => MeliPayamakProvider::class,
        'nasrpayam'      => NasrPayamProvider::class,
        'novin1sms'      => Novin1SmsProvider::class,
        'payamakpanel'   => PayamakPanelProvider::class,
        'payameroz'      => PayameRozProvider::class,
        'raygansms'      => RayganSmsProvider::class,
        'signalads'      => SignalAdsProvider::class,
        'smsban'         => SmsBanProvider::class,
        'smsbartar'      => SmsBartarProvider::class,
        'smscall'        => SmsCallProvider::class,
        'smsclick'       => SmsClickProvider::class,
        'smshooshmand'   => SmsHooshmandProvider::class,
        'smsline'        => SmsLineProvider::class,
        'smsmelli'       => SmsMelliProvider::class,
        'smsservice'     => SmsServiceProvider::class,
        'smstoos'        => SmsToosProvider::class,
        'ssmss'          => SsmssProvider::class,
        'textsms'        => TextSmsProvider::class,

        // Iranian gateways missed in the first batch (recon TSV was incomplete).
        // Same caveats: Iran-network only, reverse-engineered from v7 PHP,
        // ship with `TESTED = false` until manually verified.
        'adspanel'       => AdsPanelProvider::class,
        'afe'            => AfeProvider::class,
        'arkapayamak'    => ArkaPayamakProvider::class,
        'avalpayam'      => AvalPayamProvider::class,
        'bandarsms'      => BandarSmsProvider::class,
        'bestit'         => BestitProvider::class,
        'candoosms'      => CandooSmsProvider::class,
        'chapargah'      => ChapargahProvider::class,
        'firstpayamak'   => FirstPayamakProvider::class,
        'ghasedak'       => GhasedakProvider::class,
        'imencms'        => ImenCmsProvider::class,
        'mydnspanel'     => MyDnsPanelProvider::class,
        'onlinepanel'    => OnlinePanelProvider::class,
        'paaz'           => PaazProvider::class,
        'parsasms'       => ParsaSmsProvider::class,
        'parsgreen'      => ParsGreenProvider::class,
        'persiansms'     => PersianSmsProvider::class,
        'rayansmspanel'  => RayanSmsPanelProvider::class,
        'sabanovin'      => SabanovinProvider::class,
        'sunwaysms'      => SunwaySmsProvider::class,
        'tsms'           => TsmsProvider::class,
    ];

    public function register(ServiceContainer $container): void
    {
        $container->register('gateway.registry', fn() => new GatewayRegistry());
        $container->register('gateway.email.wp', fn() => new WpMailGateway());
        $container->register('gateway.webhook', fn() => new HttpWebhookGateway());
        $container->register('gateway.telegram', fn($c) => new TelegramGateway(
            $c->get('telegram.bot_client'),
        ));
        $container->register('gateway.line', fn($c) => new LineGateway(
            $c->get('line.bot_client'),
        ));
        $container->register('gateway.test', fn() => new TestGateway());
        $container->register('template.engine', fn() => new MustacheEngine());

        $container->register('message.dispatcher', fn($c) => new MessageDispatcher(
            $c->get('gateway.registry'),
            $c->get('log.message'),
            $c->get('event.dispatcher'),
            $c->get('queue'),
        ));

        $container->register('messaging.keyword_matcher', function () {
            $settings = get_option('wsms_optout_settings', []);
            return new KeywordMatcher(
                $settings['custom_stop_keywords'] ?? [],
                $settings['custom_start_keywords'] ?? [],
            );
        });

        $container->register('messaging.optout_manager', fn($c) => new OptOutManager(
            $c->get('contact.repository'),
            $c->get('event.dispatcher'),
            $c->get('message.dispatcher'),
            $c->get('messaging.keyword_matcher'),
        ));

        $container->register('messaging.suppression_guard', fn($c) => new SuppressionGuard(
            $c->get('contact.repository'),
        ));

        $container->register('messaging.status_propagator', fn($c) => new StatusPropagator(
            $c->get('contact.repository'),
            $c->get('event.dispatcher'),
        ));

        $container->register('email.unsubscribe_token', fn() => new UnsubscribeTokenService());

        $container->register('email.header_composer', fn($c) => new EmailHeaderComposer(
            $c->get('email.unsubscribe_token'),
        ));

        $container->register('template.catalog_manager', fn($c) => new TemplateCatalogManager(
            $c->get('gateway.registry'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Lazy wire: OptOutManager is only resolved if a send failure looks like an opt-out
        $container->get('message.dispatcher')->setOptOutManagerResolver(
            fn() => $container->get('messaging.optout_manager'),
        );

        // Lazy wire: SuppressionGuard is only resolved when checking a send
        $container->get('message.dispatcher')->setSuppressionGuardResolver(
            fn() => $container->get('messaging.suppression_guard'),
        );

        $registry = $container->get('gateway.registry');

        // Eager: built-in gateways (always available, no external API deps)
        $registry->register($container->get('gateway.email.wp'));
        $registry->register($container->get('gateway.webhook'));

        // Deferred: email gateways with external API deps
        $registry->registerDeferred('mailtrap', MailtrapGateway::class);

        // Deferred: gateways with constructor dependencies
        $registry->registerDeferred('telegram', fn() => $container->get('gateway.telegram'));
        $registry->registerDeferred('line', fn() => $container->get('gateway.line'));

        // Deferred: all SMS/messaging providers (lazy — only instantiated when accessed)
        // Providers implementing SupportsTemplates get the catalog manager injected
        $templateProviders = ['twilio', 'kavenegar', 'razpayamak', 'farapayamak', 'payamakaria', 'farazsms', 'hostiran', 'smsir', 'payamresan', 'plivo', 'sinch', 'infobip', 'messagebird', 'smsapi', 'fast2sms', 'smsc', 'smsgatewayhub', 'smsgatewaycenter', 'seven', 'afilnet', 'tubelightcommunications', 'espay', 'melipayamak', 'ghasedak', 'telnyx'];

        foreach (self::PROVIDERS as $id => $class) {
            if (in_array($id, $templateProviders, true)) {
                $registry->registerDeferred($id, function () use ($container, $class) {
                    $provider = new $class();
                    $provider->setCatalogManager($container->get('template.catalog_manager'));
                    return $provider;
                });
            } else {
                $registry->registerDeferred($id, $class);
            }
        }

        // Test gateway: only in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $registry->register($container->get('gateway.test'));
        }

        try {
            do_action('wsms_register_gateways', $registry);
        } catch (\Throwable $e) {
            error_log('[WP-SMS] Gateway registration failed: ' . $e->getMessage());
        }
    }
}
