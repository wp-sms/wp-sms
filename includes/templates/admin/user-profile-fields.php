<h2><?php _e('SMS', 'wp-sms'); ?></h2>
<table class="form-table">
    <?php foreach ($fields as $field): ?>
        <tr>
            <th>
                <?php echo isset($field['title']) ? esc_html($field['title']) : '' ?>
            </th>
            <td>
                <?php echo isset($field['content']) ? $field['content'] : '' ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

