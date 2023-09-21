<p><?php echo sprintf(__('New incoming message received at %s. You can check your admin panel to do more actions!', 'wp-sms'), $received_at); ?></p>

<h3><?php echo sprintf(__('Message from %s', 'wp-sms'), $sender); ?></h3>
<code><?php echo $message; ?></code>

<?php if ($has_command): ?>
    <ul>
        <li><span class="title"><?php _e('Command', 'wp-sms'); ?>:</span><?php echo $command_name; ?></li>
        <li><span class="title"><?php _e('Action', 'wp-sms'); ?>:</span><?php echo $action_message; ?></li>
    </ul>
<?php endif; ?>