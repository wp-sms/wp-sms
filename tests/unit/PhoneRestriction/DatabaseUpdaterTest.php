<?php

namespace WSms\Tests\Unit\PhoneRestriction;

use PHPUnit\Framework\TestCase;
use WSms\PhoneRestriction\DatabaseUpdater;
use WSms\PhoneRestriction\EnhancedPhoneDatabase;
use WSms\PhoneRestriction\RestrictionSettings;

class DatabaseUpdaterTest extends TestCase
{
    private string $tmpDir;
    private RestrictionSettings $settings;
    private DatabaseUpdater $updater;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wsms-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $GLOBALS['_test_upload_dir'] = $this->tmpDir;
        $GLOBALS['_test_options']    = [];
        $GLOBALS['_test_as_scheduled_actions'] = [];

        unset($GLOBALS['_test_wp_mkdir_p']);
        unset($GLOBALS['_test_wp_remote_get']);

        EnhancedPhoneDatabase::resetCache();

        $this->settings = new RestrictionSettings();
        $this->updater  = new DatabaseUpdater($this->settings);
    }

    protected function tearDown(): void
    {
        EnhancedPhoneDatabase::resetCache();
        $this->deleteDir($this->tmpDir);

        unset($GLOBALS['_test_upload_dir']);
        unset($GLOBALS['_test_wp_mkdir_p']);
        unset($GLOBALS['_test_wp_remote_get']);
        $GLOBALS['_test_as_scheduled_actions'] = [];
    }

    // --- download() ---

    public function test_download_success_writes_file(): void
    {
        $this->mockCdnResponse($this->validDbJson());

        $result = $this->updater->download();

        $this->assertTrue($result['success']);
        $this->assertFileExists(EnhancedPhoneDatabase::getFilePath());

        $written = json_decode(file_get_contents(EnhancedPhoneDatabase::getFilePath()), true);
        $this->assertArrayHasKey('territories', $written);
    }

    public function test_download_resets_cache_after_success(): void
    {
        // Pre-load should return null (no file)
        $this->assertNull(EnhancedPhoneDatabase::load());
        EnhancedPhoneDatabase::resetCache();

        $this->mockCdnResponse($this->validDbJson());
        $this->updater->download();

        // After download, load() should return the new data
        $db = EnhancedPhoneDatabase::load();
        $this->assertNotNull($db);
        $this->assertSame('1.0.0', $db->getVersion());
    }

    public function test_download_returns_failure_on_network_error(): void
    {
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'Connection timed out');

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timed out', $result['message']);
        $this->assertFileDoesNotExist(EnhancedPhoneDatabase::getFilePath());
    }

    public function test_download_returns_failure_on_non_200_status(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => 'Not Found',
            'response' => ['code' => 404],
        ];

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('404', $result['message']);
    }

    public function test_download_returns_failure_on_invalid_json(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => '<html>Error</html>',
            'response' => ['code' => 200],
        ];

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid response from server.', $result['message']);
    }

    public function test_download_returns_failure_when_territories_missing(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode(['version' => '1.0.0']),
            'response' => ['code' => 200],
        ];

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid database format.', $result['message']);
    }

    public function test_download_returns_failure_when_territories_empty(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode(['version' => '1.0.0', 'territories' => []]),
            'response' => ['code' => 200],
        ];

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid database format.', $result['message']);
    }

    public function test_download_returns_failure_when_response_too_large(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => str_repeat('x', 2 * 1024 * 1024 + 1),
            'response' => ['code' => 200],
        ];

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertSame('Response exceeds size limit.', $result['message']);
    }

    public function test_download_returns_failure_when_dir_not_writable(): void
    {
        $GLOBALS['_test_wp_mkdir_p'] = false;
        $this->mockCdnResponse($this->validDbJson());

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertSame('Could not create storage directory.', $result['message']);
    }

    public function test_download_does_not_corrupt_existing_on_failure(): void
    {
        // Write an existing valid file
        $path = EnhancedPhoneDatabase::getFilePath();
        $dir  = dirname($path);
        @mkdir($dir, 0755, true);
        file_put_contents($path, $this->validDbJson());
        $originalContent = file_get_contents($path);

        // Now simulate a failed download (invalid JSON)
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => '<html>Error</html>',
            'response' => ['code' => 200],
        ];

        $result = $this->updater->download();

        $this->assertFalse($result['success']);
        $this->assertSame($originalContent, file_get_contents($path));
    }

    // --- syncCronSchedule() ---

    public function test_sync_cron_schedules_when_auto_update_enabled(): void
    {
        // auto_update is true by default in RestrictionSettings
        $this->updater->syncCronSchedule();

        $this->assertTrue(as_has_scheduled_action(DatabaseUpdater::CRON_HOOK, [], DatabaseUpdater::AS_GROUP));

        $action = $GLOBALS['_test_as_scheduled_actions'][0];
        $this->assertSame(WEEK_IN_SECONDS, $action['interval']);
        $this->assertSame(DatabaseUpdater::CRON_HOOK, $action['hook']);
    }

    public function test_sync_cron_skips_if_already_scheduled(): void
    {
        $this->updater->syncCronSchedule();
        $this->updater->syncCronSchedule();

        $this->assertCount(1, $GLOBALS['_test_as_scheduled_actions']);
    }

    public function test_sync_cron_unschedules_when_disabled(): void
    {
        // First schedule it
        $this->updater->syncCronSchedule();
        $this->assertCount(1, $GLOBALS['_test_as_scheduled_actions']);

        // Disable auto_update
        $this->settings->save(['enhanced_db' => ['auto_update' => false]]);

        $this->updater->syncCronSchedule();

        $this->assertFalse(as_has_scheduled_action(DatabaseUpdater::CRON_HOOK, [], DatabaseUpdater::AS_GROUP));
    }

    // --- handleCron() ---

    public function test_handle_cron_downloads_when_enabled(): void
    {
        $this->mockCdnResponse($this->validDbJson());

        $this->updater->handleCron();

        $this->assertFileExists(EnhancedPhoneDatabase::getFilePath());
    }

    public function test_handle_cron_skips_when_disabled(): void
    {
        $this->settings->save(['enhanced_db' => ['auto_update' => false]]);
        $this->mockCdnResponse($this->validDbJson());

        $this->updater->handleCron();

        $this->assertFileDoesNotExist(EnhancedPhoneDatabase::getFilePath());
    }

    // --- unschedule() ---

    public function test_unschedule_clears_action(): void
    {
        $this->updater->syncCronSchedule();
        $this->assertTrue(as_has_scheduled_action(DatabaseUpdater::CRON_HOOK, [], DatabaseUpdater::AS_GROUP));

        $this->updater->unschedule();

        $this->assertFalse(as_has_scheduled_action(DatabaseUpdater::CRON_HOOK, [], DatabaseUpdater::AS_GROUP));
    }

    // --- helpers ---

    private function mockCdnResponse(string $body): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => 200],
        ];
    }

    private function validDbJson(): string
    {
        return json_encode([
            'version'     => '1.0.0',
            'territories' => [
                'US' => [
                    'country_code'  => '1',
                    'leading_digits' => '2[0-9]|3[0-9]',
                    'patterns'      => [
                        'mobile'    => '[2-9]\\d{9}',
                        'toll_free' => '8(?:00|55|66|77|88)\\d{7}',
                    ],
                ],
            ],
        ]);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
