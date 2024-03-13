<div class="alignleft actions bulkactions">
    <select name="group_id">
        <option value=""><?php esc_html_e('Filter by group', 'wp-sms'); ?></option>
        <?php foreach ($groups as $group) : ?>
            <option value="<?php echo esc_attr($group->ID); ?>" <?php echo selected($selected, $group->ID); ?>><?php echo esc_html($group->name); ?></option>
        <?php endforeach; ?>
    </select>
    <input type="submit" name="" id="post-query-submit" class="button" value="<?php esc_html_e('Filter', 'wp-sms'); ?>">
</div>