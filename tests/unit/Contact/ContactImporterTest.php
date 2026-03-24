<?php

namespace WSms\Tests\Unit\Contact;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WSms\Contact\ContactImporter;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Database\Connection;

class ContactImporterTest extends TestCase
{
    private ContactRepositoryInterface&MockObject $contacts;
    private ContactImporter $importer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);

        // Mock wpdb for transactions
        $GLOBALS['wpdb'] = new class extends \wpdb {
            public array $queries = [];
            public function query($query) { $this->queries[] = $query; return true; }
        };

        $this->importer = new ContactImporter($this->contacts, new Connection($GLOBALS['wpdb']));

        $this->tempDir = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function test_import_creates_new_contacts(): void
    {
        $csv = $this->writeCsv([
            ['email', 'phone', 'first_name', 'last_name'],
            ['john@example.com', '+1234567890', 'John', 'Doe'],
            ['jane@example.com', '+0987654321', 'Jane', 'Smith'],
        ]);

        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->expects($this->exactly(2))->method('create');

        $result = $this->importer->importFromCsv($csv);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['updated']);
    }

    public function test_import_skips_rows_without_email_or_phone(): void
    {
        $csv = $this->writeCsv([
            ['email', 'phone', 'first_name'],
            ['', '', 'Nameless'],
        ]);

        $this->contacts->expects($this->never())->method('create');

        $result = $this->importer->importFromCsv($csv);

        $this->assertSame(1, $result['skipped']);
    }

    public function test_import_duplicate_handling_update(): void
    {
        $csv = $this->writeCsv([
            ['email', 'first_name'],
            ['existing@test.com', 'Updated'],
        ]);

        $existing = ['id' => 'C1', 'email' => 'existing@test.com', 'first_name' => 'Old'];
        $this->contacts->method('findByEmail')->willReturn($existing);
        $this->contacts->expects($this->once())->method('update')->with('C1', $this->anything());
        $this->contacts->expects($this->never())->method('create');

        $result = $this->importer->importFromCsv($csv, [], ['duplicate_handling' => 'update']);

        $this->assertSame(1, $result['updated']);
    }

    public function test_import_duplicate_handling_skip(): void
    {
        $csv = $this->writeCsv([
            ['email', 'first_name'],
            ['existing@test.com', 'Updated'],
        ]);

        $this->contacts->method('findByEmail')->willReturn(['id' => 'C1', 'email' => 'existing@test.com']);
        $this->contacts->expects($this->never())->method('update');
        $this->contacts->expects($this->never())->method('create');

        $result = $this->importer->importFromCsv($csv, [], ['duplicate_handling' => 'skip']);

        $this->assertSame(1, $result['skipped']);
    }

    public function test_import_duplicate_handling_update_if_empty(): void
    {
        $csv = $this->writeCsv([
            ['email', 'first_name', 'last_name'],
            ['existing@test.com', 'NewFirst', 'NewLast'],
        ]);

        $existing = ['id' => 'C1', 'email' => 'existing@test.com', 'first_name' => 'Already Set', 'last_name' => ''];
        $this->contacts->method('findByEmail')->willReturn($existing);

        // Should only update last_name (which is empty on existing)
        $this->contacts->expects($this->once())->method('update')
            ->with('C1', $this->callback(function ($data) {
                return isset($data['last_name']) && $data['last_name'] === 'NewLast'
                    && !isset($data['first_name']); // first_name already set, should NOT be updated
            }));

        $result = $this->importer->importFromCsv($csv, [], ['duplicate_handling' => 'update_if_empty']);

        $this->assertSame(1, $result['updated']);
    }

    public function test_import_match_by_phone(): void
    {
        $csv = $this->writeCsv([
            ['email', 'phone', 'first_name'],
            ['', '+1234567890', 'PhoneOnly'],
        ]);

        $this->contacts->method('findByPhone')->willReturn(['id' => 'C1', 'phone' => '+1234567890', 'first_name' => 'Old']);
        $this->contacts->expects($this->once())->method('update');

        $result = $this->importer->importFromCsv($csv, [], ['match_field' => 'phone']);

        $this->assertSame(1, $result['updated']);
    }

    public function test_import_match_by_email_or_phone_tries_email_first(): void
    {
        $csv = $this->writeCsv([
            ['email', 'phone'],
            ['found@test.com', '+1234567890'],
        ]);

        $this->contacts->method('findByEmail')->willReturn(['id' => 'C1', 'email' => 'found@test.com']);
        $this->contacts->expects($this->never())->method('findByPhone');
        $this->contacts->expects($this->once())->method('update');

        $this->importer->importFromCsv($csv, [], ['match_field' => 'email_or_phone']);
    }

    public function test_import_with_field_mapping(): void
    {
        $csv = $this->writeCsv([
            ['Email Address', 'Mobile', 'Given Name'],
            ['mapped@test.com', '+111', 'Mapped'],
        ]);

        $mapping = [
            'email'      => 'Email Address',
            'phone'      => 'Mobile',
            'first_name' => 'Given Name',
        ];

        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->expects($this->once())->method('create')
            ->with($this->callback(function ($data) {
                return $data['email'] === 'mapped@test.com'
                    && $data['phone'] === '+111'
                    && $data['first_name'] === 'Mapped'
                    && $data['source'] === 'import';
            }));

        $this->importer->importFromCsv($csv, $mapping);
    }

    public function test_import_wraps_in_transaction(): void
    {
        $csv = $this->writeCsv([
            ['email'],
            ['test@test.com'],
        ]);

        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->method('create')->willReturn('C1');

        $this->importer->importFromCsv($csv);

        $queries = $GLOBALS['wpdb']->queries;
        $this->assertSame('START TRANSACTION', $queries[0]);
        $this->assertSame('COMMIT', $queries[1]);
    }

    public function test_import_row_error_does_not_abort(): void
    {
        $csv = $this->writeCsv([
            ['email'],
            ['good@test.com'],
            ['bad@test.com'],
            ['also-good@test.com'],
        ]);

        $callCount = 0;
        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->method('create')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 2) {
                throw new \RuntimeException('DB error');
            }
            return 'C' . $callCount;
        });

        $result = $this->importer->importFromCsv($csv);

        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_previewCsv_returns_headers_and_sample_rows(): void
    {
        $csv = $this->writeCsv([
            ['email', 'phone', 'name'],
            ['a@b.com', '+1', 'Alice'],
            ['c@d.com', '+2', 'Bob'],
            ['e@f.com', '+3', 'Charlie'],
            ['g@h.com', '+4', 'Dave'],
            ['i@j.com', '+5', 'Eve'],
            ['k@l.com', '+6', 'Frank'],
        ]);

        $result = $this->importer->previewCsv($csv);

        $this->assertSame(['email', 'phone', 'name'], $result['headers']);
        $this->assertCount(5, $result['rows']); // Max 5
        $this->assertSame(['a@b.com', '+1', 'Alice'], $result['rows'][0]);
    }

    public function test_import_custom_fields_via_mapping(): void
    {
        $csv = $this->writeCsv([
            ['email', 'company', 'department'],
            ['test@test.com', 'Acme', 'Engineering'],
        ]);

        $mapping = [
            'email'             => 'email',
            'custom.company'    => 'company',
            'custom.department' => 'department',
        ];

        $this->contacts->method('findByEmail')->willReturn(null);
        $this->contacts->expects($this->once())->method('create')
            ->with($this->callback(function ($data) {
                return isset($data['custom_fields'])
                    && $data['custom_fields']['company'] === 'Acme'
                    && $data['custom_fields']['department'] === 'Engineering';
            }));

        $this->importer->importFromCsv($csv, $mapping);
    }

    private function writeCsv(array $rows): string
    {
        $path = $this->tempDir . '/test-import-' . uniqid() . '.csv';
        $fp = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($fp, $row, ',', '"', '');
        }
        fclose($fp);
        return $path;
    }
}
