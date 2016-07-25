<?php

class TP_Messages{

	private $parent;

	private $message_data;
	private $message;
	private $from;

	public function __construct($parent){
		$this->parent = $parent;
		$this->message_data = array();
	}

	public function test(){
		$data = array(
			'recipients' => json_encode($this->message_data),
			'message'	 => $this->message,
			'from'		 =>	$this->from,
		);
		$result = $this->parent->request('messages/merge', $data);
		return $result ? $result : null;	
	}

	public function send(){
		$data = array(
			'recipients' => json_encode($this->message_data),
			'message'	 => $this->message,
			'from'		 =>	$this->from,
		);
		$result = $this->parent->request('messages/send', $data);
		return $result ? $result : null;	
	}

	public function add_recipient($phone_number, $merge_data){
		$recipient = array('phone_number' => $phone_number, 'merge' => $merge_data);
		$this->message_data[] = $recipient;
	}

	public function list_recipients($type = 'array'){
		if($type === 'json'){
			return json_encode($this->message_data);
		}else if($type === 'array'){
			return print_r($this->message_data, true);
		}
	}

	public function set_message($message){
		$this->message = $message;
	}

	public function get_message(){
		return $this->message;
	}

	public function set_from($from){
		$this->from = $from;
	}

	public function get_from(){
		return $this->from;
	}

	public function reset(){
		$this->message = '';
		$this->from = '';
		$this->message_data = array();
	}

	public function clear_recipients(){
		$this->message_data = array();
	}

}