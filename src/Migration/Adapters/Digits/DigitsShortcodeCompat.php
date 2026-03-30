<?php

namespace WSms\Migration\Adapters\Digits;

use WSms\Database\Connection;

defined('ABSPATH') || exit;

class DigitsShortcodeCompat
{
    private const SHIM_MAP = [
        'digits_phone_login' => '[wsms_auth page="login"]',
        'digits_register'    => '[wsms_auth page="register"]',
        'digits_login'       => '[wsms_auth page="login"]',
        'digits_profile'     => '[wsms_auth page="profile"]',
    ];

    private const TTL_MONTHS = 6;
    private const OPTION_KEY = 'wsms_migration_shortcode_compat';

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function register(): void
    {
        $config = get_option(self::OPTION_KEY);
        if (!$config || !($config['active'] ?? false)) {
            return;
        }

        // Check TTL expiry.
        $expiresAt = $config['expires_at'] ?? '';
        if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
            $this->scheduleExpiryNotice($config['affected_pages'] ?? []);
            return;
        }

        foreach (self::SHIM_MAP as $shortcode => $replacement) {
            if (!shortcode_exists($shortcode)) {
                add_shortcode($shortcode, fn() => do_shortcode($replacement));
            }
        }
    }

    /**
     * Scan for published pages using Digits shortcodes.
     *
     * @return array[] Each: {id, title, shortcodes[]}
     */
    public function detectAffectedPages(): array
    {
        $postsTable = $this->db->table('posts');
        $patterns = array_keys(self::SHIM_MAP);

        $likeConditions = [];
        foreach ($patterns as $shortcode) {
            $likeConditions[] = $this->db->prepare(
                "post_content LIKE %s",
                '%[' . $this->db->escLike($shortcode) . '%',
            );
        }

        if (empty($likeConditions)) {
            return [];
        }

        $whereClause = implode(' OR ', $likeConditions);

        $rows = $this->db->getResults(
            "SELECT ID, post_title, post_content FROM {$postsTable}
             WHERE ({$whereClause}) AND post_status = 'publish'",
        );

        $affected = [];
        foreach ($rows as $row) {
            $found = [];
            foreach ($patterns as $shortcode) {
                if (str_contains($row['post_content'], '[' . $shortcode)) {
                    $found[] = $shortcode;
                }
            }
            if (!empty($found)) {
                $affected[] = [
                    'id'         => (int) $row['ID'],
                    'title'      => $row['post_title'],
                    'shortcodes' => $found,
                ];
            }
        }

        return $affected;
    }

    public function activate(): void
    {
        $affectedPages = $this->detectAffectedPages();
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+' . self::TTL_MONTHS . ' months'));

        update_option(self::OPTION_KEY, [
            'active'         => true,
            'activated_at'   => current_time('mysql', true),
            'expires_at'     => $expiresAt,
            'affected_pages' => $affectedPages,
        ], false);
    }

    public function deactivate(): void
    {
        delete_option(self::OPTION_KEY);
    }

    public function getAffectedPages(): array
    {
        $config = get_option(self::OPTION_KEY);
        return $config['affected_pages'] ?? [];
    }

    private function scheduleExpiryNotice(array $affectedPages): void
    {
        if (empty($affectedPages) || !is_admin()) {
            return;
        }

        add_action('admin_notices', function () use ($affectedPages) {
            $count = count($affectedPages);
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                sprintf(
                    esc_html__('You still have %d pages using Digits shortcodes. Consider updating them to WSMS shortcodes.', 'wp-sms'),
                    $count,
                ),
            );
        });
    }
}
