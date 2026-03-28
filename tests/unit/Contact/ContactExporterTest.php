<?php

namespace WSms\Tests\Unit\Contact;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WSms\Contact\ContactExporter;
use WSms\Contact\Contracts\ContactRepositoryInterface;

class ContactExporterTest extends TestCase
{
    private ContactRepositoryInterface&MockObject $contacts;
    private ContactExporter $exporter;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);
        $this->exporter = new ContactExporter($this->contacts);
        $this->tempDir = sys_get_temp_dir();
    }

    public function test_export_includes_all_fields(): void
    {
        $this->contacts->method('findAll')->willReturn([
            [
                'id' => 'C1', 'email' => 'test@test.com', 'phone' => '+1234567890',
                'first_name' => 'John', 'last_name' => 'Doe', 'status' => 'subscribed',
                'source' => 'manual', 'wp_user_id' => '42', 'custom_fields' => ['company' => 'Acme'],
                'created_at' => '2026-01-01', 'updated_at' => '2026-01-02',
            ],
        ]);

        $this->contacts->method('getTagsForContacts')->willReturn([
            'C1' => [
                ['id' => 'T1', 'name' => 'VIP', 'slug' => 'vip', 'color' => '#ef4444'],
                ['id' => 'T2', 'name' => 'Active', 'slug' => 'active', 'color' => '#22c55e'],
            ],
        ]);

        $path = $this->tempDir . '/export-test-' . uniqid() . '.csv';
        $this->exporter->exportToCsv([], $path);

        $content = file_get_contents($path);
        $lines = explode("\n", trim($content));

        // Header should include all fields + custom field columns
        $header = str_getcsv($lines[0]);
        $this->assertContains('wp_user_id', $header);
        $this->assertContains('updated_at', $header);
        $this->assertContains('tags', $header);
        $this->assertContains('custom_company', $header);

        // Data row
        $data = str_getcsv($lines[1]);
        $headerMap = array_flip($header);
        $this->assertSame('42', $data[$headerMap['wp_user_id']]);
        $this->assertSame('VIP, Active', $data[$headerMap['tags']]);
        $this->assertSame('Acme', $data[$headerMap['custom_company']]);

        unlink($path);
    }

    public function test_export_handles_empty_list(): void
    {
        $this->contacts->method('findAll')->willReturn([]);

        $path = $this->tempDir . '/export-empty-' . uniqid() . '.csv';
        $this->exporter->exportToCsv([], $path);

        $content = file_get_contents($path);
        $lines = explode("\n", trim($content));

        $this->assertCount(1, $lines); // Only header
        $header = str_getcsv($lines[0]);
        $this->assertSame('id', $header[0]);

        unlink($path);
    }

    public function test_export_flattens_custom_fields_across_contacts(): void
    {
        $this->contacts->method('findAll')->willReturn([
            [
                'id' => 'C1', 'email' => 'a@b.com', 'phone' => '', 'first_name' => '',
                'last_name' => '', 'status' => 'subscribed', 'source' => 'manual',
                'wp_user_id' => null, 'custom_fields' => ['company' => 'Acme'],
                'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
            ],
            [
                'id' => 'C2', 'email' => 'c@d.com', 'phone' => '', 'first_name' => '',
                'last_name' => '', 'status' => 'subscribed', 'source' => 'manual',
                'wp_user_id' => null, 'custom_fields' => ['department' => 'Eng'],
                'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
            ],
        ]);

        $this->contacts->method('getTagsForContacts')->willReturn([]);

        $path = $this->tempDir . '/export-multi-' . uniqid() . '.csv';
        $this->exporter->exportToCsv([], $path);

        $content = file_get_contents($path);
        $lines = explode("\n", trim($content));
        $header = str_getcsv($lines[0]);

        // Both custom field keys should be in header
        $this->assertContains('custom_company', $header);
        $this->assertContains('custom_department', $header);

        unlink($path);
    }

    public function test_export_handles_json_string_custom_fields(): void
    {
        $this->contacts->method('findAll')->willReturn([
            [
                'id' => 'C1', 'email' => 'a@b.com', 'phone' => '', 'first_name' => '',
                'last_name' => '', 'status' => 'subscribed', 'source' => 'manual',
                'wp_user_id' => null, 'custom_fields' => '{"key":"val"}',
                'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
            ],
        ]);

        $this->contacts->method('getTagsForContacts')->willReturn([]);

        $path = $this->tempDir . '/export-json-str-' . uniqid() . '.csv';
        $this->exporter->exportToCsv([], $path);

        $content = file_get_contents($path);
        $lines = explode("\n", trim($content));
        $header = str_getcsv($lines[0]);

        $this->assertContains('custom_key', $header);

        unlink($path);
    }
}
