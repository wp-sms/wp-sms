<?php

namespace WSms\Tests\Unit\Contact;

use PHPUnit\Framework\TestCase;
use WSms\Contact\ContactRepository;
use WSms\Database\Connection;

class ContactRepositoryTest extends TestCase
{
    private object $wpdb;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->wpdb = $this->createWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->connection = new Connection($this->wpdb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function test_create_normalizes_email_to_lowercase(): void
    {
        $this->wpdb->insertResult = true;

        $repo = new ContactRepository($this->connection);
        $repo->create(['email' => 'John@Example.COM', 'phone' => '+1 (234) 567-8900']);

        $insert = $this->wpdb->inserts[0];
        $this->assertSame('john@example.com', $insert['data']['email']);
    }

    public function test_create_normalizes_phone(): void
    {
        $this->wpdb->insertResult = true;

        $repo = new ContactRepository($this->connection);
        $repo->create(['phone' => '+1 (234) 567-8900']);

        $insert = $this->wpdb->inserts[0];
        $this->assertSame('+12345678900', $insert['data']['phone']);
    }

    public function test_update_normalizes_email_and_phone(): void
    {
        $this->wpdb->updateResult = true;

        $repo = new ContactRepository($this->connection);
        $repo->update('C1', ['email' => 'Test@Email.COM', 'phone' => '+1 (555) 123-4567']);

        $update = $this->wpdb->updates[0];
        $this->assertSame('test@email.com', $update['data']['email']);
        $this->assertSame('+15551234567', $update['data']['phone']);
    }

    public function test_find_decodes_custom_fields_json(): void
    {
        $this->wpdb->getRowResult = [
            'id' => 'C1', 'email' => 'test@test.com', 'phone' => null,
            'custom_fields' => '{"company":"Acme","role":"admin"}',
            'first_name' => 'Test', 'last_name' => 'User',
            'status' => 'subscribed', 'source' => 'manual',
            'wp_user_id' => null, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ];

        $repo = new ContactRepository($this->connection);
        $contact = $repo->find('C1');

        $this->assertIsArray($contact['custom_fields']);
        $this->assertSame('Acme', $contact['custom_fields']['company']);
    }

    public function test_findAll_decodes_custom_fields_for_all_rows(): void
    {
        $this->wpdb->getResultsResult = [
            ['id' => 'C1', 'custom_fields' => '{"key":"val1"}', 'email' => 'a@b.com', 'status' => 'subscribed'],
            ['id' => 'C2', 'custom_fields' => '{"key":"val2"}', 'email' => 'c@d.com', 'status' => 'subscribed'],
        ];

        $repo = new ContactRepository($this->connection);
        $contacts = $repo->findAll();

        $this->assertIsArray($contacts[0]['custom_fields']);
        $this->assertSame('val1', $contacts[0]['custom_fields']['key']);
        $this->assertSame('val2', $contacts[1]['custom_fields']['key']);
    }

    public function test_findByEmail_uses_case_insensitive_query(): void
    {
        $this->wpdb->getRowResult = null;

        $repo = new ContactRepository($this->connection);
        $repo->findByEmail('Test@Example.com');

        $this->assertStringContainsString('test@example.com', $this->wpdb->lastQuery);
    }

    public function test_findByPhone_normalizes_before_query(): void
    {
        $this->wpdb->getRowResult = null;

        $repo = new ContactRepository($this->connection);
        $repo->findByPhone('+1 (234) 567-8900');

        $this->assertStringContainsString('+12345678900', $this->wpdb->lastQuery);
    }

    public function test_delete_removes_tags_then_contact(): void
    {
        $this->wpdb->deleteResult = true;

        $repo = new ContactRepository($this->connection);
        $repo->delete('C1');

        $this->assertCount(2, $this->wpdb->deletes);
        $this->assertSame('test_wsms_contact_tag', $this->wpdb->deletes[0]['table']);
        $this->assertSame('test_wsms_contacts', $this->wpdb->deletes[1]['table']);
    }

    public function test_bulkDelete_removes_tags_and_contacts(): void
    {
        $this->wpdb->queryResult = 3;

        $repo = new ContactRepository($this->connection);
        $count = $repo->bulkDelete(['C1', 'C2', 'C3']);

        $this->assertSame(3, $count);
        $this->assertCount(2, $this->wpdb->queries); // DELETE from contact_tag + DELETE from contacts
    }

    public function test_bulkDelete_with_empty_ids_returns_zero(): void
    {
        $repo = new ContactRepository($this->connection);
        $this->assertSame(0, $repo->bulkDelete([]));
    }

    public function test_bulkUpdateStatus_updates_matching_contacts(): void
    {
        $this->wpdb->queryResult = 2;

        $repo = new ContactRepository($this->connection);
        $count = $repo->bulkUpdateStatus(['C1', 'C2'], 'bounced');

        $this->assertSame(2, $count);
        $this->assertStringContainsString('bounced', $this->wpdb->queries[0]);
    }

    public function test_findByIds_returns_contacts_with_decoded_fields(): void
    {
        $this->wpdb->getResultsResult = [
            ['id' => 'C1', 'custom_fields' => '{"k":"v"}', 'email' => 'a@b.com'],
        ];

        $repo = new ContactRepository($this->connection);
        $contacts = $repo->findByIds(['C1']);

        $this->assertCount(1, $contacts);
        $this->assertIsArray($contacts[0]['custom_fields']);
    }

    public function test_findByIds_with_empty_array(): void
    {
        $repo = new ContactRepository($this->connection);
        $this->assertSame([], $repo->findByIds([]));
    }

    public function test_findByTag_returns_contacts_for_tag(): void
    {
        $this->wpdb->getResultsResult = [
            ['id' => 'C1', 'custom_fields' => null, 'email' => 'a@b.com'],
        ];

        $repo = new ContactRepository($this->connection);
        $contacts = $repo->findByTag('T1', 50, 0);

        $this->assertCount(1, $contacts);
        $this->assertStringContainsString('INNER JOIN', $this->wpdb->lastQuery);
    }

    public function test_countByTag_returns_count(): void
    {
        $this->wpdb->getVarResult = '7';

        $repo = new ContactRepository($this->connection);
        $this->assertSame(7, $repo->countByTag('T1'));
    }

    /** @dataProvider normalizePhoneProvider */
    public function test_normalizePhone(string $input, string $expected): void
    {
        $this->assertSame($expected, ContactRepository::normalizePhone($input));
    }

    public static function normalizePhoneProvider(): array
    {
        return [
            'strips spaces and parens' => ['+1 (234) 567-8900', '+12345678900'],
            'strips dots'              => ['+1.234.567.8900', '+12345678900'],
            'already clean'            => ['+12345678900', '+12345678900'],
            'leading plus preserved'   => ['+44 7911 123456', '+447911123456'],
        ];
    }

    public function test_normalizePhone_throws_for_non_e164(): void
    {
        $this->expectException(\WSms\Exception\ValidationException::class);
        ContactRepository::normalizePhone('12345678900');
    }

    private function createWpdb(): \wpdb
    {
        return new class extends \wpdb {
            public string $prefix = 'test_';
            public array $inserts = [];
            public array $deletes = [];
            public array $updates = [];
            public array $queries = [];
            public $insertResult = true;
            public $deleteResult = true;
            public $updateResult = true;
            public $queryResult = 0;
            public $getRowResult = null;
            public $getResultsResult = [];
            public $getVarResult = null;
            public string $lastQuery = '';

            public function insert($table, $data, $format = null) {
                $this->inserts[] = ['table' => $table, 'data' => $data];
                return $this->insertResult;
            }

            public function delete($table, $where, $format = null) {
                $this->deletes[] = ['table' => $table, 'where' => $where];
                return $this->deleteResult;
            }

            public function update($table, $data, $where, $format = null, $whereFormat = null) {
                $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];
                return $this->updateResult;
            }

            public function replace($table, $data, $format = null) {
                $this->inserts[] = ['table' => $table, 'data' => $data];
                return true;
            }

            public function query($query) {
                $this->queries[] = $query;
                return $this->queryResult;
            }

            public function get_row($query, $output = null, $y = 0) {
                $this->lastQuery = $query;
                return $this->getRowResult;
            }

            public function get_results($query, $output = null) {
                $this->lastQuery = $query;
                return $this->getResultsResult;
            }

            public function get_var($query, $x = 0, $y = 0) {
                $this->lastQuery = $query;
                return $this->getVarResult;
            }

            public function prepare($query, ...$args) {
                $this->lastQuery = $query;
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function esc_like($text) {
                return addcslashes($text, '_%\\');
            }
        };
    }
}
