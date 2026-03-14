<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class AvatarManager
{
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
    }

    /**
     * Get avatar URL with fallback chain: custom upload → social → null.
     */
    public function getAvatarUrl(int $userId): ?string
    {
        $custom = get_user_meta($userId, self::META_CUSTOM_AVATAR, true);
        if (!empty($custom)) {
            return $custom;
        }

        $social = get_user_meta($userId, self::META_SOCIAL_AVATAR, true);
        if (!empty($social)) {
            return $social;
        }

        return null;
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

        $this->deleteAvatar($user->ID);
        delete_user_meta($user->ID, self::META_SOCIAL_AVATAR);

        return [
            'items_removed'  => true,
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
