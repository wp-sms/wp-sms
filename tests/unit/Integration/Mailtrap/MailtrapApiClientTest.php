<?php

namespace WSms\Tests\Unit\Integration\Mailtrap;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Mailtrap\MailtrapApiClient;

class MailtrapApiClientTest extends TestCase
{
    private MailtrapApiClient $client;

    protected function setUp(): void
    {
        $this->client = new MailtrapApiClient('test-token', '12345');
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_request'],
            $GLOBALS['_test_wp_remote_request_last_url'],
            $GLOBALS['_test_wp_remote_request_last_args'],
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

    private function lastUrl(): string
    {
        return $GLOBALS['_test_wp_remote_request_last_url'] ?? '';
    }

    private function lastArgs(): array
    {
        return $GLOBALS['_test_wp_remote_request_last_args'] ?? [];
    }

    private function lastBody(): array
    {
        $args = $this->lastArgs();

        return json_decode($args['body'] ?? '{}', true);
    }

    // --- Auth header ---

    public function testRequestIncludesApiTokenHeader(): void
    {
        $this->mockResponse(200, [['id' => 1]]);

        $this->client->getAccounts();

        $headers = $this->lastArgs()['headers'];
        $this->assertSame('test-token', $headers['Api-Token']);
    }

    public function testRequestIncludesJsonHeaders(): void
    {
        $this->mockResponse(200, [['id' => 1]]);

        $this->client->getAccounts();

        $headers = $this->lastArgs()['headers'];
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
    }

    // --- getAccounts ---

    public function testGetAccountsUrl(): void
    {
        $this->mockResponse(200, [['id' => 1, 'name' => 'My Account']]);

        $result = $this->client->getAccounts();

        $this->assertSame('https://mailtrap.io/api/accounts', $this->lastUrl());
        $this->assertSame('GET', $this->lastArgs()['method']);
        $this->assertSame(1, $result[0]['id']);
    }

    // --- getSuppressions ---

    public function testGetSuppressionsUrl(): void
    {
        $this->mockResponse(200, ['data' => []]);

        $this->client->getSuppressions();

        $this->assertSame('https://mailtrap.io/api/accounts/12345/suppressions', $this->lastUrl());
        $this->assertSame('GET', $this->lastArgs()['method']);
    }

    public function testGetSuppressionsWithCursorParam(): void
    {
        $this->mockResponse(200, ['data' => []]);

        $this->client->getSuppressions(['cursor' => 'abc123']);

        $this->assertStringContainsString('cursor=abc123', $this->lastUrl());
    }

    // --- deleteSuppression ---

    public function testDeleteSuppressionUrl(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 204],
            'body'     => '',
            'headers'  => [],
        ];

        $this->client->deleteSuppression('supp-99');

