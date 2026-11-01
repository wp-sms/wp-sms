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

        // The test site loads WooCommerce without running its installer, so the
        // roles may lack WooCommerce's capabilities (WooCommerce 11 does not add
        // them from create_roles() here). Give the two order-managing roles the
        // capability WooCommerce checks before opening an order.
        if (!get_role('shop_manager')) {
            add_role('shop_manager', 'Shop manager', ['read' => true]);
        }
        foreach (['administrator', 'shop_manager'] as $role) {
            get_role($role)->add_cap('manage_woocommerce');
        }

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

        $this->assertSame($this->order->get_id(), $this->editableOrderId(), $this->why());
    }

    public function testAdministratorOnTheHposOrderScreenGetsTheOrder()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        set_current_screen('woocommerce_page_wc-orders');
        $_GET['page'] = 'wc-orders';
        $_GET['id']   = (string) $this->order->get_id();

        $this->assertSame($this->order->get_id(), $this->editableOrderId(), $this->why());
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
     * What the checks saw, printed when an expected order id comes back 0.
     *
     * @return string
     */
    private function why()
    {
        $id = $this->order->get_id();

        return sprintf(
            'screen=%s manage_woocommerce=%s edit_shop_order=%s order_found=%s wc=%s',
            get_current_screen() ? get_current_screen()->id : 'none',
            var_export(current_user_can('manage_woocommerce'), true),
            var_export(current_user_can('edit_shop_order', $id), true),
            var_export((bool) wc_get_order($id), true),
            defined('WC_VERSION') ? WC_VERSION : '?'
        );
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
