<script type="text/javascript">
	function openwin() {
		var url=document.form.wp_webservice.value;
		if(url==1) {
			document.location.href="<?php echo $sms_page['about']; ?>";
		}
	}
	
	jQuery(document).ready(function(){
		jQuery(".chosen-select").chosen();
		
		jQuery("#wps_reset").click(function(){
			if(confirm('<?php _e('Your Web service data will be deleted. Are you sure?', 'wp-sms'); ?>')) {
				return true;
			} else {
				return false;
			}
		});
	});
</script>

<?php do_action('wp_sms_settings_page'); ?>

<style>
	p.register{
		float: <?php echo is_rtl() == true? "right":"left"; ?>
	}
</style>

<div class="wrap">
	<?php include( dirname( __FILE__ ) . '/tabs.php' ); ?>
	<form method="post" action="options.php" name="form">
		<table class="form-table">
			<?php wp_nonce_field('update-options');?>
			<tr>
				<th><?php _e('Gateway', 'wp-sms'); ?>:</th>
				<td>
					<select name="wp_webservice" id="wp-webservice" class="chosen-select<?php echo is_rtl() == true? " chosen-rtl":""; ?>" onChange="javascript:openwin()">
						<option value=""><?php _e('Select your Web Service', 'wp-sms'); ?></option>
						
						<?php do_action('wp_sms_gateway_list'); ?>
						
						<optgroup label="<?php _e('Iran', 'wp-sms'); ?>">
							<option value="sarinapayamak" <?php selected(get_option('wp_webservice'), 'sarinapayamak'); ?>>Sarinapayamak.com</option>
							<option value="iransmspanel" <?php selected(get_option('wp_webservice'), 'iransmspanel'); ?>>iransmspanel.ir</option>
							<option value="chaparpanel" <?php selected(get_option('wp_webservice'), 'chaparpanel'); ?>>chaparpanel.ir</option>
							<option value="adpdigital" <?php selected(get_option('wp_webservice'), 'adpdigital'); ?>>adpdigital.com</option>
							<option value="hostiran" <?php selected(get_option('wp_webservice'), 'hostiran'); ?>>hostiran.net</option>
							<option value="farapayamak" <?php selected(get_option('wp_webservice'), 'farapayamak'); ?>>farapayamak.com</option>
							<option value="parandhost" <?php selected(get_option('wp_webservice'), 'parandhost'); ?>>parandhost.com</option>
							<option value="smsde" <?php selected(get_option('wp_webservice'), 'smsde'); ?>>smsde.ir</option>
							<option value="payamakde" <?php selected(get_option('wp_webservice'), 'payamakde'); ?>>payamakde.ir</option>
							<option value="panizsms" <?php selected(get_option('wp_webservice'), 'panizsms'); ?>>panizsms.com</option>
							<option value="sepehritc" <?php selected(get_option('wp_webservice'), 'sepehritc'); ?>>sepehritc.com</option>
							<option value="markazpayamak" <?php selected(get_option('wp_webservice'), 'markazpayamak'); ?>>markazpayamak.ir</option>
							<option value="payameavval" <?php selected(get_option('wp_webservice'), 'payameavval'); ?>>payameavval.com</option>
							<option value="smsclick" <?php selected(get_option('wp_webservice'), 'smsclick'); ?>>smsclick.ir</option>
							<option value="persiansms" <?php selected(get_option('wp_webservice'), 'persiansms'); ?>>persiansms.com</option>
							<option value="ariaideh" <?php selected(get_option('wp_webservice'), 'ariaideh'); ?>>ariaideh.com</option>
							<option value="sms_s" <?php selected(get_option('wp_webservice'), 'sms_s'); ?>>modiresms.com</option>
							<option value="sadat24" <?php selected(get_option('wp_webservice'), 'sadat24'); ?>>sadat24.ir</option>
							<option value="smscall" <?php selected(get_option('wp_webservice'), 'smscall'); ?>>smscall.ir</option>
							<option value="tablighsmsi" <?php selected(get_option('wp_webservice'), 'tablighsmsi'); ?>>tablighsmsi.com</option>
							<option value="paaz" <?php selected(get_option('wp_webservice'), 'paaz'); ?>>paaz.ir</option>
							<option value="textsms" <?php selected(get_option('wp_webservice'), 'textsms'); ?>>textsms.ir</option>
							<option value="jahanpayamak" <?php selected(get_option('wp_webservice'), 'jahanpayamak'); ?>>jahanpayamak.info</option>
							<option value="opilo" <?php selected(get_option('wp_webservice'), 'opilo'); ?>>opilo.com</option>
							<option value="barzinsms" <?php selected(get_option('wp_webservice'), 'barzinsms'); ?>>barzinsms.ir</option>
							<option value="smsmart" <?php selected(get_option('wp_webservice'), 'smsmart'); ?>>smsmart.ir</option>
							<option value="loginpanel" <?php selected(get_option('wp_webservice'), 'loginpanel'); ?>>loginpanel.ir</option>
							<option value="imencms" <?php selected(get_option('wp_webservice'), 'imencms'); ?>>imencms.com</option>
							<option value="tcisms" <?php selected(get_option('wp_webservice'), 'tcisms'); ?>>tcisms.com</option>
							<option value="caffeweb" <?php selected(get_option('wp_webservice'), 'caffeweb'); ?>>caffeweb.com</option>
							<option value="nasrpayam" <?php selected(get_option('wp_webservice'), 'nasrpayam'); ?>>nasrPayam.ir</option>
							<option value="smsbartar" <?php selected(get_option('wp_webservice'), 'smsbartar'); ?>>sms-bartar.com</option>
							<option value="fayasms" <?php selected(get_option('wp_webservice'), 'fayasms'); ?>>fayasms.ir</option>
							<option value="payamresan" <?php selected(get_option('wp_webservice'), 'payamresan'); ?>>payam-resan.com</option>
							<option value="mdpanel" <?php selected(get_option('wp_webservice'), 'mdpanel'); ?>>ippanel.com</option>
							<option value="payameroz" <?php selected(get_option('wp_webservice'), 'payameroz'); ?>>payameroz.ir</option>
							<option value="niazpardaz" <?php selected(get_option('wp_webservice'), 'niazpardaz'); ?>>niazpardaz.com</option>
							<option value="niazpardazcom" <?php selected(get_option('wp_webservice'), 'niazpardazcom'); ?>>niazpardaz.com - New</option>
							<option value="hisms" <?php selected(get_option('wp_webservice'), 'hisms'); ?>>hi-sms.ir</option>
							<option value="joghataysms" <?php selected(get_option('wp_webservice'), 'joghataysms'); ?>>joghataysms.ir</option>
							<option value="mediana" <?php selected(get_option('wp_webservice'), 'mediana'); ?>>mediana.ir</option>
							<option value="aradsms" <?php selected(get_option('wp_webservice'), 'aradsms'); ?>>arad-sms.ir</option>
							<option value="asiapayamak" <?php selected(get_option('wp_webservice'), 'asiapayamak'); ?>>payamak.asia</option>
							<option value="sharifpardazan" <?php selected(get_option('wp_webservice'), 'sharifpardazan'); ?>>2345.ir</option>
							<option value="sarabsms" <?php selected(get_option('wp_webservice'), 'sarabsms'); ?>>sarabsms.ir</option>
							<option value="ponishasms" <?php selected(get_option('wp_webservice'), 'ponishasms'); ?>>ponishasms.ir</option>
							<option value="payamakalmas" <?php selected(get_option('wp_webservice'), 'payamakalmas'); ?>>payamakalmas.ir</option>
							<option value="sms" <?php selected(get_option('wp_webservice'), 'sms'); ?>>sms.ir - Old</option>
							<option value="sms_new" <?php selected(get_option('wp_webservice'), 'sms_new'); ?>>sms.ir - New</option>
							<option value="popaksms" <?php selected(get_option('wp_webservice'), 'popaksms'); ?>>popaksms.ir</option>
							<option value="novin1sms" <?php selected(get_option('wp_webservice'), 'novin1sms'); ?>>novin1sms.ir</option>
							<option value="_500sms" <?php selected(get_option('wp_webservice'), '_500sms'); ?>>500sms.ir</option>
							<option value="matinsms" <?php selected(get_option('wp_webservice'), 'matinsms'); ?>>smspanel.mat-in.ir</option>
							<option value="iranspk" <?php selected(get_option('wp_webservice'), 'iranspk'); ?>>iranspk.ir</option>
							<option value="freepayamak" <?php selected(get_option('wp_webservice'), 'freepayamak'); ?>>freepayamak.ir</option>
							<option value="itpayamak" <?php selected(get_option('wp_webservice'), 'itpayamak'); ?>>itpayamak.ir</option>
							<option value="irsmsland" <?php selected(get_option('wp_webservice'), 'irsmsland'); ?>>irsmsland.ir</option>
							<option value="avalpayam" <?php selected(get_option('wp_webservice'), 'avalpayam'); ?>>avalpayam.com</option>
							<option value="smstoos" <?php selected(get_option('wp_webservice'), 'smstoos'); ?>>smstoos.ir</option>
							<option value="smsmaster" <?php selected(get_option('wp_webservice'), 'smsmaster'); ?>>smsmaster.ir</option>
							<option value="ssmss" <?php selected(get_option('wp_webservice'), 'ssmss'); ?>>ssmss.ir</option>
							<option value="isun" <?php selected(get_option('wp_webservice'), 'isun'); ?>>isun.company</option>
							<option value="idehpayam" <?php selected(get_option('wp_webservice'), 'idehpayam'); ?>>idehpayam.com</option>
							<option value="smsarak" <?php selected(get_option('wp_webservice'), 'smsarak'); ?>>smsarak.ir</option>
							<option value="novinpayamak" <?php selected(get_option('wp_webservice'), 'novinpayamak'); ?>>novinpayamak.com</option>
							<option value="melipayamak" <?php selected(get_option('wp_webservice'), 'melipayamak'); ?>>melipayamak.ir</option>
							<option value="postgah" <?php selected(get_option('wp_webservice'), 'postgah'); ?>>postgah.net</option>
							<option value="smsfa" <?php selected(get_option('wp_webservice'), 'smsfa'); ?>>smsfa.net</option>
							<option value="rayanbit" <?php selected(get_option('wp_webservice'), 'rayanbit'); ?>>rayanbit.net</option>
							<option value="smsmelli" <?php selected(get_option('wp_webservice'), 'smsmelli'); ?>>smsmelli.com</option>
							<option value="smsban" <?php selected(get_option('wp_webservice'), 'smsban'); ?>>smsban.ir</option>
							<option value="smsroo" <?php selected(get_option('wp_webservice'), 'smsroo'); ?>>smsroo.ir</option>
							<option value="navidsoft" <?php selected(get_option('wp_webservice'), 'navidsoft'); ?>>navid-soft.ir</option>
							<option value="afe" <?php selected(get_option('wp_webservice'), 'afe'); ?>>afe.ir</option>
							<option value="smshooshmand" <?php selected(get_option('wp_webservice'), 'smshooshmand'); ?>>smshooshmand.com</option>
							<option value="asanak" <?php selected(get_option('wp_webservice'), 'asanak'); ?>>asanak.ir</option>
							<option value="payamakpanel" <?php selected(get_option('wp_webservice'), 'payamakpanel'); ?>>payamak-panel.com</option>
							<option value="barmanpayamak" <?php selected(get_option('wp_webservice'), 'barmanpayamak'); ?>>barmanpayamak.ir</option>
							<option value="farazpayam" <?php selected(get_option('wp_webservice'), 'farazpayam'); ?>>farazpayam.com</option>
							<option value="_0098sms" <?php selected(get_option('wp_webservice'), '_0098sms'); ?>>0098sms.com</option>
							<option value="amansoft" <?php selected(get_option('wp_webservice'), 'amansoft'); ?>>amansoft.ir</option>
							<option value="faraed" <?php selected(get_option('wp_webservice'), 'faraed'); ?>>faraed.com</option>
							<option value="spadbs" <?php selected(get_option('wp_webservice'), 'spadbs'); ?>>spadsms.ir</option>
							<option value="bandarsms" <?php selected(get_option('wp_webservice'), 'bandarsms'); ?>>bandarit.ir</option>
							<option value="tgfsms" <?php selected(get_option('wp_webservice'), 'tgfsms'); ?>>tgfsms.ir</option>
							<option value="payamgah" <?php selected(get_option('wp_webservice'), 'payamgah'); ?>>payamgah.net</option>
							<option value="sabasms" <?php selected(get_option('wp_webservice'), 'sabasms'); ?>>sabasms.biz</option>
							<option value="chapargah" <?php selected(get_option('wp_webservice'), 'chapargah'); ?>>chapargah.ir</option>
							<option value="yashilsms" <?php selected(get_option('wp_webservice'), 'yashilsms'); ?>>yashil-sms.ir</option>
							<option value="ismsie" <?php selected(get_option('wp_webservice'), 'ismsie'); ?>>isms.ir</option>
							<option value="wifisms" <?php selected(get_option('wp_webservice'), 'wifisms'); ?>>wifisms.ir</option>
							<option value="razpayamak" <?php selected(get_option('wp_webservice'), 'razpayamak'); ?>>razpayamak.com</option>
							<option value="bestit" <?php selected(get_option('wp_webservice'), 'bestit'); ?>>bestit.co</option>
							<option value="pegahpayamak" <?php selected(get_option('wp_webservice'), 'pegahpayamak'); ?>>pegah-payamak.ir</option>
							<option value="adspanel" <?php selected(get_option('wp_webservice'), 'adspanel'); ?>>adspanel.ir</option>
							<option value="mydnspanel" <?php selected(get_option('wp_webservice'), 'mydnspanel'); ?>>mydnspanel.com</option>
							<option value="esms24" <?php selected(get_option('wp_webservice'), 'esms24'); ?>>esms24.ir</option>
							<option value="payamakaria" <?php selected(get_option('wp_webservice'), 'payamakaria'); ?>>payamakaria.ir</option>
							<option value="pichakhost" <?php selected(get_option('wp_webservice'), 'pichakhost'); ?>>sitralweb.com</option>
							<option value="tsms" <?php selected(get_option('wp_webservice'), 'tsms'); ?>>tsms.ir</option>
							<option value="parsasms" <?php selected(get_option('wp_webservice'), 'parsasms'); ?>>parsasms.com</option>
							<option value="modiranweb" <?php selected(get_option('wp_webservice'), 'modiranweb'); ?>>modiranweb.net</option>
							<option value="smsline" <?php selected(get_option('wp_webservice'), 'smsline'); ?>>smsline.ir</option>
							<option value="iransms" <?php selected(get_option('wp_webservice'), 'iransms'); ?>>iransms.co</option>
							<option value="arkapayamak" <?php selected(get_option('wp_webservice'), 'arkapayamak'); ?>>arkapayamak.ir</option>
							<option value="smsservice" <?php selected(get_option('wp_webservice'), 'smsservice'); ?>>smsservice.ir</option>
						</optgroup>
						
						<optgroup label="<?php _e('Brazil', 'wp-sms'); ?>">
							<option value="sonoratecnologia" <?php selected(get_option('wp_webservice'), 'sonoratecnologia'); ?>>sonoratecnologia.com.br</option>
						</optgroup>

						<optgroup label="<?php _e('Turkey', 'wp-sms'); ?>">
							<option value="bulutfon" <?php selected(get_option('wp_webservice'), 'bulutfon'); ?>>bulutfon.com</option>
						</optgroup>
						
						<optgroup label="<?php _e('Spania', 'wp-sms'); ?>">
							<option value="afilnet" <?php selected(get_option('wp_webservice'), 'afilnet'); ?>>afilnet.com</option>
							<option value="labsmobile" <?php selected(get_option('wp_webservice'), 'labsmobile'); ?>>labsmobile.com</option>
						</optgroup>
						
						<optgroup label="<?php _e('German', 'wp-sms'); ?>">
							<option value="sms77" <?php selected(get_option('wp_webservice'), 'sms77'); ?>>sms77.de</option>
						</optgroup>
						
						<optgroup label="<?php _e('New Zealand', 'wp-sms'); ?>">
							<option value="unisender" <?php selected(get_option('wp_webservice'), 'unisender'); ?>>unisender.com</option>
						</optgroup>
						
						<optgroup label="<?php _e('Austria', 'wp-sms'); ?>">
							<option value="smsgateway" <?php selected(get_option('wp_webservice'), 'smsgateway'); ?>>sms-gateway.at</option>
						</optgroup>
						
						<optgroup label="<?php _e('Pakistan', 'wp-sms'); ?>">
							<option value="difaan" <?php selected(get_option('wp_webservice'), 'difaan'); ?>>difaan</option>
						</optgroup>
						
						<optgroup label="<?php _e('Indian', 'wp-sms'); ?>">
							<option value="shreesms" <?php selected(get_option('wp_webservice'), 'shreesms'); ?>>shreesms.net</option>
						</optgroup>
						
						<optgroup label="<?php _e('Italian', 'wp-sms'); ?>">
							<option value="dot4all" <?php selected(get_option('wp_webservice'), 'dot4all'); ?>>dot4all.it</option>
							<option value="smshosting" <?php selected(get_option('wp_webservice'), 'smshosting'); ?>>smshosting.it</option>
						</optgroup>
						
						<optgroup label="<?php _e('Polish', 'wp-sms'); ?>">
							<option value="smsapi" <?php selected(get_option('wp_webservice'), 'smsapi'); ?>>smsapi.pl</option>
						</optgroup>
						
						<optgroup label="<?php _e('Arabia', 'wp-sms'); ?>">
							<option value="gateway" <?php selected(get_option('wp_webservice'), 'gateway'); ?>>gateway.sa</option>
						</optgroup>
						
						<optgroup label="<?php _e('Global', 'wp-sms'); ?>">
							<option value="smsglobal" <?php selected(get_option('wp_webservice'), 'smsglobal'); ?>>smsglobal.com</option>
							<option value="bearsms" <?php selected(get_option('wp_webservice'), 'bearsms'); ?>>bearsms.com</option>
							<option value="smss" <?php selected(get_option('wp_webservice'), 'smss'); ?>>smss.co.il</option>
							<option value="mtarget" <?php selected(get_option('wp_webservice'), 'mtarget'); ?>>mtarget.fr</option>
						</optgroup>
						
						<!--Option information-->
						<option value="1" id="option-information"><?php _e('For more information about adding Web Service', 'wp-sms'); ?></option>
						<!--Option information-->
					</select>
					
					<?php if(get_option('wp_webservice')) { ?>
						<a href="admin.php?page=wp-sms-settings&tab=web-service&action=reset" class="button" id="wps_reset"><?php _e('Reset', 'wp-sms'); ?></a>
					<?php } ?>
					
					<?php do_action('wp_sms_after_gateway'); ?>
					
					<?php if(!get_option('wp_webservice')) { ?>
					<p class="description"><?php echo sprintf(__('If your gateway is not on the top list, <a href="%s">click here.</a>', 'wp-sms'), $sms_page['about']); ?></p>
					<?php } ?>
				</td>
			</tr>

			<?php if(get_option('wp_webservice')) { ?>
			<tr>
				<th><?php _e('Username', 'wp-sms'); ?>:</th>
				<td>
					<input type="text" dir="ltr" name="wp_username" value="<?php echo get_option('wp_username'); ?>"/>
					<p class="description"><?php echo sprintf(__('Your username in <a href="%s">%s</a>', 'wp-sms'), $sms->tariff, get_option('wp_webservice')); ?></p>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Password', 'wp-sms'); ?>:</th>
				<td>
					<input type="password" dir="ltr" name="wp_password" value="<?php echo get_option('wp_password'); ?>"/>
					<p class="description"><?php echo sprintf(__('Your password in <a href="%s">%s</a>', 'wp-sms'), $sms->tariff, get_option('wp_webservice')); ?></p>
				</td>
			</tr>
			
			<?php if($sms->has_key) { ?>
			<tr>
				<th><?php _e('API/Key', 'wp-sms'); ?>:</th>
				<td>
					<input type="text" dir="ltr" name="wps_key" value="<?php echo get_option('wps_key'); ?>"/>
					<p class="description"><?php echo sprintf(__('Your API Key in <a href="%s">%s</a>', 'wp-sms'), $sms->tariff, get_option('wp_webservice')); ?></p>
				</td>
			</tr>
			<?php } ?>

			<tr>
				<th><?php _e('Number', 'wp-sms'); ?>:</th>
				<td>
					<input type="text" dir="ltr" name="wp_number" value="<?php echo get_option('wp_number'); ?>"/>
					<p class="description"><?php echo sprintf(__('Your SMS sender number in <a href="%s">%s</a>', 'wp-sms'), $sms->tariff, get_option('wp_webservice')); ?></p>
				</td>
			</tr>
			
			<?php if($sms->GetCredit() > 0) { ?>
			<tr>
				<th><?php _e('Status', 'wp-sms'); ?>:</th>
				<td class="wpsms-has-credit">
					<span class="dashicons dashicons-yes"></span><span style="font-weight: bold;"><?php _e('Active', 'wp-sms'); ?></span>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Credit', 'wp-sms'); ?>:</th>
				<td>
					<?php global $sms; echo $sms->GetCredit() . " " . $sms->unit; ?>
				</td>
			</tr>
			<?php } else { ?>
			<tr>
				<th><?php _e('Status', 'wp-sms'); ?>:</th>
				<td class="wpsms-no-credit">
					<span class="dashicons dashicons-no"></span><span style="font-weight: bold;"><?php _e('Deactive', 'wp-sms'); ?></span>
				</td>
			</tr>
			<?php } ?>
			<?php } ?>
			
			<tr>
				<td>
					<p class="submit">
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="page_options" value="wp_webservice,wp_username,wp_password,wps_key,wp_number" />
						<input type="submit" class="button-primary" name="Submit" value="<?php _e('Update', 'wp-sms'); ?>" />
					</p>
				</td>
			</tr>
		</table>
	</form>	
</div>