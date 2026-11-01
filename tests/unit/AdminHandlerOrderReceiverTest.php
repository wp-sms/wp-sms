<?php

namespace unit;

use WP_SMS\Service\Assets\Handlers\AdminHandler;
use WP_UnitTestCase;

/**
 * The admin script may only carry an order id (and with it the customer's
 * phone number) on the WooCommerce order screen, for a user WooCommerce lets
 * edit that order.
 *
 * Before this, any logged-in user could open e.g. /wp-admin/profile.php?post=<order id>
 * and read that order's customer number from WP_Sms_Admin_Object.receiver.
 */
class AdminHandlerOrderReceiverTest extends WP_UnitTestCase
{
    /** @var \WC_Order */
    private $order;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        // The test site loads WooCommerce without running its installer, so its
        // roles and capabilities (shop_manager, manage_woocommerce) are missing.
        // create_roles() is safe to repeat: it only adds what is missing.
        \WC_Install::create_roles();

        $this->order = wc_create_order();
        $this->order->set_billing_phone('+15550100199');
        // Whichever mobile field the site uses, the order carries the number.
        $this->order->update_meta_data(\WP_SMS\Helper::getUserMobileFieldName(), '+15550100199');
        $this->order->save();
    }

    public function tearDown(): void
    {
        unset($_GET['post'], $_GET['id'], $_GET['page']);
        $GLOBALS['current_screen'] = null;

        parent::tearDown();
    }

    public function testAnotherAdminScreenNeverResolvesAnOrder()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        set_current_screen('profile');
        $_GET['post'] = (string) $this->order->get_id();

        $this->assertSame(0, $this->editableOrderId());
    }

    public function testContributorOnTheOrderScreenGetsNoOrder()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'contributor']));
        set_current_screen('shop_order');
        $_GET['post'] = (string) $this->order->get_id();

        $this->assertSame(0, $this->editableOrderId());
    }

    public function testSubscriberOnTheOrderScreenGetsNoOrder()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        set_current_screen('shop_order');
        $_GET['post'] = (string) $this->order->get_id();

        $this->assertSame(0, $this->editableOrderId());
    }

    public function testShopManagerOnTheOrderScreenGetsTheOrder()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'shop_manager']));
        set_current_screen('shop_order');
        $_GET['post'] = (string) $this->order->get_id();

        $this->assertSame($this->order->get_id(), $this->editableOrderId());
    }

    public function testAdministratorOnTheHposOrderScreenGetsTheOrder()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        set_current_screen('woocommerce_page_wc-orders');
        $_GET['page'] = 'wc-orders';
        $_GET['id']   = (string) $this->order->get_id();

        $this->assertSame($this->order->get_id(), $this->editableOrderId());
    }

    public function testAnIdThatIsNotAnOrderGetsNothing()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        set_current_screen('shop_order');
        $_GET['post'] = (string) self::factory()->post->create();

        $this->assertSame(0, $this->editableOrderId());
    }

    public function testTheScriptDataCarriesNoNumberOffTheOrderScreen()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'contributor']));
        set_current_screen('profile');
        $_GET['post'] = (string) $this->order->get_id();

        (new AdminHandler())->scripts();

        $data = (string) wp_scripts()->get_data('wpsms-admin', 'data');
        $this->assertStringContainsString('"receiver":""', $data);
        $this->assertStringNotContainsString('15550100199', $data);
    }

    /**
     * @return int
     */
    private function editableOrderId()
    {
        $method = new \ReflectionMethod(AdminHandler::class, 'getEditableOrderId');
        $method->setAccessible(true);

        return $method->invoke(new AdminHandler());
    }
}
