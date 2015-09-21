<table class="form-table">
	<tr>
		<th><label for="mobile"><?php _e('Mobile', 'wp-sms'); ?></label></th>
		<td>
			<input type="text" class="regular-text" name="mobile" value="<?php echo esc_attr(get_the_author_meta('mobile', $user->ID)); ?>" id="mobile" />
			<span class="description"><?php _e('User mobile number.', 'wp-sms'); ?></span>
		</td>
	</tr>
</table>