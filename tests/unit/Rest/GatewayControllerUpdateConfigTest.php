<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Rest\GatewayController;

/**
 * Regression: GatewayController::updateConfig used to call
 *   update_option('wsms_gateway_configs', $config, false)
 * with the raw PUT body, completely replacing the option. Because the React
 * gateways page sends partial PUTs (the auto-assign-defaults useEffect only
 * includes the gateway(s) that need an `is_default` flag updated), this
 * wiped every other gateway's stored credentials on every save — and the
 * resulting state churn drove the React page into a runaway PUT/GET loop.
 *
 * The fix: shallow-merge by gateway id so partial updates only touch the
 * gateways named in the request.
 */
class GatewayControllerUpdateConfigTest extends TestCase
{
    private GatewayController $controller;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_current_user_can'] = true;

        $this->controller = new GatewayController(
            new GatewayRegistry(),
            $this->createMock(MessageLoggerInterface::class),
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_current_user_can'],
        );
    }

    private function makeRequest(array $body): \WP_REST_Request
    {
        $request = new \WP_REST_Request('PUT', '/wsms/v1/gateways/config');
        $request->set_body(json_encode($body));
        return $request;
    }

    public function testPartialPutPreservesOtherGatewaysCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'vonage' => [
                'shared'   => ['api_key' => 'KEY-A', 'api_secret' => 'SECRET-A'],
                'channels' => ['sms' => ['from' => '+10000']],
            ],
            'twilio' => [
                'shared' => ['account_sid' => 'AC-XYZ', 'auth_token' => 'TOK-XYZ'],
            ],
        ];

        // Auto-assign-defaults sends a partial body containing only the
        // newly-mounted webhook gateway — vonage and twilio are not in it.
        $this->controller->updateConfig($this->makeRequest([
            'webhook' => [
                'shared'     => [],
                'channels'   => [],
                'is_default' => ['webhook' => true],
            ],
        ]));

        $stored = $GLOBALS['_test_options']['wsms_gateway_configs'];

        $this->assertSame(['KEY-A', 'SECRET-A'], [
            $stored['vonage']['shared']['api_key'],
            $stored['vonage']['shared']['api_secret'],
        ], 'Vonage credentials must survive a partial PUT for an unrelated gateway');
        $this->assertSame('+10000', $stored['vonage']['channels']['sms']['from']);
        $this->assertSame('AC-XYZ', $stored['twilio']['shared']['account_sid']);
        $this->assertSame(['webhook' => true], $stored['webhook']['is_default']);
    }

    public function testPartialPutReplacesGatewayEntryFully(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'vonage' => [
                'shared'     => ['api_key' => 'OLD-KEY', 'api_secret' => 'OLD-SECRET'],
                'channels'   => ['sms' => ['from' => '+10000']],
                'is_default' => ['sms' => true],
            ],
        ];

        // User edits Vonage's credentials in the gateway config panel and
        // saves — the React side sends the FULL gateway config, which must
        // replace vonage's entry rather than recursively merging into it
        // (otherwise stale keys could survive a clear).
        $this->controller->updateConfig($this->makeRequest([
            'vonage' => [
                'shared'     => ['api_key' => 'NEW-KEY', 'api_secret' => 'NEW-SECRET'],
                'channels'   => ['sms' => ['from' => '+20000']],
                'is_default' => ['sms' => true],
            ],
        ]));

        $stored = $GLOBALS['_test_options']['wsms_gateway_configs']['vonage'];

        $this->assertSame('NEW-KEY', $stored['shared']['api_key']);
        $this->assertSame('NEW-SECRET', $stored['shared']['api_secret']);
        $this->assertSame('+20000', $stored['channels']['sms']['from']);
        $this->assertSame(['sms' => true], $stored['is_default']);
    }

    public function testEmptyPutBodyIsNoop(): void
    {
        $original = [
            'vonage' => [
                'shared' => ['api_key' => 'KEEP', 'api_secret' => 'KEEP-SECRET'],
            ],
        ];
        $GLOBALS['_test_options']['wsms_gateway_configs'] = $original;

        $this->controller->updateConfig($this->makeRequest([]));

        // An empty array body must not wipe the option (the previous bug
        // would have replaced the entire option with [] here).
        $this->assertSame($original, $GLOBALS['_test_options']['wsms_gateway_configs']);
    }
}
