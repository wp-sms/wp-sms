<?php

namespace WSms\Integration\EmailOctopus;

use WSms\Integration\Marketing\RateLimiter;

defined('ABSPATH') || exit;

class EmailOctopusApiClient
{
    private const BASE_URL = 'https://api.emailoctopus.com';

    private RateLimiter $rateLimiter;

    public function __construct(
        private readonly string $apiKey,
    ) {
        $this->rateLimiter = new RateLimiter(10, 100);
    }

    public function validateKey(): array
    {
        return $this->request('GET', '/account');
    }

    public function getLists(): array
    {
        return $this->paginateAll('/lists');
    }

    public function getList(string $listId): array
    {
        return $this->request('GET', "/lists/{$listId}");
    }

    public function getContact(string $listId, string $email): ?array
    {
        $contactId = self::contactId($email);

        try {
            return $this->request('GET', "/lists/{$listId}/contacts/{$contactId}");
        } catch (\RuntimeException $e) {
            if (self::isNotFoundError($e)) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create or update a contact by email (upsert).
     * Tags are string arrays: ["tag1", "tag2"].
     */
    public function upsertContact(string $listId, string $email, array $fields = [], array $tags = [], ?string $status = null): array
    {
        $body = [
            'email_address' => $email,
            'fields'        => (object) $fields,
        ];

        if (!empty($tags)) {
            $body['tags'] = array_values($tags);
        }

        if ($status !== null) {
            $body['status'] = $status;
        }

        return $this->request('PUT', "/lists/{$listId}/contacts", $body);
    }

    /**
     * Update a specific existing contact.
     * Tags are boolean objects: {"tagName": true/false}.
     */
    public function updateContact(string $listId, string $email, array $fields = [], array $tags = [], ?string $status = null): array
    {
        $contactId = self::contactId($email);
        $body = [];

        if (!empty($fields)) {
            $body['fields'] = (object) $fields;
        }

        if (!empty($tags)) {
            $body['tags'] = (object) $tags;
        }

        if ($status !== null) {
            $body['status'] = $status;
        }

        return $this->request('PUT', "/lists/{$listId}/contacts/{$contactId}", $body);
    }

    public function batchContacts(string $listId, array $members): array
    {
        return $this->request('PUT', "/lists/{$listId}/contacts/batch", [
            'members' => $members,
        ]);
    }

    public function deleteContact(string $listId, string $email): void
    {
        $contactId = self::contactId($email);
        $this->request('DELETE', "/lists/{$listId}/contacts/{$contactId}");
    }

    public function getContacts(string $listId, array $params = []): array
    {
        $query = http_build_query($params);
        $path = "/lists/{$listId}/contacts" . ($query ? "?{$query}" : '');

        return $this->request('GET', $path);
    }

    public function paginateContacts(string $listId, array $params = []): array
    {
        $query = http_build_query($params);
        $path = "/lists/{$listId}/contacts" . ($query ? "?{$query}" : '');

        return $this->paginateAll($path);
    }

    public function queueIntoAutomation(string $automationId, string $email): array
    {
        return $this->request('POST', "/automations/{$automationId}/queue", [
            'contact_id' => self::contactId($email),
        ]);
    }

    public static function contactId(string $email): string
    {
        return md5(strtolower(trim($email)));
    }

    public static function isNotFoundError(\RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), '(404)');
    }

    private function paginateAll(string $path): array
    {
        $all = [];
        $startingAfter = null;
        $separator = str_contains($path, '?') ? '&' : '?';

        do {
            $url = $path . $separator . 'limit=100';
            if ($startingAfter !== null) {
                $url .= '&starting_after=' . urlencode($startingAfter);
            }

            $response = $this->request('GET', $url);
            $items = $response['data'] ?? [];
            array_push($all, ...$items);

            $startingAfter = $response['paging']['next']['starting_after'] ?? null;
        } while ($startingAfter !== null && !empty($items));

        return $all;
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $this->rateLimiter->acquire();

        $url = self::BASE_URL . $path;
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException(
                'EmailOctopus API error: ' . $response->get_error_message(),
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
                'EmailOctopus rate limit exceeded. Retry after ' . $retryAfter . 's',
            );
        }

        if ($code >= 400) {
            $error = $data['detail'] ?? $data['title'] ?? "HTTP {$code}";

            if ($code === 422 && !empty($data['errors'])) {
                $fieldErrors = array_map(
                    fn(array $e) => ($e['pointer'] ?? '') . ': ' . ($e['detail'] ?? ''),
                    $data['errors'],
                );
                $error .= ' [' . implode('; ', $fieldErrors) . ']';
            }

            throw new \RuntimeException("EmailOctopus API error ({$code}): {$error}");
        }

        return $data;
    }
}
