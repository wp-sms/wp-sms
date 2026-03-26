<?php

namespace WSms\Integration\Mailtrap;

use WSms\Integration\Marketing\RateLimiter;

defined('ABSPATH') || exit;

class MailtrapApiClient
{
    private const BASE_URL = 'https://mailtrap.io';
    public const GATEWAY_CONFIG_OPTION = 'wsms_gateway_configs';
    public const GATEWAY_ID = 'mailtrap';

    private RateLimiter $rateLimiter;

    public function __construct(
        private readonly string $apiToken,
        private readonly string $accountId = '',
    ) {
        $this->rateLimiter = new RateLimiter(3, 10);
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public static function fromGatewayConfig(): ?self
    {
        $configs = get_option(self::GATEWAY_CONFIG_OPTION, []);
        $config = $configs[self::GATEWAY_ID] ?? [];
        $apiToken = $config['shared']['api_token'] ?? '';
        $accountId = $config['shared']['account_id'] ?? '';

        if ($apiToken && $accountId) {
            return new self($apiToken, $accountId);
        }

        return null;
    }

    // --- Account ---

    public function getAccounts(): array
    {
        return $this->request('GET', '/api/accounts');
    }

    // --- Suppressions ---

    public function getSuppressions(array $params = []): array
    {
        $query = http_build_query($params);
        $path = "/api/accounts/{$this->accountId}/suppressions" . ($query ? "?{$query}" : '');

        return $this->request('GET', $path);
    }

    public function deleteSuppression(string $id): void
    {
        $this->request('DELETE', "/api/accounts/{$this->accountId}/suppressions/{$id}");
    }

    // --- Contacts ---

    public function getContacts(int $listId, array $params = []): array
    {
        if ($listId > 0) {
            $params['list_id'] = $listId;
        }

        $query = http_build_query($params);
        $path = "/api/accounts/{$this->accountId}/contacts" . ($query ? "?{$query}" : '');

        return $this->request('GET', $path);
    }

    public function findContactByEmail(int $listId, string $email): ?array
    {
        try {
            $contacts = $this->getContacts($listId, ['email' => $email]);
            $items = $contacts['data'] ?? $contacts;

            if (!is_array($items)) {
                return null;
            }

            foreach ($items as $contact) {
                if (($contact['email'] ?? '') === $email) {
                    return $contact;
                }
            }

            return null;
        } catch (\RuntimeException) {
            return null;
        }
    }

    public function upsertContact(int $listId, string $email, array $fields = []): array
    {
        try {
            return $this->createContact($listId, $email, $fields);
        } catch (\RuntimeException $e) {
            if (!self::isConflictError($e)) {
                throw $e;
            }

            $existing = $this->findContactByEmail($listId, $email);
            if ($existing && !empty($fields)) {
                $this->updateContact((int) $existing['id'], $fields);
            }

            return $existing ?? [];
        }
    }

    public function createContact(int $listId, string $email, array $fields = []): array
    {
        $body = [
            'email'   => $email,
            'list_id' => $listId,
        ];

        if (!empty($fields)) {
            $body['fields'] = $fields;
        }

        return $this->request('POST', "/api/accounts/{$this->accountId}/contacts", $body);
    }

    public function updateContact(int $contactId, array $fields = []): array
    {
        $body = [];

        if (!empty($fields)) {
            $body['fields'] = $fields;
        }

        return $this->request('PATCH', "/api/accounts/{$this->accountId}/contacts/{$contactId}", $body);
    }

    public function deleteContact(int $contactId): void
    {
        $this->request('DELETE', "/api/accounts/{$this->accountId}/contacts/{$contactId}");
    }

    // --- Lists ---

    public function getLists(): array
    {
        return $this->request('GET', "/api/accounts/{$this->accountId}/contacts/lists");
    }

    // --- Error detection ---

    public static function isNotFoundError(\RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), '(404)');
    }

    public static function isConflictError(\RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), '(409)') || str_contains($e->getMessage(), '(422)');
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $this->rateLimiter->acquire();

        $url = self::BASE_URL . $path;
        $args = [
            'method'  => $method,
            'headers' => [
                'Api-Token'    => $this->apiToken,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException(
                'Mailtrap API error: ' . $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 204) {
            return [];
        }

        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true) ?: [];

        if ($code === 429) {
            $retryAfter = (int) (wp_remote_retrieve_header($response, 'Retry-After') ?: 5);
            $this->rateLimiter->handleRetryAfter($retryAfter);
            throw new \RuntimeException(
                'Mailtrap rate limit exceeded. Retry after ' . $retryAfter . 's',
            );
        }

        if ($code >= 400) {
            $error = $data['error'] ?? $data['message'] ?? "HTTP {$code}";

            if ($code === 422 && !empty($data['errors'])) {
                $fieldErrors = array_map(
                    fn($key, $messages) => $key . ': ' . (is_array($messages) ? implode(', ', $messages) : $messages),
                    array_keys($data['errors']),
                    array_values($data['errors']),
                );
                $error .= ' [' . implode('; ', $fieldErrors) . ']';
            }

            throw new \RuntimeException("Mailtrap API error ({$code}): {$error}");
        }

        return $data;
    }
}
