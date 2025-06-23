<?php

namespace WP_SMS\User;

use WP_SMS\BackgroundProcess\Async\MobileNumberSyncProcess;

class SyncHelper
{
    public static function scheduleMobileFieldSync(string $oldSource, string $newSource): void
    {
        $oldKey = self::getMetaKeyFromSource($oldSource);
        $newKey = self::getMetaKeyFromSource($newSource);

        // Optional debug log (you can remove in production)
        WPSms()::log('Scheduling sync from meta key: ' . $oldKey . ' to ' . $newKey);

        if ($oldKey === $newKey) {
            return;
        }

        $process = WPSms()->getBackgroundProcess('mobile_number_sync');

        if (!$process instanceof MobileNumberSyncProcess) {
            WPSms()::log('Background process not available or incorrect type.');
            return;
        }

        $batchSize = 200;
        $offset    = 0;

        do {
            $users = get_users([
                'fields' => ['ID'],
                'number' => $batchSize,
                'offset' => $offset,
            ]);

            foreach ($users as $user) {
                $process->push_to_queue([
                    'user_id'     => $user->ID,
                    'old_meta'    => $oldKey,
                    'new_meta'    => $newKey,
                ]);
            }

            $offset += $batchSize;
        } while (count($users) === $batchSize);

        $process->save()->dispatch();
    }

    public static function getMetaKeyFromSource(string $source): ?string
    {
        switch ($source) {
            case 'add_mobile_field_in_profile':
            case 'add_mobile_field_in_wc_billing':
                return 'mobile'; // WP SMS-defined meta key

            case 'use_phone_field_in_wc_billing':
                return 'billing_phone'; // WooCommerce native key

            default:
                return null;
        }
    }
}
