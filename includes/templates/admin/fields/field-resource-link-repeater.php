<div class="repeater">
    <div data-repeater-list="wpsms_settings[<?php echo esc_attr($args['id']) ?>]">
        <?php if (is_array($value) && count($value)) : ?>
            <?php foreach ($value as $data) : ?>
                <?php
                    $chatbox_link_title    = isset($data['chatbox_link_title']) ? $data['chatbox_link_title'] : '';
                    $chatbox_link_url      = isset($data['chatbox_link_url']) ? $data['chatbox_link_url'] : '';
                ?>
                <div class="repeater-item" data-repeater-item>
                    <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc; overflow: hidden;">
                        <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                            <input placeholder="<?php esc_html_e('Troubleshooting Common Issues', 'wp-sms') ?>" type="text" name="chatbox_link_title" style="display: block; width: 99%;" value="<?php echo esc_attr($chatbox_link_title) ?>" />
                            <p class="description"><?php esc_html_e('Add titles and URLs for your resource links, e.g., \'FAQs\' or \'Contact Us\'', 'wp-sms') ?></p>
                        </div>
                        <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                            <input placeholder="<?php echo esc_url(site_url('troubleshooting')); ?>" type="text" name="chatbox_link_url" style="display: block; width: 99%;" value="<?php echo esc_url($chatbox_link_url) ?>" />
                        </div>
                        <div>
                            <input type="button" value="<?php esc_html_e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc; overflow: hidden;">
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <input placeholder="<?php esc_html_e('Troubleshooting Common Issues', 'wp-sms') ?>" type="text" name="chatbox_link_title" style="display: block; width: 99%;" />
                        <p class="description"><?php esc_html_e('Add titles and URLs for your resource links, e.g., \'FAQs\' or \'Contact Us\'', 'wp-sms') ?></p>
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <input placeholder="<?php echo esc_url(site_url('troubleshooting')); ?>" type="text" name="chatbox_link_url" style="display: block; width: 99%;" />
                    </div>
                    <div>
                        <input type="button" value="<?php esc_html_e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="<?php esc_html_e('Add another link', 'wp-sms') ?>" class="button button-primary" data-repeater-create/>
    </div>
</div>