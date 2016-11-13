<?php

class TP_Contacts{

	private $parent;

	public function __construct($parent){
		$this->parent = $parent;
	}

	public function get_all(){
		$result = $this->parent->request('contacts/get/all');
		return $result ? $result : null;
	}

	public function get($id){
		$data = array(
			'id' => $id,
		);
		$result = $this->parent->request('contacts/get/contact', $data);
		return $result ? $result : null;
	}

	public function get_by_number($phone_number){
		$data = array(
			'phone_number' => $phone_number,
		);
		$result = $this->parent->request('contacts/get/contact-by-number', $data);
		return $result ? $result : null;
	}

	public function add($first_name, $last_name, $phone_number, $group_id = 0){

		if(substr($phone_number, 0, 1) == '0')
			$phone_number = $this->parent->account->get_country_code() . substr($phone_number, 1);

		$data = array('first_name' => $first_name, 'last_name' => $last_name, 'phone_number' => $phone_number, 'group' => $group_id);

		$method = $this->get_by_number($phone_number) ? 'edit' : 'add';
		$result = $this->parent->request('contacts/' . $method, $data);

		return $result ? $result : null;
	}

	public function remove($phone_number){
		$data = array(
			'phone_number' => $phone_number,
		);
		$result = $this->parent->request('contacts/remove', $data);
		return $result ? $result : null;		
	}

}