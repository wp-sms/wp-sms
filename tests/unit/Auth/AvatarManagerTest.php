<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\AvatarManager;

class AvatarManagerTest extends TestCase
{
    private AvatarManager $manager;
    private string $testUploadDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new AvatarManager();
        $this->testUploadDir = sys_get_temp_dir() . '/wsms-test-uploads-' . uniqid();
        $GLOBALS['_test_upload_dir'] = $this->testUploadDir;
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_image_editor'], $GLOBALS['_test_wp_mkdir_p']);
    }

    protected function tearDown(): void
    {
        // Cleanup test upload directory.
        $avatarDir = $this->testUploadDir . '/wsms-avatars';
        if (is_dir($avatarDir)) {
            array_map('unlink', glob($avatarDir . '/*'));
            @rmdir($avatarDir);
        }
        @rmdir($this->testUploadDir);

        unset(
            $GLOBALS['_test_upload_dir'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_image_editor'],
            $GLOBALS['_test_wp_mkdir_p'],
        );

        parent::tearDown();
    }

    // ──────────────────────────────────────────────
    //  downloadAndStoreAvatar — skip / failure cases
    // ──────────────────────────────────────────────

    public function testReturnsFalseWhenUserHasExistingCustomAvatar(): void
    {
        $GLOBALS['_test_user_meta'][1]['wsms_custom_avatar'] = 'http://localhost/existing.jpg';

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $this->assertFalse($result);
    }

    public function testReturnsFalseOnNetworkError(): void
    {
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_error', 'Connection failed');

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $this->assertFalse($result);
    }

    public function testReturnsFalseOnNon200Response(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'response' => ['code' => 404],
            'body'     => 'Not Found',
            'headers'  => ['content-type' => 'text/html'],
        ];

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $this->assertFalse($result);
    }

    public function testReturnsFalseOnEmptyBody(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'response' => ['code' => 200],
            'body'     => '',
            'headers'  => ['content-type' => 'image/jpeg'],
        ];

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $this->assertFalse($result);
    }

    public function testReturnsFalseOnOversizedBody(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'response' => ['code' => 200],
            'body'     => str_repeat('x', 2 * 1024 * 1024 + 1),
            'headers'  => ['content-type' => 'image/jpeg'],
        ];

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $this->assertFalse($result);
    }

    /**
     * @dataProvider badMimeTypeProvider
     */
    public function testReturnsFalseOnBadMimeType(string $contentType): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'response' => ['code' => 200],
            'body'     => 'fake-image-data',
            'headers'  => ['content-type' => $contentType],
        ];

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.bmp');

        $this->assertFalse($result);
    }

    public static function badMimeTypeProvider(): array
    {
        return [
            'text/html'       => ['text/html'],
            'application/pdf' => ['application/pdf'],
            'image/bmp'       => ['image/bmp'],
            'image/svg+xml'   => ['image/svg+xml'],
            'empty'           => [''],
        ];
    }

    public function testReturnsFalseWhenMkdirFails(): void
    {
        $GLOBALS['_test_wp_remote_get'] = $this->makeValidImageResponse('image/jpeg');
        $GLOBALS['_test_wp_mkdir_p'] = false;

        $result = $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $this->assertFalse($result);
    }

    // ──────────────────────────────────────────────
    //  downloadAndStoreAvatar — success cases
    // ──────────────────────────────────────────────

    /**
     * @dataProvider validMimeTypeProvider
     */
    public function testSucceedsForValidImage(string $mime, string $expectedExt): void
    {
        $GLOBALS['_test_wp_remote_get'] = $this->makeValidImageResponse($mime);

        $result = $this->manager->downloadAndStoreAvatar(42, 'https://example.com/photo');

        $this->assertTrue($result);

        // Verify META_CUSTOM_AVATAR was set with local URL.
        $storedUrl = $GLOBALS['_test_user_meta'][42]['wsms_custom_avatar'] ?? '';
        $this->assertStringContainsString('/wsms-avatars/42.' . $expectedExt, $storedUrl);

        // Verify file exists on disk.
        $expectedFile = $this->testUploadDir . '/wsms-avatars/42.' . $expectedExt;
        $this->assertFileExists($expectedFile);
    }

    public static function validMimeTypeProvider(): array
    {
        return [
            'jpeg'        => ['image/jpeg', 'jpg'],
            'jpg variant' => ['image/jpg', 'jpg'],
            'png'         => ['image/png', 'png'],
            'gif'         => ['image/gif', 'gif'],
            'webp'        => ['image/webp', 'webp'],
        ];
    }

    public function testHandlesMimeWithCharsetSuffix(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'response' => ['code' => 200],
            'body'     => 'fake-image-data',
            'headers'  => ['content-type' => 'image/png; charset=UTF-8'],
        ];

        $result = $this->manager->downloadAndStoreAvatar(5, 'https://example.com/photo');

        $this->assertTrue($result);
        $storedUrl = $GLOBALS['_test_user_meta'][5]['wsms_custom_avatar'] ?? '';
        $this->assertStringContainsString('/wsms-avatars/5.png', $storedUrl);
    }

    public function testCleansUpTempFileOnSuccess(): void
    {
        $GLOBALS['_test_wp_remote_get'] = $this->makeValidImageResponse('image/jpeg');

        $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        // Temp files in system temp dir with wsms_avatar_ prefix should be cleaned up.
        $tempFiles = glob(sys_get_temp_dir() . '/wsms_avatar_*');
        $this->assertEmpty($tempFiles, 'Temp file was not cleaned up');
    }

    public function testCleansUpTempFileOnFailure(): void
    {
        $GLOBALS['_test_wp_remote_get'] = $this->makeValidImageResponse('image/jpeg');
        $GLOBALS['_test_wp_mkdir_p'] = false;

        $this->manager->downloadAndStoreAvatar(1, 'https://example.com/photo.jpg');

        $tempFiles = glob(sys_get_temp_dir() . '/wsms_avatar_*');
        $this->assertEmpty($tempFiles, 'Temp file was not cleaned up after failure');
    }

    public function testDoesNotOverwriteExistingCustomAvatar(): void
    {
        $originalUrl = 'http://localhost/wp-content/uploads/wsms-avatars/1.png';
        $GLOBALS['_test_user_meta'][1]['wsms_custom_avatar'] = $originalUrl;
        $GLOBALS['_test_wp_remote_get'] = $this->makeValidImageResponse('image/jpeg');

        $this->manager->downloadAndStoreAvatar(1, 'https://example.com/new-photo.jpg');

        // Custom avatar should remain unchanged.
        $this->assertSame($originalUrl, $GLOBALS['_test_user_meta'][1]['wsms_custom_avatar']);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function makeValidImageResponse(string $mime): array
    {
        return [
            'response' => ['code' => 200],
            'body'     => 'fake-image-data',
            'headers'  => ['content-type' => $mime],
        ];
    }
}
