<?php

namespace WP_SMS\Admin\NoticeHandler;

use WP_SMS\Utils\AdminHelper;
use WP_SMS\Utils\Request;

class Notice
{
    private static $adminNotices = array();

    /**
     * List Of Dismissed Notices.
     *
     * @var array
     * @static
     * @access private
     */
    private static $dismissedNotices = [];

    public static function addNotice($message, $id, $class = 'info', $isDismissible = true)
    {
        $notice = array(
            'message'        => $message,
            'id'             => $id,
            'class'          => $class,
            'is_dismissible' => (bool)$isDismissible,
        );

        self::$adminNotices[$id] = $notice;
    }

    /**
     * @param $message
     * @param $class
     * @param $isDismissible
     * @return void
     */
    public static function addFlashNotice($message, $class = 'info', $isDismissible = true)
    {
        // Add flash notice using transient
        $flashNotices = get_transient('wp_statistics_flash_notices');
        if (!$flashNotices) {
            $flashNotices = array();
        }

        $flashNotices[] = array(
            'message'        => $message,
            'class'          => $class,
            'is_dismissible' => (bool)$isDismissible,
        );

        set_transient('wp_statistics_flash_notices', $flashNotices, 3); // Keep for 3 second
    }

    public static function displayNotices()
    {
        $dismissedNotices = self::getDismissedNotices();

        foreach (self::$adminNotices as $id => $notice) {
            if (in_array($id, $dismissedNotices, true)) {
                continue; // Skip dismissed notices
            }

            $dismissible = $notice['is_dismissible'] ? ' is-dismissible' : '';
            $dismissUrl  = self::getDismissUrl($id, $notice);

            self::renderNoticeInternal($notice, $dismissible, $dismissUrl);
        }

        // Display flash notices
        $flashNotices = get_transient('wp_statistics_flash_notices');

        if ($flashNotices) {
            foreach ($flashNotices as $flashNotice) {
                $dismissible = $flashNotice['is_dismissible'] ? ' is-dismissible' : '';
                self::renderNoticeInternal($flashNotice, $dismissible, '');
            }

            delete_transient('wp_statistics_flash_notices');
        }
    }

    private static function getDismissUrl($id, $notice)
    {
        if ($notice['is_dismissible']) {
            return add_query_arg(array(
                'action'    => 'wp_statistics_dismiss_notice',
                'notice_id' => $id,
                'nonce'     => wp_create_nonce('wp_statistics_dismiss_notice'),
            ), sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])));
        }

        return '';
    }

    public static function renderNotice($message, $id, $class = 'info')
    {
        $notice = array(
            'message'        => $message,
            'id'             => $id,
            'class'          => $class,
            'is_dismissible' => false,
        );

        $dismissible = $notice['is_dismissible'] ? ' is-dismissible' : '';
        $dismissUrl  = self::getDismissUrl($notice['id'], $notice);

        self::renderNoticeInternal($notice, $dismissible, $dismissUrl);
    }

    /**
     *
     * @todo Should use component View::load() instead of getTemplate
     * @param $notice
     * @param $dismissible
     * @param $dismissUrl
     * @return void
     */
    private static function renderNoticeInternal($notice, $dismissible, $dismissUrl)
    {
        AdminHelper::getTemplate('notice', [
            'notice'      => $notice,
            'dismissible' => $dismissible,
            'dismissUrl'  => $dismissUrl
        ]);
    }

    public static function getDismissedNotices() {
        if (empty(self::$dismissedNotices)) {
            self::$dismissedNotices = get_option('wp_statistics_dismissed_notices', []);
        }
        
        return self::$dismissedNotices;
    }
}
