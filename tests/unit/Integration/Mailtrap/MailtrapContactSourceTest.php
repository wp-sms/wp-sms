<?php

namespace WSms\Tests\Unit\Integration\Mailtrap;

use PHPUnit\Framework\TestCase;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Mailtrap\MailtrapContactSource;

class MailtrapContactSourceTest extends TestCase
{
    private MailtrapContactSource $source;
    private ContactRepositoryInterface $contacts;

    protected function setUp(): void
    {
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);
        $this->source = new MailtrapContactSource($this->contacts);
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

    public function testGetType(): void
    {
        $this->assertSame('mailtrap', $this->source->getType());
    }

    public function testGetName(): void
    {
        $this->assertSame('Mailtrap', $this->source->getName());
    }

    // --- isAvailable ---

    public function testIsAvailableWhenConnected(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);

        $this->assertTrue($this->source->isAvailable());
    }

    public function testIsNotAvailableWhenNoConfig(): void
    {
        $this->assertFalse($this->source->isAvailable());
    }

    // --- syncOne ---

    public function testSyncOneUpsertsContact(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, [
            'data' => [
                ['email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe', 'fields' => []],
            ],
        ]);

        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->method('findByPhone')->willReturn(null);
        $this->contacts->expects($this->once())
            ->method('create')
            ->willReturn('contact-uuid');

        $result = $this->source->syncOne('jane@example.com', ['list_id' => 42]);

        $this->assertSame('contact-uuid', $result);
    }

    public function testSyncOneReturnsNullOnNotFound(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, ['data' => []]);

        $result = $this->source->syncOne('missing@example.com', ['list_id' => 42]);

        $this->assertNull($result);
    }

    public function testSyncOneReturnsNullWhenNoClient(): void
    {
        $result = $this->source->syncOne('test@example.com', ['list_id' => 42]);

        $this->assertNull($result);
    }

    // --- getBatch ---

    public function testGetBatchReturnsEmails(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, [
            'data' => [
                ['email' => 'a@example.com'],
                ['email' => 'b@example.com'],
            ],
        ]);

        $result = $this->source->getBatch(['list_id' => 42], 100, null);

        $this->assertSame(['a@example.com', 'b@example.com'], $result);
    }

    public function testGetBatchReturnsEmptyWhenNoClient(): void
    {
        $result = $this->source->getBatch(['list_id' => 42], 100, null);

        $this->assertSame([], $result);
    }

    // --- countAvailable ---

    public function testCountAvailableReturnsListCount(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, [
            ['id' => 42, 'name' => 'Newsletter', 'contacts_count' => 250],
        ]);

        $count = $this->source->countAvailable(['list_id' => 42]);

        $this->assertSame(250, $count);
    }

    public function testCountAvailableReturnsZeroForUnknownList(): void
    {
        $this->setGatewayConfig(['api_token' => 'tok', 'account_id' => '123']);
        $this->mockResponse(200, [
            ['id' => 99, 'name' => 'Other', 'contacts_count' => 100],
        ]);

        $count = $this->source->countAvailable(['list_id' => 42]);

        $this->assertSame(0, $count);
    }

    public function testCountAvailableReturnsZeroWhenNoClient(): void
    {
        $count = $this->source->countAvailable(['list_id' => 42]);

        $this->assertSame(0, $count);
    }

    // --- getAvailableFields ---

    public function testGetAvailableFields(): void
    {
        $fields = $this->source->getAvailableFields();

        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('first_name', $fields);
        $this->assertArrayHasKey('last_name', $fields);
    }
}
