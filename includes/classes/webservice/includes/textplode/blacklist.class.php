<?php

class TP_Blacklist{

	private $parent;

	public function __construct($parent){
		$this->parent = $parent;
	}

	public function get_all(){
		$result = $this->parent->request('blacklist/get/all');
		return $result ? $result : null;
	}

	public function add($phone_number){
		$data = array(
			'phone_number' => $phone_number,
		);
		$result = $this->parent->request('blacklist/add');
		return $result ? $result : null;
	}

	public function remove($phone_number){
		$data = array(
			'phone_number' => $phone_number,
		);
		$result = $this->parent->request('blacklist/remove');
		return $result ? $result : null;
	}

}