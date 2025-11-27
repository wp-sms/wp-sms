<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class="wp-sms-notice wp-sms-notice--warning">
    <div>
        <p class="wp-sms-notice__title"><?php esc_html_e('Notice:', 'wp-sms') ?></p>
        <div class="wp-sms-notice__desc">
            <?php
            echo wp_kses_post(sprintf(
                /* translators: %s: URL to license activation page */
                __('This add-on does not have an active license, which means it cannot receive updates, including important security updates.
For uninterrupted access to updates and to keep your site secure, we strongly recommend activating a license. Activate your license <a href="%s">here</a>.', 'wp-sms'),
                esc_url(admin_url('admin.php?page=wp-sms-add-ons'))
            ));
            ?>
        </div>
    </div>
</div>
