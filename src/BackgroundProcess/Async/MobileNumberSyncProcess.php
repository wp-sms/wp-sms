<?php

namespace WP_SMS\BackgroundProcess\Async;

use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;
use WP_SMS\Traits\TransientCacheTrait;

class MobileNumberSyncProcess extends WP_Background_Process
{
    use TransientCacheTrait;

    protected $action = 'mobile_number_sync';

    protected $updated = 0;
    protected $inserted = 0;
    protected $skipped = 0;

    protected function task($item)
    {
        $user_id = $item['user_id'] ?? null;
        $oldMeta = $item['old_meta'] ?? null;
        $newMeta = $item['new_meta'] ?? null;

        if (!$user_id || !$oldMeta || !$newMeta || $oldMeta === $newMeta) {
            return false;
        }

        $oldValue = get_user_meta($user_id, $oldMeta, true);
        $newValue = get_user_meta($user_id, $newMeta, true);

        if (empty($oldValue)) {
            return false;
        }

        if (empty($newValue)) {
            update_user_meta($user_id, $newMeta, $oldValue);
            $this->inserted++;
        } elseif ($oldValue !== $newValue) {
            update_user_meta($user_id, $newMeta, $oldValue);
            $this->updated++;
        } else {
            $this->skipped++;
        }

        return false;
    }


    protected function complete()
    {
        $total_updated = $this->updated + $this->inserted;

        WPSms()::log("Mobile field sync completed – {$this->updated} conflicts resolved, {$this->inserted} inserted, {$this->skipped} skipped.");

        $message = sprintf(
            __('Mobile Number Field sync completed: %d numbers updated or added, %d skipped (already matched or empty).', 'wp-sms'),
            $total_updated,
            $this->skipped
        );

        $this->setCachedResult('wp_sms_mobile_sync_notice', $message, 60);

        parent::complete();
    }
}