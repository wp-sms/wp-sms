<h2><?php _e('SMS', 'wp-sms'); ?></h2>
<table class="form-table">
    <?php foreach ($fields as $field): ?>
        <tr>
            <th>
                <?= isset($field['title']) ? $field['title'] : '' ?>
            </th>
            <td>
                <?= isset($field['content']) ? $field['content'] : '' ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

