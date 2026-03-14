<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class AvatarManager
{
    /** @var array<int, ?string> Request-scoped avatar URL cache. */
    private array $avatarUrlCache = [];

    private const UPLOAD_DIR = 'wsms-avatars';
    private const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_MIMES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    ];
    private const META_CUSTOM_AVATAR = 'wsms_custom_avatar';
    private const META_SOCIAL_AVATAR = 'wsms_social_avatar';
    private const RESIZE_PX = 256;

    /**
     * Upload a custom avatar for a user.
     *
     * @param int $userId
     * @param array $file $_FILES-style array
     * @return array{success: bool, error?: string, message: string, avatar_url?: string}
     */
    public function uploadAvatar(int $userId, array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'no_file', 'message' => 'No file uploaded.'];
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            return ['success' => false, 'error' => 'file_too_large', 'message' => 'File exceeds 2MB limit.'];
        }

        $validated = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], self::ALLOWED_MIMES);

        if (empty($validated['type'])) {
            return ['success' => false, 'error' => 'invalid_type', 'message' => 'Invalid image type. Allowed: JPG, PNG, GIF, WebP.'];
        }

        $uploadDir = $this->getUploadDir();
        if (!wp_mkdir_p($uploadDir)) {
            return ['success' => false, 'error' => 'upload_failed', 'message' => 'Could not create upload directory.'];
        }

        // Remove old avatar file.
        $this->deleteAvatarFile($userId);

        $ext = $validated['ext'] ?: 'jpg';
        $filename = $userId . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        // Resize via WP image editor.
        $editor = wp_get_image_editor($file['tmp_name']);

        if (is_wp_error($editor)) {
            // Fallback: move without resize.
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'error' => 'upload_failed', 'message' => 'Failed to save file.'];
            }
        } else {
            $editor->resize(self::RESIZE_PX, self::RESIZE_PX, true);
            $saved = $editor->save($filepath);

            if (is_wp_error($saved)) {
                return ['success' => false, 'error' => 'resize_failed', 'message' => 'Failed to process image.'];
            }

            // Use the actual saved path (extension may differ).
            $filepath = $saved['path'];
            $filename = basename($filepath);
        }

        $url = $this->getUploadUrl() . '/' . $filename;
        update_user_meta($userId, self::META_CUSTOM_AVATAR, $url);
        unset($this->avatarUrlCache[$userId]);

        return [
            'success'    => true,
            'message'    => 'Avatar uploaded successfully.',
            'avatar_url' => $url,
        ];
    }

    /**
     * Delete a user's custom avatar.
     */
    public function deleteAvatar(int $userId): void
    {
        $this->deleteAvatarFile($userId);
        delete_user_meta($userId, self::META_CUSTOM_AVATAR);
        unset($this->avatarUrlCache[$userId]);
    }

    /**
     * Get avatar URL with fallback chain: custom upload → social → null.
     */
    public function getAvatarUrl(int $userId): ?string
    {
        if (array_key_exists($userId, $this->avatarUrlCache)) {
            return $this->avatarUrlCache[$userId];
        }

        $custom = get_user_meta($userId, self::META_CUSTOM_AVATAR, true);
        if (!empty($custom)) {
            return $this->avatarUrlCache[$userId] = $custom;
        }

        $social = get_user_meta($userId, self::META_SOCIAL_AVATAR, true);

        return $this->avatarUrlCache[$userId] = (!empty($social) ? $social : null);
    }

    /**
     * Save a social provider's avatar URL.
     */
    public function saveSocialAvatar(int $userId, string $url): void
    {
        if (empty($url)) {
            return;
        }

        update_user_meta($userId, self::META_SOCIAL_AVATAR, esc_url_raw($url));
    }

    /**
     * Download a social avatar and store it locally as a custom avatar.
     *
     * Uses META_CUSTOM_AVATAR so it becomes the primary avatar. Skips if the user
     * already has a custom avatar (user-uploaded takes precedence). Never throws —
     * failures must not block login.
     */
    public function downloadAndStoreAvatar(int $userId, string $url): bool
    {
        // Don't overwrite user-uploaded avatars.
        $existing = get_user_meta($userId, self::META_CUSTOM_AVATAR, true);

        if (!empty($existing)) {
            return false;
        }

        $tempFile = null;

        try {
            $response = wp_remote_get($url, ['timeout' => 10]);

            if (is_wp_error($response)) {
                return false;
            }

            if (wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }

            $body = wp_remote_retrieve_body($response);

            if (empty($body)) {
                return false;
            }

            if (strlen($body) > self::MAX_SIZE_BYTES) {
                return false;
            }

            // Validate Content-Type against allowed MIME types.
            $contentType = wp_remote_retrieve_header($response, 'content-type');
            $mime = strtolower(trim(explode(';', (string) $contentType)[0]));
            $ext = $this->extensionFromMime($mime);

            if ($ext === null) {
                return false;
            }

            // Write to temp file for image processing.
            $tempFile = wp_tempnam('wsms_avatar_');
            file_put_contents($tempFile, $body);

            $uploadDir = $this->getUploadDir();

            if (!wp_mkdir_p($uploadDir)) {
                return false;
            }

            $this->deleteAvatarFile($userId);

            $filename = $userId . '.' . $ext;
            $filepath = $uploadDir . '/' . $filename;

            // Resize to standard dimensions via WP image editor.
            $editor = wp_get_image_editor($tempFile);

            if (!is_wp_error($editor)) {
                $editor->resize(self::RESIZE_PX, self::RESIZE_PX, true);
                $saved = $editor->save($filepath);

                if (!is_wp_error($saved)) {
                    $filepath = $saved['path'];
                    $filename = basename($filepath);
                }
            } else {
                // Fallback: copy without resize.
                copy($tempFile, $filepath);
            }

            $localUrl = $this->getUploadUrl() . '/' . $filename;
            update_user_meta($userId, self::META_CUSTOM_AVATAR, $localUrl);
            unset($this->avatarUrlCache[$userId]);

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            if ($tempFile !== null && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Filter WordPress get_avatar_url to use our avatar chain.
     * Hook: get_avatar_url (priority 10).
     */
    public function filterGetAvatarUrl(string $url, mixed $idOrEmail, array $args): string
    {
        $userId = $this->resolveUserId($idOrEmail);

        if (!$userId) {
            return $url;
        }

        $customUrl = $this->getAvatarUrl($userId);

        return $customUrl ?: $url;
    }

    /**
     * Filter WordPress get_avatar to use our avatar chain.
     * Hook: get_avatar (priority 10).
     */
    public function filterGetAvatar(string $avatar, mixed $idOrEmail, int $size, string $default, string $alt, array $args): string
    {
        $userId = $this->resolveUserId($idOrEmail);

        if (!$userId) {
            return $avatar;
        }

        $customUrl = $this->getAvatarUrl($userId);

        if (!$customUrl) {
            return $avatar;
        }

        return sprintf(
            '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" loading="lazy" />',
            esc_attr($alt),
            esc_url($customUrl),
            (int) $size,
            (int) $size,
            (int) $size,
        );
    }

    /**
     * Cleanup avatar files on user deletion.
     * Hook: delete_user.
     */
    public function cleanupOnUserDelete(int $userId): void
    {
        $this->deleteAvatarFile($userId);
    }

    /**
     * GDPR: export custom avatar data.
     */
    public function exportPersonalData(array $exportItems, string $emailAddress, int $page): array
    {
        $user = get_user_by('email', $emailAddress);

        if (!$user) {
            return $exportItems;
        }

        $customAvatar = get_user_meta($user->ID, self::META_CUSTOM_AVATAR, true);
        $socialAvatar = get_user_meta($user->ID, self::META_SOCIAL_AVATAR, true);

        if (!empty($customAvatar) || !empty($socialAvatar)) {
            $data = [];

            if (!empty($customAvatar)) {
                $data[] = ['name' => 'Custom Avatar URL', 'value' => $customAvatar];
            }

            if (!empty($socialAvatar)) {
                $data[] = ['name' => 'Social Avatar URL', 'value' => $socialAvatar];
            }

            $exportItems[] = [
                'group_id'    => 'wsms-avatar',
                'group_label' => 'WP SMS Avatar',
                'item_id'     => 'wsms-avatar-' . $user->ID,
                'data'        => $data,
            ];
        }

        return $exportItems;
    }

    /**
     * GDPR: erase avatar data.
     */
    public function erasePersonalData(string $emailAddress, int $page): array
    {
        $user = get_user_by('email', $emailAddress);

        if (!$user) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        $hadCustom = !empty(get_user_meta($user->ID, self::META_CUSTOM_AVATAR, true));
        $hadSocial = !empty(get_user_meta($user->ID, self::META_SOCIAL_AVATAR, true));

        $this->deleteAvatar($user->ID);
        delete_user_meta($user->ID, self::META_SOCIAL_AVATAR);

        return [
            'items_removed'  => $hadCustom || $hadSocial,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }

    private function deleteAvatarFile(int $userId): void
    {
        $dir = $this->getUploadDir();
        $pattern = $dir . '/' . $userId . '.*';
        $files = glob($pattern);

        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private function getUploadDir(): string
    {
        $uploadDir = wp_upload_dir();

        return $uploadDir['basedir'] . '/' . self::UPLOAD_DIR;
    }

    private function getUploadUrl(): string
    {
        $uploadDir = wp_upload_dir();

        return $uploadDir['baseurl'] . '/' . self::UPLOAD_DIR;
    }

    private function extensionFromMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png'               => 'png',
            'image/gif'               => 'gif',
            'image/webp'              => 'webp',
            default                   => null,
        };
    }

    private function resolveUserId(mixed $idOrEmail): ?int
    {
        if (is_numeric($idOrEmail)) {
            return (int) $idOrEmail;
        }

        if ($idOrEmail instanceof \WP_User) {
            return $idOrEmail->ID;
        }

        if ($idOrEmail instanceof \WP_Comment) {
            return $idOrEmail->user_id ? (int) $idOrEmail->user_id : null;
        }

        if (is_string($idOrEmail) && is_email($idOrEmail)) {
            $user = get_user_by('email', $idOrEmail);
            return $user ? $user->ID : null;
        }

        return null;
    }
}
