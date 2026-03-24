<?php

namespace WSms\Tests\Unit\Contact;

use PHPUnit\Framework\TestCase;
use WSms\Contact\TagRepository;
use WSms\Database\Connection;

class TagRepositoryTest extends TestCase
{
    private TagRepository $repo;
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

    public function test_create_inserts_tag_with_generated_slug(): void
    {
        $this->wpdb->getRowResult = null; // no existing slug
        $this->wpdb->insertResult = true;

        $repo = new TagRepository($this->connection);
        $id = $repo->create(['name' => 'VIP Clients', 'color' => '#ef4444']);

        $this->assertNotEmpty($id);
        $this->assertCount(1, $this->wpdb->inserts);

        $insert = $this->wpdb->inserts[0];
        $this->assertSame('test_wsms_tags', $insert['table']);
        $this->assertSame('VIP Clients', $insert['data']['name']);
        $this->assertSame('vip-clients', $insert['data']['slug']);
        $this->assertSame('#ef4444', $insert['data']['color']);
    }

    public function test_create_uses_default_color(): void
    {
        $this->wpdb->getRowResult = null;
        $this->wpdb->insertResult = true;

        $repo = new TagRepository($this->connection);
        $repo->create(['name' => 'Test']);

        $this->assertSame('#3b82f6', $this->wpdb->inserts[0]['data']['color']);
    }

    public function test_delete_cascades_to_contact_tag(): void
    {
        $this->wpdb->deleteResult = true;

        $repo = new TagRepository($this->connection);
        $repo->delete('TAG123');

        $this->assertCount(2, $this->wpdb->deletes);
        $this->assertSame('test_wsms_contact_tag', $this->wpdb->deletes[0]['table']);
        $this->assertSame(['tag_id' => 'TAG123'], $this->wpdb->deletes[0]['where']);
        $this->assertSame('test_wsms_tags', $this->wpdb->deletes[1]['table']);
    }

    public function test_find_returns_tag_by_id(): void
    {
        $this->wpdb->getRowResult = ['id' => 'T1', 'name' => 'VIP', 'slug' => 'vip', 'color' => '#ef4444'];

        $repo = new TagRepository($this->connection);
        $result = $repo->find('T1');

        $this->assertSame('VIP', $result['name']);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $this->wpdb->getRowResult = null;

        $repo = new TagRepository($this->connection);
        $this->assertNull($repo->find('NONEXISTENT'));
    }

    public function test_findAll_returns_all_tags(): void
    {
        $this->wpdb->getResultsResult = [
            ['id' => 'T1', 'name' => 'A', 'slug' => 'a', 'color' => '#ef4444'],
            ['id' => 'T2', 'name' => 'B', 'slug' => 'b', 'color' => '#22c55e'],
        ];

        $repo = new TagRepository($this->connection);
        $tags = $repo->findAll();

        $this->assertCount(2, $tags);
    }

    public function test_update_changes_name_and_regenerates_slug(): void
    {
        $this->wpdb->getRowResult = null; // No existing slug conflict
        $this->wpdb->updateResult = true;

        $repo = new TagRepository($this->connection);
        $repo->update('T1', ['name' => 'New Name']);

        $this->assertCount(1, $this->wpdb->updates);
        $this->assertSame('New Name', $this->wpdb->updates[0]['data']['name']);
        $this->assertSame('new-name', $this->wpdb->updates[0]['data']['slug']);
    }

    public function test_getContactCount_returns_count(): void
    {
        $this->wpdb->getVarResult = '5';

        $repo = new TagRepository($this->connection);
        $this->assertSame(5, $repo->getContactCount('T1'));
    }

    private function createWpdb(): \wpdb
    {
        return new class extends \wpdb {
            public string $prefix = 'test_';
            public array $inserts = [];
            public array $deletes = [];
            public array $updates = [];
            public $insertResult = true;
            public $deleteResult = true;
            public $updateResult = true;
            public $getRowResult = null;
            public $getResultsResult = [];
            public $getVarResult = null;

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

            public function get_row($query, $output = null, $y = 0) {
                return $this->getRowResult;
            }

            public function get_results($query, $output = null) {
                return $this->getResultsResult;
            }

            public function get_var($query, $x = 0, $y = 0) {
                return $this->getVarResult;
            }

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }
        };
    }
}
