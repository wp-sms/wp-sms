<?php

require_once('account.class.php');
require_once('activity.class.php');
require_once('blacklist.class.php');
require_once('contacts.class.php');
require_once('groups.class.php');
require_once('messages.class.php');

class Textplode{

	private $version;
	private $base_url;
	private $api_key;

	private $error_code;
	private $error_message;

	public $account;
	public $activity;
	public $blacklist;
	public $contacts;
	public $groups;
	public $messages;

	public function __construct($api_key = null){
		$this->api_key = $api_key;
		$this->version = 'v3';
		$this->base_url = 'http://api.textplode.com/' . $this->version . '/';

		$this->account 		= new TP_Account($this);
		$this->activity 	= new TP_Activity($this);
		$this->blacklist 	= new TP_Blacklist($this);
		$this->contacts 	= new TP_Contacts($this);
		$this->groups 		= new TP_Groups($this);
		$this->messages 	= new TP_Messages($this);
	}

	public function get_version(){
		return $this->version;
	}

	private function set_last_error($code, $message){
		$this->error_code = $code;
		$this->error_message = $message;
	}

	public function get_last_error(){
		return array('code' => $this->error_code, 'message' => $this->error_message);
	}

	public function toString($array){
		while(true){
			if(is_array($array)){
				$values = array_values($array);
				$values = $values[0];
			}else break;
		}
		return $array;
	}

	public function request($method, $data = array()){
		$data = array_merge($data, array('api_key' => $this->api_key));

		// print_r($data);

		$curl = curl_init($this->base_url . $method);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
		$resp = curl_exec($curl);
		// echo $method . ': ' . $resp . '<br/>';
		
		return $this->output($resp);
	}

	public function get_service_status(){
		$result = $this->request('/service/status');
		return $result ? $result : null;
	}

	public function get_service_messages($data){
		$result = $this->request('/service/messages', $data);
		return $result ? $result : null;
	}

	private function output($data){
		$data = json_decode($data, true);

		if($data['errors']['errorCode'] != 200){
			$this->set_last_error($data['errors']['errorCode'], $data['errors']['errorMessage']);
			return '';
		}else{
			return $data['data'];
		}
	}

}