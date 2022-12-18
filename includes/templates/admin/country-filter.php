<div class="alignleft actions bulkactions">
    <select name="country_code">
        <option value=""><?php _e('Filter by country', 'wp-sms'); ?></option>
        <?php foreach ($countries as $country) : ?>
            <option value="<?php echo esc_attr($country['code']); ?>" <?php echo selected($selected, $country['code']); ?>><?php echo esc_html($country['name']) . ' (' . esc_html($country['total']) . ' subscribers)'; ?></option>
        <?php endforeach; ?>
    </select>
    <input type="submit" name="" id="post-query-submit" class="button" value="<?php _e('Filter', 'wp-sms'); ?>">
</div>