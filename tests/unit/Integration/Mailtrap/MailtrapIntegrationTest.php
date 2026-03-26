<?php

namespace WSms\Tests\Unit\Integration\Mailtrap;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Contracts\IntegrationCapability;
use WSms\Integration\Mailtrap\MailtrapIntegration;

class MailtrapIntegrationTest extends TestCase
{
    private MailtrapIntegration $integration;

    protected function setUp(): void
    {
        $this->integration = new MailtrapIntegration();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_request'],
            $GLOBALS['_test_wp_remote_request_last_url'],
            $GLOBALS['_test_wp_remote_request_last_args'],
            $GLOBALS['_test_options'],
        );
    }

    private function mockResponse(int $code = 200, array $body = [], array $headers = []): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => $code],
            'body'     => wp_json_encode($body),
            'headers'  => $headers,
        ];
    }

    private function setGatewayConfig(array $shared): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mailtrap' => [
                'shared' => $shared,
            ],
        ];
    }

    // --- Identity ---

    public function testGetId(): void
    {
        $this->assertSame('mailtrap_marketing', $this->integration->getId());
    }

    public function testGetName(): void
    {
        $this->assertSame('Mailtrap', $this->integration->getName());
    }

    public function testGetCategory(): void
    {
        $this->assertSame('email_marketing', $this->integration->getCategory());
    }

    public function testGetAuthType(): void
    {
        $this->assertSame('gateway', $this->integration->getAuthType());
    }

    public function testGetAuthSchemaIsEmpty(): void
    {
        $this->assertSame([], $this->integration->getAuthSchema());
    }

    // --- connect ---

    public function testConnectDiscoversAccountIdFromGateway(): void
    {
        $this->setGatewayConfig(['api_token' => 'valid-token']);
        $this->mockResponse(200, [['id' => 99, 'name' => 'Test Account']]);

        $result = $this->integration->connect([]);

        $this->assertSame('99', $result['account_id']);
        $this->assertArrayNotHasKey('api_token', $result);

        // Verify account_id was saved to gateway config
        $gwConfig = $GLOBALS['_test_options']['wsms_gateway_configs']['mailtrap'] ?? [];
        $this->assertSame('99', $gwConfig['shared']['account_id']);
    }

    public function testConnectThrowsWhenGatewayNotConfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configure the Mailtrap gateway first');

        $this->integration->connect([]);
    }

    public function testConnectThrowsOnNoAccounts(): void
    {
        $this->setGatewayConfig(['api_token' => 'valid-token']);
        $this->mockResponse(200, []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No accounts found');

        $this->integration->connect([]);
    }

    public function testConnectThrowsOn403WithHelpfulMessage(): void
    {
        $this->setGatewayConfig(['api_token' => 'send-only-token']);
        $this->mockResponse(403, ['message' => 'Forbidden']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('promotional API');

        $this->integration->connect([]);
    }

    public function testConnectThrowsOnInvalidToken(): void
    {
        $this->setGatewayConfig(['api_token' => 'bad-token']);
        $this->mockResponse(401, ['error' => 'Unauthorized']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid API token');

        $this->integration->connect([]);
    }

    // --- isConnected ---

    public function testIsConnectedWhenBothTokenAndAccountPresent(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);

        $this->assertTrue($this->integration->isConnected());
    }

    public function testIsNotConnectedWhenMissingAccountId(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok']);

        $this->assertFalse($this->integration->isConnected());
    }

    public function testIsNotConnectedWhenMissingToken(): void
    {
        $this->setGatewayConfig(['account_id' => '123']);

        $this->assertFalse($this->integration->isConnected());
    }

    public function testIsNotConnectedWhenNoConfig(): void
    {
        $this->assertFalse($this->integration->isConnected());
    }

    // --- getCapabilities ---

    public function testCapabilitiesStructure(): void
    {
        $caps = $this->integration->getCapabilities();

        $indexed = [];
        foreach ($caps as $cap) {
            $indexed[$cap['id']] = $cap;
        }

        $this->assertTrue($indexed[IntegrationCapability::CONTACT_SYNC]['supported']);
        $this->assertTrue($indexed[IntegrationCapability::LIST_MANAGEMENT]['supported']);
        $this->assertTrue($indexed[IntegrationCapability::SUPPRESSION_SYNC]['supported']);
        $this->assertTrue($indexed[IntegrationCapability::EMAIL_GATEWAY]['supported']);
        $this->assertSame('mailtrap', $indexed[IntegrationCapability::EMAIL_GATEWAY]['gateway_id']);
        $this->assertFalse($indexed[IntegrationCapability::TAGS]['supported']);
        $this->assertFalse($indexed[IntegrationCapability::AUTOMATIONS]['supported']);
        $this->assertFalse($indexed[IntegrationCapability::ENGAGEMENT_DATA]['supported']);
    }

    // --- pushContact ---

    public function testPushContactSuccessOnCreate(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, ['id' => 55, 'email' => 'test@example.com']);

        $result = $this->integration->pushContact(
            ['email' => 'test@example.com', 'first_name' => 'Jane'],
            ['default_list_id' => 42],
        );

        $this->assertTrue($result->success);
        $this->assertSame('55', $result->providerContactId);
    }

    public function testPushContactFailsWhenNotConnected(): void
    {
        $result = $this->integration->pushContact(
            ['email' => 'test@example.com'],
            ['default_list_id' => 42],
        );

        $this->assertFalse($result->success);
        $this->assertSame('Not connected', $result->error);
    }

    public function testPushContactFailsWhenNoList(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);

        $result = $this->integration->pushContact(
            ['email' => 'test@example.com'],
            [],
        );

        $this->assertFalse($result->success);
        $this->assertSame('No default list configured', $result->error);
    }

    public function testPushContactSkipsWhenNoEmail(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);

        $result = $this->integration->pushContact(
            ['first_name' => 'Jane'],
            ['default_list_id' => 42],
        );

        $this->assertTrue($result->success);
        $this->assertSame('skipped', $result->status);
    }

    // --- updateContactStatus ---

    public function testUpdateContactStatusSkips(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);

        $result = $this->integration->updateContactStatus('test@example.com', 'bounced', ['default_list_id' => 42]);

        $this->assertTrue($result->success);
        $this->assertSame('skipped', $result->status);
    }

    // --- getLists ---

    public function testGetListsFormatsResponse(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, [
            ['id' => 1, 'name' => 'Newsletter', 'contacts_count' => 150],
            ['id' => 2, 'name' => 'Updates', 'contacts_count' => 50],
        ]);

        $lists = $this->integration->getLists([]);

        $this->assertCount(2, $lists);
        $this->assertSame(1, $lists[0]['id']);
        $this->assertSame('Newsletter', $lists[0]['name']);
        $this->assertSame(150, $lists[0]['contact_count']);
    }

    public function testGetListsReturnsEmptyWhenNotConnected(): void
    {
        $this->assertSame([], $this->integration->getLists([]));
    }

    // --- pollSuppressions ---

    public function testPollSuppressionsMapsBounceReason(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, [
            'data' => [
                ['id' => '1', 'email' => 'bounced@example.com', 'type' => 'bounce', 'created_at' => '2025-01-01T00:00:00Z'],
                ['id' => '2', 'email' => 'spam@example.com', 'type' => 'complaint', 'created_at' => '2025-01-02T00:00:00Z'],
                ['id' => '3', 'email' => 'unsub@example.com', 'type' => 'unsubscribe', 'created_at' => '2025-01-03T00:00:00Z'],
            ],
        ]);

        $result = $this->integration->pollSuppressions([]);

        $this->assertCount(3, $result['events']);
        $this->assertSame('bounced', $result['events'][0]['status']);
        $this->assertSame('complained', $result['events'][1]['status']);
        $this->assertSame('unsubscribed', $result['events'][2]['status']);
        $this->assertSame('3', $result['cursor']);
    }

    public function testPollSuppressionsReturnsEmptyOnError(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(500, []);

        $result = $this->integration->pollSuppressions([], 'prev-cursor');

        $this->assertEmpty($result['events']);
        $this->assertSame('prev-cursor', $result['cursor']);
    }

    public function testPollSuppressionsPassesCursor(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, ['data' => []]);

        $this->integration->pollSuppressions([], 'cursor-abc');

        $url = $GLOBALS['_test_wp_remote_request_last_url'] ?? '';
        $this->assertStringContainsString('cursor=cursor-abc', $url);
    }

    public function testPollSuppressionsReturnsEmptyWhenNotConnected(): void
    {
        $result = $this->integration->pollSuppressions([]);

        $this->assertEmpty($result['events']);
        $this->assertNull($result['cursor']);
    }

    // --- getFieldMapping ---

    public function testGetFieldMapping(): void
    {
        $mapping = $this->integration->getFieldMapping();

        $this->assertSame('email', $mapping['email']);
        $this->assertSame('first_name', $mapping['first_name']);
        $this->assertSame('last_name', $mapping['last_name']);
    }

    // --- getActions ---

    public function testGetActionsReturnsTwoActions(): void
    {
        $actions = $this->integration->getActions();

        $this->assertCount(2, $actions);

        $ids = array_map(fn($a) => $a->getId(), $actions);
        $this->assertContains('mailtrap_add_to_list', $ids);
        $this->assertContains('mailtrap_remove_from_list', $ids);
    }

    // --- getDescription ---

    public function testDescriptionMentionsGateway(): void
    {
        $desc = $this->integration->getDescription();

        $this->assertStringContainsString('gateway', $desc);
    }
}
