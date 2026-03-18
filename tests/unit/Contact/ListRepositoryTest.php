<?php

namespace WSms\Tests\Unit\Contact;

use PHPUnit\Framework\TestCase;
use WSms\Contact\ListRepository;

class ListRepositoryTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = $this->createWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function test_create_inserts_list_with_json_conditions(): void
    {
        $this->wpdb->insertResult = true;
        $conditions = ['match' => 'all', 'conditions' => [['type' => 'attribute', 'field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']]];

        $repo = new ListRepository();
        $id = $repo->create([
            'name'       => 'Active Subscribers',
            'type'       => 'dynamic',
            'conditions' => $conditions,
        ]);

        $this->assertNotEmpty($id);
        $insert = $this->wpdb->inserts[0];
        $this->assertSame('Active Subscribers', $insert['data']['name']);
        $this->assertSame('dynamic', $insert['data']['type']);
        $this->assertSame(json_encode($conditions), $insert['data']['conditions']);
    }

    public function test_create_static_list_with_tag_id(): void
    {
        $this->wpdb->insertResult = true;

        $repo = new ListRepository();
        $repo->create([
            'name'   => 'VIP List',
            'type'   => 'static',
            'tag_id' => 'TAG123',
        ]);

        $insert = $this->wpdb->inserts[0];
        $this->assertSame('static', $insert['data']['type']);
        $this->assertSame('TAG123', $insert['data']['tag_id']);
        $this->assertNull($insert['data']['conditions']);
    }

    public function test_find_decodes_json_conditions(): void
    {
        $conditions = ['match' => 'all', 'conditions' => []];
        $this->wpdb->getRowResult = [
            'id' => 'L1', 'name' => 'Test', 'type' => 'dynamic',
            'conditions' => json_encode($conditions), 'tag_id' => null,
            'contact_count' => '5', 'description' => null,
            'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ];

        $repo = new ListRepository();
        $list = $repo->find('L1');

        $this->assertIsArray($list['conditions']);
        $this->assertSame('all', $list['conditions']['match']);
    }

    public function test_findAll_with_type_filter(): void
    {
        $this->wpdb->getResultsResult = [
            ['id' => 'L1', 'name' => 'A', 'type' => 'dynamic', 'conditions' => null, 'tag_id' => null, 'contact_count' => '0', 'description' => null, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        ];

        $repo = new ListRepository();
        $lists = $repo->findAll('dynamic');

        $this->assertCount(1, $lists);
        $this->assertStringContainsString('dynamic', $this->wpdb->lastQuery);
    }

    public function test_findAll_without_type_filter(): void
    {
        $this->wpdb->getResultsResult = [];

        $repo = new ListRepository();
        $repo->findAll();

        $this->assertStringNotContainsString('WHERE', $this->wpdb->lastQuery);
    }

    public function test_delete_removes_list(): void
    {
        $this->wpdb->deleteResult = true;

        $repo = new ListRepository();
        $result = $repo->delete('L1');

        $this->assertTrue($result);
        $this->assertSame('test_wsms_lists', $this->wpdb->deletes[0]['table']);
    }

    public function test_updateContactCount(): void
    {
        $this->wpdb->updateResult = true;

        $repo = new ListRepository();
        $repo->updateContactCount('L1', 42);

        $this->assertSame(42, $this->wpdb->updates[0]['data']['contact_count']);
    }

    public function test_update_encodes_conditions_to_json(): void
    {
        $this->wpdb->updateResult = true;
        $conditions = ['match' => 'any', 'conditions' => []];

        $repo = new ListRepository();
        $repo->update('L1', ['conditions' => $conditions]);

        $this->assertSame(json_encode($conditions), $this->wpdb->updates[0]['data']['conditions']);
    }

    private function createWpdb(): object
    {
        return new class {
            public string $prefix = 'test_';
            public array $inserts = [];
            public array $deletes = [];
            public array $updates = [];
            public $insertResult = true;
            public $deleteResult = true;
            public $updateResult = true;
            public $getRowResult = null;
            public $getResultsResult = [];
            public string $lastQuery = '';

            public function insert(string $table, array $data): bool {
                $this->inserts[] = ['table' => $table, 'data' => $data];
                return $this->insertResult;
            }

            public function delete(string $table, array $where): bool {
                $this->deletes[] = ['table' => $table, 'where' => $where];
                return $this->deleteResult;
            }

            public function update(string $table, array $data, array $where): bool {
                $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];
                return $this->updateResult;
            }

            public function get_row($query, $output = 'OBJECT') {
                $this->lastQuery = $query;
                return $this->getRowResult;
            }

            public function get_results($query, $output = 'OBJECT') {
                $this->lastQuery = $query;
                return $this->getResultsResult;
            }

            public function prepare(string $query, ...$args): string {
                $this->lastQuery = $query;
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }
        };
    }
}
