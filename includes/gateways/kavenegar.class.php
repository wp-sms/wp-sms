<?php
class kavenegar extends WP_SMS {
	const APIPATH = "http://api.kavenegar.com/v1/%s/%s/%s.json/";
	private function get_path($method, $base = 'sms')
    {
        return sprintf(self::APIPATH, trim($this->has_key), $base, $method);
    }		
	public function __construct() {
		parent::__construct();
		$this->validateNumber = "+xxxxxxxxxxxxx";
		$this->has_key=true;
	}	
	public function SendSMS() {	
		$this->from = apply_filters('wp_sms_from', $this->from);
		$this->to = apply_filters('wp_sms_to', $this->to);
		$this->msg = apply_filters('wp_sms_msg', $this->msg);		
		$to = implode($this->to, ",");
		$msg = urlencode($this->msg);	
		$path  = $this->get_path("send");	
		$result = @file_get_contents($path."?receptor=".$to."&sender=".$this->from."&message=".$msg);
		if (false !== $result){
			$result = json_decode($result);
			if($result){
				if ($result->return->status == 200) {
					$this->InsertToDB($this->from, $this->msg, $this->to);
					do_action('wp_sms_send', $result);	
					return $result;
				}
			}				
		}	
		return new WP_Error( 'send-sms', $result );
	}	
	public function GetCredit() {
		$remaincredit=0;
		$path = $this->get_path("info", "account");
		$result = @file_get_contents($path);
		if (false !== $result){
			$json_response = json_decode($result);
			if($json_response){
				if ($json_response->return->status == 200) {
					$remaincredit=$json_response->entries->remaincredit;
				}
			}				
		}
		return $remaincredit;
	} 
}