<?php

namespace WP_SMS\Services\Settings;

use WP_SMS\Notice\NoticeManager;
use WP_SMS\Traits\TransientCacheTrait;
use WP_SMS\User\SyncHelper;

class MobileFieldSourceWatcher
{
    use TransientCacheTrait;

    public function register(): void
    {
        add_filter('pre_update_option_wpsms_settings', [$this, 'maybeSyncMobileField'], 10, 2);
        if ($msg = $this->getCachedResult('wp_sms_mobile_sync_notice')) {
            NoticeManager::getInstance()->registerNotice($this->getCacheKey('wp_sms_mobile_sync_notice'), esc_html($msg), true);
        }
    }

    public function maybeSyncMobileField(array $new, array $old): array
    {
        $oldSource = $old['add_mobile_field'] ?? null;
        $newSource = $new['add_mobile_field'] ?? null;

        if ($oldSource && $newSource && $oldSource !== $newSource) {
            SyncHelper::scheduleMobileFieldSync($oldSource, $newSource);
        }

        return $new;
    }
}
