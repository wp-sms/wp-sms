<script type="text/javascript">
	var boxId2 = 'wp_get_message';
	var counter = 'wp_counter';
	var part = 'wp_part';
	var max = 'wp_max';
	function charLeft2() {
		checkSMSLength(boxId2, counter, part, max);
	}
	
	jQuery(document).ready(function(){
		jQuery(".wpsms-value").hide();
		jQuery(".wpsms-group").show();
		
		jQuery("select#select_sender").change(function(){
			var get_method = "";
			jQuery("select#select_sender option:selected").each(
				function(){
					get_method += jQuery(this).attr('id');
				}
			);
			if(get_method == 'wp_subscribe_username') {
				jQuery(".wpsms-value").hide();
				jQuery(".wpsms-group").fadeIn();
			} else if(get_method == 'wp_users') {
				jQuery(".wpsms-value").hide();
				jQuery(".wpsms-users").fadeIn();
			} else if(get_method == 'wp_tellephone') {
				jQuery(".wpsms-value").hide();
				jQuery(".wpsms-numbers").fadeIn();
				jQuery("#wp_get_number").focus();
			}
		});
		
		charLeft2();
		jQuery("#" + boxId2).bind('keyup', function() {
			charLeft2();
		});
		jQuery("#" + boxId2).bind('keydown', function() {
			charLeft2();
		});
		jQuery("#" + boxId2).bind('paste', function(e) {
			charLeft2();
		});
	});
</script>

<div class="wrap">
	<h2><?php _e('Send SMS', 'wp-sms'); ?></h2>
	<form method="post" action="">
		<table class="form-table">
			<?php wp_nonce_field('update-options');?>
			<tr>
				<th><h3><?php _e('Send SMS', 'wp-sms'); ?></h4></th>
			</tr>
			<tr>
				<td><?php _e('Send from', 'wp-sms'); ?>:</td>
				<td><?php echo $this->sms->from; ?></td>
			</tr>
			<tr>
				<td><?php _e('Send to', 'wp-sms'); ?>:</td>
				<td>
					<select name="wp_send_to" id="select_sender">
						<option value="wp_subscribe_username" id="wp_subscribe_username"><?php _e('Subscribe users', 'wp-sms'); ?></option>
						<option value="wp_users" id="wp_users"><?php _e('Wordpress Users', 'wp-sms'); ?></option>
						<option value="wp_tellephone" id="wp_tellephone"><?php _e('Number(s)', 'wp-sms'); ?></option>
					</select>
					
					<select name="wpsms_group_name" class="wpsms-value wpsms-group">
						<option value="all">
						<?php
							global $wpdb, $table_prefix;
							$username_active = $wpdb->query("SELECT * FROM {$table_prefix}sms_subscribes WHERE status = '1'");
							echo sprintf(__('All (%s subscribers active)', 'wp-sms'), $username_active);
						?>
						</option>
						<?php foreach($get_group_result as $items): ?>
						<option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
						<?php endforeach; ?>
					</select>
					
					<span class="wpsms-value wpsms-users">
						<span><?php echo sprintf(__('<b>%s</b> Users have mobile number.', 'wp-sms'), count($get_users_mobile)); ?></span>
					</span>
					
					<span class="wpsms-value wpsms-numbers">
						<input type="text" style="direction:ltr;" id="wp_get_number" name="wp_get_number" value=""/>
						<span style="font-size: 10px"><?php echo sprintf(__('For example: <code>%s</code>', 'wp-sms'), $this->sms->validateNumber); ?></span>
					</span>
				</td>
			</tr>
			
			<tr>
				<td><?php _e('SMS', 'wp-sms'); ?>:</td>
				<td>
					<textarea name="wp_get_message" id="wp_get_message" style="width:350px; height: 200px; direction:ltr;"></textarea><br />
					<?php _e('The remaining words', 'wp-sms'); ?>: <span id="wp_counter" class="number"></span>/<span id="wp_max" class="number"></span><br />
					<span id="wp_part" class="number"></span> <?php _e('SMS', 'wp-sms'); ?><br />
					<p class="number">
						<?php echo __('Your credit', 'wp-sms') . ': ' . $this->sms->GetCredit() . ' ' . $this->sms->unit; ?>
					</p>
				</td>
			</tr>
			<?php if($this->sms->flash == "enable") { ?>
			<tr>
				<td><?php _e('Send a Flash', 'wp-sms'); ?>:</td>
				<td>
					<input type="radio" id="flash_yes" name="wp_flash" value="true"/>
					<label for="flash_yes"><?php _e('Yes', 'wp-sms'); ?></label>
					<input type="radio" id="flash_no" name="wp_flash" value="false" checked="checked"/>
					<label for="flash_no"><?php _e('No', 'wp-sms'); ?></label>
					<br />
					<p class="description"><?php _e('Flash is possible to send messages without being asked, opens', 'wp-sms'); ?></p>
				</td>
			</tr>
			<?php } ?>
			<tr>
				<td>
					<p class="submit">
						<input type="submit" class="button-primary" name="SendSMS" value="<?php _e('Send SMS', 'wp-sms'); ?>" />
					</p>
				</td>
			</tr>
		</table>
	</form>
</div>