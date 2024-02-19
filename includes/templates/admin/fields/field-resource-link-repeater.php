<div class="repeater">
    <div data-repeater-list="wpsms_settings[<?php echo esc_attr($args['id']) ?>]">
        <?php if (is_array($value) && count($value)) : ?>
            <?php foreach ($value as $data) : ?>
                <?php 
                    $resource_link_title    = isset($data['resource_link_title']) ? $data['resource_link_title'] : '';
                    $resource_link_url      = isset($data['resource_link_url']) ? $data['resource_link_url'] : '';
                ?>
                <div class="repeater-item" data-repeater-item>
                    <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc; overflow: hidden;">
                        <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                            <input placeholder="<?php _e('Resource Link Title', 'wp-sms') ?>" type="text" name="resource_link_title" style="display: block; width: 99%;" value="<?php echo esc_attr($resource_link_title) ?>" />
                        </div>
                        <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                            <input placeholder="<?php _e('Resource Link URL', 'wp-sms') ?>" type="text" name="resource_link_url" style="display: block; width: 99%;" value="<?php echo esc_attr($resource_link_url) ?>" />
                        </div>
                        <div>
                            <input type="button" value="<?php _e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc; overflow: hidden;">
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <input placeholder="<?php _e('Resource Link Title', 'wp-sms') ?>" type="text" name="resource_link_title" style="display: block; width: 99%;" />
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <input placeholder="<?php _e('Resource Link URL', 'wp-sms') ?>" type="text" name="resource_link_url" style="display: block; width: 99%;" />
                    </div>
                    <div>
                        <input type="button" value="<?php _e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="<?php _e('Add another resource link', 'wp-sms') ?>" class="button button-primary" data-repeater-create/>
    </div>
</div>