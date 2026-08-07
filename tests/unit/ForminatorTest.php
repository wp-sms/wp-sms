<?php

namespace {
    if (!class_exists('Forminator_CForm_Front_Action')) {
        class Forminator_CForm_Front_Action
        {
            public static $prepared_data = [];
        }
    }

    if (!class_exists('Forminator_API')) {
        class Forminator_API
        {
            public static function get_form_fields($formId)
            {
                return [];
            }
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

            // The once-per-request guard is static, so clear it between tests that reuse a form ID.
            $handled = new \ReflectionProperty(Forminator::class, 'handledForms');
            $handled->setAccessible(true);
            $handled->setValue(null, []);
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

        public function testFallsBackToPostDataWhenPreparedDataIsEmpty()
        {
            $_POST['phone-1'] = 'recipient-from-post';

            $forminator = new Forminator();
            $reflection = new ReflectionClass($forminator);
            $setData    = $reflection->getMethod('set_data');
            $setData->setAccessible(true);
            $setData->invoke($forminator);

            $data = $reflection->getProperty('data');
            $data->setAccessible(true);

            $this->assertSame('recipient-from-post', $data->getValue($forminator)['phone-1']);
        }

        public function testSubmissionForAConfiguredFormIsSentToTheChosenField()
        {
            $this->configureForm(123);

            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '123',
                'phone-1' => '+61412345678',
            ];

            $recipients = $this->captureRecipients(function () {
                (new Forminator())->handle_sms(123, ['success' => true]);
            });

            $this->assertSame([['+61412345678']], $recipients);
        }

        public function testNonAjaxSubmissionSendsThroughTheHandleSubmitEvent()
        {
            // A form with AJAX turned off reloads the page and fires after_handle_submit
            // instead of after_save_entry. WSMS listens to both, so the SMS still goes out.
            $this->configureForm(123);

            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '123',
                'phone-1' => '+61466620021',
            ];

            $forminator = new Forminator();
            $forminator->init();

            $recipients = $this->captureRecipients(function () use ($forminator) {
                do_action('forminator_form_after_handle_submit', 123, ['success' => true]);
            });

            $this->assertSame([['+61466620021']], $recipients);

            remove_all_actions('forminator_form_after_handle_submit');
            remove_all_actions('forminator_form_after_save_entry');
        }

        public function testAjaxAndNonAjaxEventsDoNotDoubleSend()
        {
            // The two events do not fire together today, but if a request ever raised both,
            // the recipient must still only be texted once.
            $this->configureForm(123);

            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '123',
                'phone-1' => '+61466620021',
            ];

            $forminator = new Forminator();

            $recipients = $this->captureRecipients(function () use ($forminator) {
                $forminator->handle_sms(123, ['success' => true]);
                $forminator->handle_sms(123, ['success' => true]);
            });

            $this->assertSame([['+61466620021']], $recipients);
        }

        public function testSubmissionForAFormWithoutSettingsSendsNothing()
        {
            // Settings are stored per form ID, so a site with several similar forms can have
            // SMS set up on one form while visitors submit another. Nothing should go out.
            $this->configureForm(123);

            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '456',
                'phone-1' => '+61412345678',
            ];

            $recipients = $this->captureRecipients(function () {
                (new Forminator())->handle_sms(456, ['success' => true]);
            });

            $this->assertSame([], $recipients);
        }

        public function testSubmissionRejectedByForminatorSendsNothing()
        {
            // Forminator fires the same event when it refuses a submission, and saves no entry.
            // Sending then would text a visitor who never got through the form.
            $this->configureForm(123);

            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '123',
                'phone-1' => '+61412345678',
            ];

            $recipients = $this->captureRecipients(function () {
                (new Forminator())->handle_sms(123, [
                    'success' => false,
                    'errors'  => [['checkbox-1' => 'Please select from the following options']],
                ]);
            });

            $this->assertSame([], $recipients);
        }

        public function testOlderForminatorResponsesStillSend()
        {
            // Responses we cannot read must not silently stop working sites from sending.
            $this->configureForm(123);

            Forminator_CForm_Front_Action::$prepared_data = [
                'form_id' => '123',
                'phone-1' => '+61412345678',
            ];

            $recipients = $this->captureRecipients(function () {
                (new Forminator())->handle_sms(123, null);
            });

            $this->assertSame([['+61412345678']], $recipients);
        }

        private function configureForm($formId)
        {
            $settings = get_option('wpsms_settings', []);

            $settings['forminator_notify_enable_field_form_' . $formId]   = '1';
            $settings['forminator_notify_receiver_field_form_' . $formId] = 'phone-1';
            $settings['forminator_notify_message_field_form_' . $formId]  = 'Thanks for contacting us.';

            update_option('wpsms_settings', $settings);
        }

        private function captureRecipients(callable $callback)
        {
            $recipients = [];

            $capture = function ($source, $to) use (&$recipients) {
                $recipients[] = $to;
                return $source;
            };

            add_filter('wp_sms_normalization_source', $capture, 10, 2);

            try {
                $callback();
            } finally {
                remove_filter('wp_sms_normalization_source', $capture, 10);
            }

            return $recipients;
        }
    }
}
