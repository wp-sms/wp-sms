<?php if(get_option('wp_subscribes_status')) { ?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$("#wpsms-submit").click(function() {
			$("#wpsms-result").hide();
			subscriber = new Array();
			subscriber['name'] = $("#wpsms-name").val();
			subscriber['mobile'] = $("#wpsms-mobile").val();
			subscriber['groups'] = $("#wpsms-groups").val();
			subscriber['type'] = $('input[name=subscribe_type]:checked').val();
			
			$("#wpsms-subscribe").ajaxStart(function(){
				$("#wpsms-submit").attr('disabled', 'disabled');
				$("#wpsms-submit").text("<?php _e('Loading...', 'wp-sms'); ?>");
			});
			
			$("#wpsms-subscribe").ajaxComplete(function(){
				$("#wpsms-submit").removeAttr('disabled');
				$("#wpsms-submit").text("<?php _e('Subscribe', 'wp-sms'); ?>");
			});
			
			$.get("<?php echo WP_SMS_DIR_PLUGIN; ?>includes/ajax/wp-sms-subscribe.php", {name:subscriber['name'], mobile:subscriber['mobile'], group:subscriber['groups'], type:subscriber['type']}, function(data, status){
				var response = $.parseJSON(data);
				
				if(response.status == 'error') {
					$("#wpsms-result").fadeIn();
					$("#wpsms-result").html('<span class="wpsms-message-error">' + response.response + '</div>');
				}
				
				if(response.status == 'success') {
					$("#wpsms-result").fadeIn();
					$("#wpsms-step-1").hide();
					$("#wpsms-result").html('<span class="wpsms-message-success">' + response.response + '</div>');
				}
				
				if(response.action == 'activation') {
					$("#wpsms-step-2").show();
				}
			});
		});
		
		<?php if(get_option('wp_subscribes_activation')) { ?>
		$("#activation").on('click', function() {
			$("#wpsms-result").hide();
			subscriber['activation'] = $("#wpsms-ativation-code").val();
			
			$("#wpsms-subscribe").ajaxStart(function(){
				$("#activation").attr('disabled', 'disabled');
				$("#activation").text("<?php _e('Loading...', 'wp-sms'); ?>");
			});
			
			$("#wpsms-subscribe").ajaxComplete(function(){
				$("#activation").removeAttr('disabled');
				$("#activation").text("<?php _e('Activation', 'wp-sms'); ?>");
			});
			
			$.get("<?php echo WP_SMS_DIR_PLUGIN; ?>includes/ajax/wp-sms-subscribe-activation.php", {mobile:subscriber['mobile'], activation:subscriber['activation']}, function(data, status){
				var response = $.parseJSON(data);
				
				if(response.status == 'error') {
					$("#wpsms-result").fadeIn();
					$("#wpsms-result").html('<span class="wpsms-message-error">' + response.response + '</div>');
				}
				
				if(response.status == 'success') {
					$("#wpsms-result").fadeIn();
					$("#wpsms-step-2").hide();
					$("#wpsms-result").html('<span class="wpsms-message-success">' + response.response + '</div>');
				}
			});
		});
		<?php } ?>
	});
</script>
<div id="wpsms-subscribe">
	<div id="wpsms-result"></div>
	<div id="wpsms-step-1">
		<p><?php echo $description; ?></p>
		<div class="wpsms-subscribe-form">
			<label><?php _e('Your name', 'wp-sms'); ?>:</label>
			<input id="wpsms-name" type="text" placeholder="<?php _e('Your name', 'wp-sms'); ?>" class="wpsms-input"/>
		</div>
		
		<div class="wpsms-subscribe-form">
			<label><?php _e('Your mobile', 'wp-sms'); ?>:</label>
			<input id="wpsms-mobile" type="text" placeholder="<?php echo get_option('wps_mnt_place_holder'); ?>" class="wpsms-input"/>
		</div>
		
		<?php if($show_group) { ?>
		<div class="wpsms-subscribe-form">
			<label><?php _e('Group', 'wp-sms'); ?>:</label>
			<select id="wpsms-groups" class="wpsms-input">
				<?php foreach($get_group_result as $items): ?>
				<option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php } ?>
		
		<div class="wpsms-subscribe-form">
			<label>
				<input type="radio" name="subscribe_type" id="wpsms-type-subscribe" value="subscribe" checked="checked"/>
				<?php _e('Subscribe', 'wp-sms'); ?>
			</label>

			<label>
				<input type="radio" name="subscribe_type" id="wpsms-type-unsubscribe" value="unsubscribe"/>
				<?php _e('Unsubscribe', 'wp-sms'); ?>
			</label>
		</div>
		
		<button class="wpsms-button" id="wpsms-submit"><?php _e('Subscribe', 'wp-sms'); ?></button>
	</div>
	
	<div id="wpsms-step-2">
		<div class="wpsms-subscribe-form">
			<label><?php _e('Activation code:', 'wp-sms'); ?></label>
			<input type="text" id="wpsms-ativation-code" placeholder="<?php _e('Activation code:', 'wp-sms'); ?>" class="wpsms-input"/>
		</div>
		
		<button class="wpsms-button" id="activation"><?php _e('Activation', 'wp-sms'); ?></button>
	</div>
</div>
<?php } else { ?>
<div id="wpsms-subscribe">
	<div class="wpsms-deactive">
		<?php _e('Subscribe is Deactive!', 'wp-sms'); ?>
	</div>
</div>
<?php } ?>