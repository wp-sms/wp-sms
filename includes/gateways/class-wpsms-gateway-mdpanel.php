<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once __DIR__ . '/class-wpsms-gateway-ippanel.php';

/**
 * MdPanel runs on IPPanel. IPPanel shut down its old SOAP service on
 * 2026-11-07, so this gateway now sends through the IPPanel Edge API with an
 * API key, the same as the "ippanel" gateway. The slug stays "mdpanel" so
 * existing sites keep their selection; a saved username and password are
 * ignored and the site is asked for an API key instead.
 */
class mdpanel extends ippanel
{
    public $tariff = "https://ippanel.com/";

    public function __construct()
    {
        parent::__construct();

        $this->gatewayFields['from']['desc']    = __('A line from your MdPanel account, for example +983000505 or 3000505.', 'wp-sms');
        $this->gatewayFields['has_key']['desc'] = __('MdPanel now works with an API key: the old username and password no longer work. Make a key in your MdPanel panel under Developers > Access Keys and paste it here.', 'wp-sms');

        $this->help = '
<div dir="rtl">
  <p><strong>MdPanel:</strong> نام کاربری و رمز عبور قبلی دیگر کار نمی کند، چون IPPanel وب سرویس قدیمی را خاموش کرده است.</p>
  <p><strong>کلید API:</strong> در پنل MdPanel از بخش توسعه دهندگان ← کلیدهای دسترسی یک کلید API بسازید و آن را در فیلد کلید API وارد کنید.</p>
  <p><strong>شماره ارسال کننده:</strong> یکی از خطوط حساب خود را وارد کنید، مثلا <code>+983000505</code> یا <code>3000505</code>.</p>
  <p><strong>ارسال با الگو (پترن):</strong> پیام را با متغیرهای افزونه بنویسید و بعد از <code>|</code> کد پترن را بگذارید، مثلا <code>کد ورود شما: %code%|jhaskdab45s6f4sfw</code>. متغیرهای پترن در پنل باید هم نام باشند، مثل <code>%code%</code>.</p>
</div>';
    }

    protected function missingKey()
    {
        return new \WP_Error('account-credit', esc_html__('Enter your MdPanel API key. The old username and password no longer work: make a key in your panel under Developers > Access Keys.', 'wp-sms'));
    }
}
