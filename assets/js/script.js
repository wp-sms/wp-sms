jQuery(document).ready(function($) {
	$("#wpsms-subscribe #wpsms-submit").click(function() {
		$("#wpsms-result").hide();

		subscriber = new Array();
		subscriber['name'] = $("#wpsms-name").val();
		subscriber['mobile'] = $("#wpsms-mobile").val();
		subscriber['groups'] = $("#wpsms-groups").val();
		subscriber['type'] = $('input[name=subscribe_type]:checked').val();
		
		$("#wpsms-subscribe").ajaxStart(function(){
			$("#wpsms-submit").attr('disabled', 'disabled');
			$("#wpsms-submit").text("Loading...");
		});
		
		$("#wpsms-subscribe").ajaxComplete(function(){
			$("#wpsms-submit").removeAttr('disabled');
			$("#wpsms-submit").text("Subscribe");
		});

		$.post(ajax_object.ajaxurl, {
			widget_id: $('#wpsms-widget-id').attr('value'),
			action: 'ajax_action',
			name:subscriber['name'],
			mobile:subscriber['mobile'],
			group:subscriber['groups'],
			type:subscriber['type'],
			nonce: ajax_object.nonce
		}, function(data, status) {
			
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
});