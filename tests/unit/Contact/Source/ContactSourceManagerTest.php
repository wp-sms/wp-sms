<?php

namespace WSms\Tests\Unit\Contact\Source;

use PHPUnit\Framework\TestCase;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Source\ContactSourceManager;
use WSms\Contact\Source\ContactSourceRegistry;
use WSms\Contact\Source\Contracts\ContactSourceInterface;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\SyncContactSourceBatchJob;

class ContactSourceManagerTest extends TestCase
{
    private ContactSourceRegistry $registry;
    private ContactRepositoryInterface $contacts;
    private QueueInterface $queue;
    private ContactSourceManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];

        $this->registry = new ContactSourceRegistry();
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);
        $this->queue = $this->createMock(QueueInterface::class);
        $this->manager = new ContactSourceManager($this->registry, $this->contacts, $this->queue);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    public function test_get_all_returns_empty_by_default(): void
    {
        $this->assertSame([], $this->manager->getAll());
    }

    public function test_save_and_get(): void
    {
        $data = [
            'status' => 'connected',
            'config' => ['auto_sync' => true],
            'stats'  => [],
        ];

        $this->manager->save('wordpress_users', $data);

        $this->assertSame($data, $this->manager->get('wordpress_users'));
    }

    public function test_get_returns_null_for_unknown_type(): void
    {
        $this->assertNull($this->manager->get('nonexistent'));
    }

    public function test_delete_removes_source(): void
    {
        $this->manager->save('wordpress_users', ['status' => 'connected']);
        $this->manager->delete('wordpress_users');

        $this->assertNull($this->manager->get('wordpress_users'));
    }

    public function test_update_status(): void
    {
        $this->manager->save('wordpress_users', ['status' => 'connected', 'config' => []]);
        $this->manager->updateStatus('wordpress_users', 'syncing');

        $this->assertSame('syncing', $this->manager->get('wordpress_users')['status']);
    }

    public function test_update_stats_merges(): void
    {
        $this->manager->save('wordpress_users', [
            'status' => 'connected',
            'stats'  => ['total_synced' => 0, 'last_synced_at' => null],
        ]);

        $this->manager->updateStats('wordpress_users', ['total_synced' => 50]);

        $stats = $this->manager->get('wordpress_users')['stats'];
        $this->assertSame(50, $stats['total_synced']);
        $this->assertNull($stats['last_synced_at']); // Preserved
    }

    public function test_is_syncing(): void
    {
        $this->manager->save('wordpress_users', ['status' => 'connected']);
        $this->assertFalse($this->manager->isSyncing('wordpress_users'));

        $this->manager->updateStatus('wordpress_users', 'syncing');
        $this->assertTrue($this->manager->isSyncing('wordpress_users'));
    }

    public function test_start_sync_dispatches_batch_job(): void
    {
        $this->manager->save('wordpress_users', [
            'status' => 'connected',
            'config' => ['auto_sync' => true],
            'stats'  => [],
        ]);

        $this->queue->expects($this->once())->method('dispatch')
            ->with($this->callback(function ($job) {
                return $job instanceof SyncContactSourceBatchJob
                    && $job->getPayload()['source_type'] === 'wordpress_users';
            }));

        $this->manager->startSync('wordpress_users');

        $this->assertSame('syncing', $this->manager->get('wordpress_users')['status']);
    }

    public function test_start_sync_skips_if_already_syncing(): void
    {
        $this->manager->save('wordpress_users', ['status' => 'syncing', 'config' => [], 'stats' => []]);

        $this->queue->expects($this->never())->method('dispatch');

        $this->manager->startSync('wordpress_users');
    }

    public function test_process_batch_syncs_ids_and_queues_next(): void
    {
        $source = $this->createMock(ContactSourceInterface::class);
        $source->method('getType')->willReturn('wordpress_users');
        $source->method('getBatch')->willReturn([1, 2, 3]);
        $source->expects($this->exactly(3))->method('syncOne')->willReturn('C1');
        $this->registry->register($source);

        $this->manager->save('wordpress_users', [
            'status' => 'syncing',
            'config' => ['auto_sync' => true],
            'stats'  => ['sync_progress' => ['synced' => 0]],
        ]);

        // getBatch returns exactly batchSize items → more to come
        $this->queue->expects($this->once())->method('dispatch');

        $this->manager->processBatch('wordpress_users', 3, null);
    }

    public function test_process_batch_only_counts_successful_syncs(): void
    {
        $source = $this->createMock(ContactSourceInterface::class);
        $source->method('getType')->willReturn('wordpress_users');
        $source->method('getBatch')->willReturn([10, 11, 12]);
        // syncOne returns null for skipped, string for success
        $source->method('syncOne')->willReturnOnConsecutiveCalls('C1', null, 'C2');
        $this->registry->register($source);

        $this->manager->save('wordpress_users', [
            'status' => 'syncing',
            'config' => [],
            'stats'  => ['sync_progress' => ['synced' => 0]],
        ]);

        $this->queue->expects($this->never())->method('dispatch');

        $this->manager->processBatch('wordpress_users', 100, 9);

        $stored = $this->manager->get('wordpress_users');
        $this->assertSame('connected', $stored['status']);
        $this->assertSame(2, $stored['stats']['total_synced']); // Only 2 succeeded
    }

    public function test_process_batch_completes_on_partial_batch(): void
    {
        $source = $this->createMock(ContactSourceInterface::class);
        $source->method('getType')->willReturn('wordpress_users');
        $source->method('getBatch')->willReturn([10, 11]); // Less than batchSize
        $source->expects($this->exactly(2))->method('syncOne')->willReturn('C1');
        $this->registry->register($source);

        $this->manager->save('wordpress_users', [
            'status' => 'syncing',
            'config' => [],
            'stats'  => ['sync_progress' => ['synced' => 5]],
        ]);

        // No next batch dispatched
        $this->queue->expects($this->never())->method('dispatch');

        $this->manager->processBatch('wordpress_users', 100, 9);

        $stored = $this->manager->get('wordpress_users');
        $this->assertSame('connected', $stored['status']);
        $this->assertSame(7, $stored['stats']['total_synced']); // 5 + 2
    }

    public function test_process_batch_exits_if_not_syncing(): void
    {
        $source = $this->createMock(ContactSourceInterface::class);
        $source->method('getType')->willReturn('wordpress_users');
        $source->expects($this->never())->method('getBatch');
        $this->registry->register($source);

        $this->manager->save('wordpress_users', ['status' => 'connected', 'config' => []]);

        $this->manager->processBatch('wordpress_users', 100, null);
    }

    public function test_get_contact_count_delegates_to_repository(): void
    {
        $this->contacts->method('count')
            ->with(['source' => 'wordpress_users'])
            ->willReturn(42);

        $this->assertSame(42, $this->manager->getContactCount('wordpress_users'));
    }
}
