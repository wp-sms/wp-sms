<?php

class TP_Account{

	private $parent;

	public function __construct($parent){
		$this->parent = $parent;
	}

	public function get_name(){
		$result = $this->parent->request('account/get/name');
		return $result ? $this->parent->toString($result) : null;
	}

	public function get_email(){
		$result = $this->parent->request('account/get/email');
		return $result ? $this->parent->toString($result) : null;
	}

	public function get_credits(){
		$result = $this->parent->request('account/get/credits');
		$credits = $result[0];
		return $result ? (int) $credits['credits'] : null;
	}

	public function get_credit_threshold(){
		$result = $this->parent->request('account/get/credit-threshold');
		return $result ? (int) $this->parent->toString($result) : null;
	}	

	public function get_default_from(){
		$result = $this->parent->request('account/get/default-from');
		return $result ? $this->parent->toString($result) : null;
	}

	public function get_international(){
		$result = $this->parent->request('account/get/international');
		return $result ? (boolean) $this->parent->toString($result) : null;
	}

	public function get_country_code(){
		$result = $this->parent->request('account/get/country-code');
		return $result ? $this->parent->toString($result) : null;	
	}	

	public function login($email, $password){
		$data = array(
			'email' => $email,
			'password' => md5($password),
		);
		$result = $this->parent->request('account/login', $data);
		return $result ? $this->parent->toString($result) : null;
	}

}