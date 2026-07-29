<?php

namespace {
    if (!class_exists('Forminator_CForm_Front_Action')) {
        class Forminator_CForm_Front_Action
        {
            public static $prepared_data = [];
        }
    }
}

namespace unit {

    use Forminator_CForm_Front_Action;
    use ReflectionClass;
    use WP_SMS\Services\Forminator\Forminator;
    use WP_UnitTestCase;

    class ForminatorTest extends WP_UnitTestCase
    {
        private $originalPost;

        public function setUp(): void
        {
            parent::setUp();

            $this->originalPost = $_POST;
            $_POST = ['form_id' => '123'];
            Forminator_CForm_Front_Action::$prepared_data = [];
        }

        public function tearDown(): void
        {
            $_POST = $this->originalPost;
            Forminator_CForm_Front_Action::$prepared_data = [];

            parent::tearDown();
        }

        public function testUsesForminatorPreparedDataForFieldRecipient()
        {
            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '123',
                'phone-1' => '+61412345678',
            ];

            $forminator = new Forminator();
            $reflection = new ReflectionClass($forminator);
            $setData    = $reflection->getMethod('set_data');
            $setData->setAccessible(true);
            $setData->invoke($forminator);

            $data = $reflection->getProperty('data');
            $data->setAccessible(true);

            $this->assertSame('+61412345678', $data->getValue($forminator)['phone-1']);
        }
    }
}
