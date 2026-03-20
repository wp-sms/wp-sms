<?php

namespace WSms\Tests\Unit\Contact\Source;

use PHPUnit\Framework\TestCase;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Source\WordPressUsersSource;

class WordPressUsersSourceTest extends TestCase
{
    private ContactRepositoryInterface $contacts;
    private WordPressUsersSource $source;

    protected function setUp(): void
    {
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);
        $this->source = new WordPressUsersSource($this->contacts);

        // Reset test globals
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_userdata']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_userdata'], $GLOBALS['_test_get_users_result'], $GLOBALS['_test_user_query_total']);
    }

    public function test_type_and_metadata(): void
    {
        $this->assertSame('wordpress_users', $this->source->getType());
        $this->assertSame('WordPress Users', $this->source->getName());
        $this->assertSame('wordpress', $this->source->getIcon());
        $this->assertTrue($this->source->isAvailable());
    }

    public function test_default_field_mapping_includes_phone(): void
    {
        $mapping = $this->source->getDefaultFieldMapping();

        $this->assertSame('user_email', $mapping['email']);
        $this->assertSame('first_name', $mapping['first_name']);
        $this->assertSame('last_name', $mapping['last_name']);
        $this->assertSame('wsms_phone', $mapping['phone']);
    }

    public function test_sync_one_creates_new_contact(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'jane@test.com';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42] = [
            'first_name'  => 'Jane',
            'last_name'   => 'Doe',
            'wsms_phone'  => '+15551234567',
            'description' => '',
        ];

        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->method('findByPhone')->willReturn(null);
        $this->contacts->expects($this->once())->method('create')
            ->with($this->callback(function (array $data) {
                return $data['email'] === 'jane@test.com'
                    && $data['first_name'] === 'Jane'
                    && $data['last_name'] === 'Doe'
                    && $data['phone'] === '+15551234567'
                    && $data['wp_user_id'] === 42
                    && $data['source'] === 'wordpress_users';
            }), false)
            ->willReturn('CONTACT_1');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $result = $this->source->syncOne(42, $config);

        $this->assertSame('CONTACT_1', $result);
    }

    public function test_sync_one_updates_existing_contact(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'jane@test.com';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42] = [
            'first_name'  => 'Jane',
            'last_name'   => 'Doe',
            'wsms_phone'  => '+15551234567',
            'description' => '',
        ];

        $this->contacts->method('findByWpUser')
            ->willReturn(['id' => 'EXISTING_1', 'email' => 'old@test.com']);
        $this->contacts->expects($this->once())->method('update');
        $this->contacts->expects($this->never())->method('create');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $result = $this->source->syncOne(42, $config);

        $this->assertSame('EXISTING_1', $result);
    }

    public function test_sync_one_skips_user_not_in_configured_roles(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'admin@test.com';
        $user->roles = ['administrator'];
        $GLOBALS['_test_userdata'] = $user;

        $this->contacts->expects($this->never())->method('create');
        $this->contacts->expects($this->never())->method('update');

        $config = [
            'roles'         => ['subscriber', 'customer'],
            'field_mapping' => $this->source->getDefaultFieldMapping(),
        ];
        $result = $this->source->syncOne(42, $config);

        $this->assertNull($result);
    }

    public function test_sync_one_skips_nonexistent_user(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $this->contacts->expects($this->never())->method('create');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $result = $this->source->syncOne(999, $config);

        $this->assertNull($result);
    }

    public function test_sync_one_skips_user_without_email(): void
    {
        $user = new \WP_User(42);
        $user->user_email = '';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;

        $this->contacts->expects($this->never())->method('create');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $result = $this->source->syncOne(42, $config);

        $this->assertNull($result);
    }

    public function test_sync_one_with_no_phone_meta(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'jane@test.com';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42] = [
            'first_name'  => 'Jane',
            'last_name'   => 'Doe',
            'description' => '',
        ];

        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->expects($this->once())->method('create')
            ->with($this->callback(function (array $data) {
                return !isset($data['phone']);
            }), false)
            ->willReturn('C1');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $result = $this->source->syncOne(42, $config);

        $this->assertSame('C1', $result);
    }

    public function test_sync_one_suppresses_events_during_bulk(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'jane@test.com';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42] = ['first_name' => 'Jane', 'last_name' => '', 'wsms_phone' => '', 'description' => ''];

        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->expects($this->once())->method('create')
            ->with($this->anything(), true)
            ->willReturn('C1');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $this->source->syncOne(42, $config, suppressEvents: true);
    }

    public function test_sync_one_with_custom_field_mapping(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'jane@test.com';
        $user->display_name = 'Jane D.';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42] = ['first_name' => 'Jane', 'last_name' => '', 'wsms_phone' => '', 'description' => ''];

        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->expects($this->once())->method('create')
            ->with($this->callback(function (array $data) {
                return $data['email'] === 'jane@test.com'
                    && $data['first_name'] === 'Jane D.';
            }), false)
            ->willReturn('C1');

        $config = [
            'field_mapping' => [
                'email'      => 'user_email',
                'first_name' => 'display_name',
            ],
        ];
        $this->source->syncOne(42, $config);
    }

    public function test_handle_deletion_marks_unsubscribed(): void
    {
        $this->contacts->method('findByWpUser')
            ->with(42)
            ->willReturn(['id' => 'C1', 'status' => 'subscribed']);

        $this->contacts->expects($this->once())->method('update')
            ->with('C1', ['status' => 'unsubscribed']);

        $this->source->handleDeletion(42);
    }

    public function test_handle_deletion_ignores_unknown_user(): void
    {
        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->expects($this->never())->method('update');

        $this->source->handleDeletion(999);
    }

    public function test_get_batch_returns_user_ids(): void
    {
        $user1 = new \stdClass();
        $user1->ID = 1;
        $user2 = new \stdClass();
        $user2->ID = 5;
        $GLOBALS['_test_get_users_result'] = [$user1, $user2];

        $ids = $this->source->getBatch([], 100, null);

        $this->assertSame([1, 5], $ids);
    }

    public function test_count_available_uses_query(): void
    {
        $GLOBALS['_test_user_query_total'] = 42;

        $count = $this->source->countAvailable([]);

        $this->assertSame(42, $count);
    }

    public function test_available_fields_list(): void
    {
        $fields = $this->source->getAvailableFields();

        $this->assertArrayHasKey('user_email', $fields);
        $this->assertArrayHasKey('wsms_phone', $fields);
        $this->assertArrayHasKey('first_name', $fields);
        $this->assertArrayHasKey('display_name', $fields);
    }

    public function test_upsert_matches_by_email_when_no_wp_user_id(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'existing@test.com';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42] = ['first_name' => 'Jane', 'last_name' => '', 'wsms_phone' => '', 'description' => ''];

        // No match by wp_user_id, but match by email
        $this->contacts->method('findByWpUser')->willReturn(null);
        $this->contacts->method('findByEmail')
            ->with('existing@test.com')
            ->willReturn(['id' => 'CSV_CONTACT', 'email' => 'existing@test.com', 'source' => 'import']);

        $this->contacts->expects($this->once())->method('update')
            ->with('CSV_CONTACT', $this->callback(function (array $data) {
                return $data['source'] === 'wordpress_users' && $data['wp_user_id'] === 42;
            }));
        $this->contacts->expects($this->never())->method('create');

        $config = ['field_mapping' => $this->source->getDefaultFieldMapping()];
        $result = $this->source->syncOne(42, $config);

        $this->assertSame('CSV_CONTACT', $result);
    }
}
