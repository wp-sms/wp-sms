<h2><?php esc_html_e('SMS', 'wp-sms'); ?></h2>
<table class="form-table">
    <?php foreach ($fields as $field): ?>
        <tr>
            <th>
                <?php echo isset($field['title']) ? esc_html($field['title']) : '' ?>
            </th>
            <td>
                <?php echo isset($field['content']) ? wp_kses($field['content'], ['input' => ['class' => [], 'type' => [], 'name' => [], 'value' => [], 'checked' => []]]) : '' ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