        $this->assertSame('https://mailtrap.io/api/accounts/12345/suppressions/supp-99', $this->lastUrl());
        $this->assertSame('DELETE', $this->lastArgs()['method']);
    }

    // --- getContacts ---

    public function testGetContactsUrlWithListId(): void
    {
        $this->mockResponse(200, ['data' => []]);

        $this->client->getContacts(42);

        $this->assertStringContainsString('/api/accounts/12345/contacts', $this->lastUrl());
        $this->assertStringContainsString('list_id=42', $this->lastUrl());
        $this->assertSame('GET', $this->lastArgs()['method']);
    }

    public function testGetContactsWithEmailFilter(): void
    {
        $this->mockResponse(200, ['data' => []]);

        $this->client->getContacts(42, ['email' => 'test@example.com']);

        $url = $this->lastUrl();
        $this->assertStringContainsString('email=test%40example.com', $url);
        $this->assertStringContainsString('list_id=42', $url);
    }

    // --- createContact ---

    public function testCreateContactUsesPost(): void
    {
        $this->mockResponse(200, ['id' => 100, 'email' => 'new@example.com']);

        $this->client->createContact(42, 'new@example.com', ['first_name' => 'Jane']);

        $this->assertStringContainsString('/api/accounts/12345/contacts', $this->lastUrl());
        $this->assertSame('POST', $this->lastArgs()['method']);

        $body = $this->lastBody();
        $this->assertSame('new@example.com', $body['email']);
        $this->assertSame(42, $body['list_id']);
        $this->assertSame('Jane', $body['fields']['first_name']);
    }

    public function testCreateContactOmitsFieldsWhenEmpty(): void
    {
        $this->mockResponse(200, ['id' => 100]);

        $this->client->createContact(42, 'new@example.com');

        $body = $this->lastBody();
        $this->assertArrayNotHasKey('fields', $body);
    }

    // --- updateContact ---

    public function testUpdateContactUsesPatch(): void
    {
        $this->mockResponse(200, ['id' => 100]);

        $this->client->updateContact(100, ['first_name' => 'Updated']);

        $this->assertStringContainsString('/api/accounts/12345/contacts/100', $this->lastUrl());
        $this->assertSame('PATCH', $this->lastArgs()['method']);

        $body = $this->lastBody();
        $this->assertSame('Updated', $body['fields']['first_name']);
    }

    // --- deleteContact ---

    public function testDeleteContactUsesDelete(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 204],
            'body'     => '',
            'headers'  => [],
        ];

        $this->client->deleteContact(100);

        $this->assertStringContainsString('/api/accounts/12345/contacts/100', $this->lastUrl());
        $this->assertSame('DELETE', $this->lastArgs()['method']);
    }

    // --- getLists ---

    public function testGetListsUrl(): void
    {
        $this->mockResponse(200, [['id' => 1, 'name' => 'Newsletter']]);

        $result = $this->client->getLists();

        $this->assertSame('https://mailtrap.io/api/accounts/12345/contacts/lists', $this->lastUrl());
        $this->assertSame('GET', $this->lastArgs()['method']);
        $this->assertSame('Newsletter', $result[0]['name']);
    }

    // --- Account ID interpolation ---

    public function testAccountIdInterpolatedIntoUrls(): void
    {
        $client = new MailtrapApiClient('tok', '99999');
        $this->mockResponse(200, []);

        $client->getLists();

        $this->assertStringContainsString('/api/accounts/99999/', $this->lastUrl());
    }

    // --- 204 handling ---

    public function testHandles204NoContent(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 204],
            'body'     => '',
            'headers'  => [],
        ];

        $this->client->deleteContact(1);
        // No exception means success
        $this->assertTrue(true);
    }

    // --- Error handling ---

    public function testErrorParsingUsesErrorField(): void
    {
        $this->mockResponse(400, ['error' => 'Bad request: missing email']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bad request: missing email');

        $this->client->getLists();
    }

    public function testErrorParsingFallsBackToMessage(): void
    {
        $this->mockResponse(403, ['message' => 'Forbidden']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');

        $this->client->getLists();
    }

    public function testErrorParsingFallsBackToHttpCode(): void
    {
        $this->mockResponse(500, []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $this->client->getLists();
    }

    public function testValidation422IncludesFieldErrors(): void
    {
        $this->mockResponse(422, [
            'error'  => 'Validation failed',
            'errors' => [
                'email' => ['must be a valid email'],
                'list_id' => ['is required'],
            ],
        ]);

        try {
            $this->client->createContact(42, 'bad');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Validation failed', $e->getMessage());
            $this->assertStringContainsString('email: must be a valid email', $e->getMessage());
            $this->assertStringContainsString('list_id: is required', $e->getMessage());
        }
    }

    // --- Rate limiting ---

    public function testRateLimitUsesRetryAfterHeader(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 429],
            'body'     => wp_json_encode(['error' => 'Too Many Requests']),
            'headers'  => ['retry-after' => '10'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Retry after 10s');

        $this->client->getLists();
    }

    public function testRateLimitDefaultsTo5Seconds(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 429],
            'body'     => wp_json_encode(['error' => 'Too Many Requests']),
            'headers'  => [],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Retry after 5s');

        $this->client->getLists();
    }

    // --- WP_Error handling ---

    public function testWpErrorThrowsRuntimeException(): void
    {
        $GLOBALS['_test_wp_remote_request'] = new \WP_Error('http_request_failed', 'Connection timed out');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection timed out');

        $this->client->getLists();
    }

    // --- isNotFoundError ---

    public function testIsNotFoundErrorMatchesMessage(): void
    {
        $this->assertTrue(MailtrapApiClient::isNotFoundError(new \RuntimeException('Mailtrap API error (404): Not found')));
        $this->assertFalse(MailtrapApiClient::isNotFoundError(new \RuntimeException('Mailtrap API error (400): Bad request')));
    }

    // --- isConflictError ---

    public function testIsConflictErrorMatches409And422(): void
    {
        $this->assertTrue(MailtrapApiClient::isConflictError(new \RuntimeException('Mailtrap API error (409): Conflict')));
        $this->assertTrue(MailtrapApiClient::isConflictError(new \RuntimeException('Mailtrap API error (422): Already exists')));
        $this->assertFalse(MailtrapApiClient::isConflictError(new \RuntimeException('Mailtrap API error (400): Bad request')));
    }

    // --- getAccountId ---

    public function testGetAccountIdReturnsStoredValue(): void
    {
        $this->assertSame('12345', $this->client->getAccountId());
    }

    // --- findContactByEmail ---

    public function testFindContactByEmailReturnsMatchingContact(): void
    {
        $this->mockResponse(200, [
            'data' => [
                ['id' => 55, 'email' => 'test@example.com', 'first_name' => 'Jane'],
            ],
        ]);

        $result = $this->client->findContactByEmail(42, 'test@example.com');

        $this->assertSame(55, $result['id']);
        $this->assertSame('test@example.com', $result['email']);
    }

    public function testFindContactByEmailReturnsNullWhenNotFound(): void
    {
        $this->mockResponse(200, ['data' => []]);

        $result = $this->client->findContactByEmail(42, 'missing@example.com');

        $this->assertNull($result);
    }

    public function testFindContactByEmailReturnsNullOnError(): void
    {
        $this->mockResponse(500, []);

        $result = $this->client->findContactByEmail(42, 'test@example.com');

        $this->assertNull($result);
    }

    // --- upsertContact ---

    public function testUpsertContactCreatesOnSuccess(): void
    {
        $this->mockResponse(200, ['id' => 100, 'email' => 'new@example.com']);

        $result = $this->client->upsertContact(42, 'new@example.com', ['first_name' => 'Jane']);

        $this->assertSame(100, $result['id']);
        $this->assertSame('POST', $this->lastArgs()['method']);
    }

    public function testUpsertContactFallsBackToUpdateOnConflict(): void
    {
        $callCount = 0;
        $GLOBALS['_test_wp_remote_request'] = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // createContact → 422 conflict
                return [
                    'response' => ['code' => 422],
                    'body'     => wp_json_encode(['error' => 'Already exists']),
                    'headers'  => [],
                ];
            }
            if ($callCount === 2) {
                // findContactByEmail → getContacts
                return [
                    'response' => ['code' => 200],
                    'body'     => wp_json_encode(['data' => [['id' => 55, 'email' => 'existing@example.com']]]),
                    'headers'  => [],
                ];
            }
            // updateContact
            return [
                'response' => ['code' => 200],
                'body'     => wp_json_encode(['id' => 55]),
                'headers'  => [],
            ];
        };

        $result = $this->client->upsertContact(42, 'existing@example.com', ['first_name' => 'Updated']);

        $this->assertSame(55, $result['id']);
        $this->assertSame(3, $callCount);
    }

    // --- fromGatewayConfig ---

    public function testFromGatewayConfigReturnsClientWhenConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mailtrap' => [
                'shared' => ['api_token' => 'tok', 'account_id' => '123'],
            ],
        ];

        $client = MailtrapApiClient::fromGatewayConfig();

        $this->assertInstanceOf(MailtrapApiClient::class, $client);
        $this->assertSame('123', $client->getAccountId());

        unset($GLOBALS['_test_options']);
    }

    public function testFromGatewayConfigReturnsNullWhenMissing(): void
    {
        $client = MailtrapApiClient::fromGatewayConfig();

        $this->assertNull($client);
    }
}
