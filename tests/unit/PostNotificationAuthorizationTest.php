<?php

namespace unit;

use WP_SMS\Notifications;

/**
 * The new-post SMS can only be sent by users with the wpsms_sendsms capability,
 * from the meta box form (nonce), never from request parameters alone.
 */
class PostNotificationAuthorizationTest extends WPSMSTestCase
{
    public function tearDown(): void
    {
        $_REQUEST = [];
        parent::tearDown();
    }

    private function publishWithSmsFields($userId, $withNonce)
    {
        wp_set_current_user($userId);

        $postId = self::factory()->post->create(['post_status' => 'draft', 'post_author' => $userId]);

        $_REQUEST = [
            'wps_send_to'         => 'numbers',
            'wps_mobile_numbers'  => '+15550001111',
            'wpsms_text_template' => 'test message',
        ];

        if ($withNonce) {
            $_REQUEST['wpsms_post_notification_nonce'] = wp_create_nonce('wpsms_post_notification');
        }

        $post              = get_post($postId);
        $post->post_status = 'publish';

        (new Notifications())->notify_subscribers_for_published_post($postId, $post, true);

        return (bool)get_post_meta($postId, '_wpsms_notification_sent', true);
    }

    public function test_author_cannot_send_sms_through_post_publish()
    {
        $authorId = self::factory()->user->create(['role' => 'author']);

        $this->assertFalse($this->publishWithSmsFields($authorId, false));
        $this->assertFalse($this->publishWithSmsFields($authorId, true));
    }

    public function test_admin_without_nonce_cannot_send_sms_through_post_publish()
    {
        $this->assertFalse($this->publishWithSmsFields($this->adminUserId, false));
    }

    public function test_admin_with_nonce_sends_sms_on_post_publish()
    {
        $this->assertTrue($this->publishWithSmsFields($this->adminUserId, true));
    }

    public function test_meta_box_is_hidden_from_users_who_cannot_send_sms()
    {
        global $wp_meta_boxes;

        wp_set_current_user(self::factory()->user->create(['role' => 'author']));
        $wp_meta_boxes = [];
        (new Notifications())->notification_meta_box();
        $this->assertEmpty($wp_meta_boxes['post']['normal']['high']['subscribe-meta-box'] ?? null);

        wp_set_current_user($this->adminUserId);
        $wp_meta_boxes = [];
        (new Notifications())->notification_meta_box();
        $this->assertNotEmpty($wp_meta_boxes['post']['normal']['high']['subscribe-meta-box'] ?? null);
    }
}
