<?php

class TP_Groups{

	private $parent;

	public function __construct($parent){
		$this->parent = $parent;
	}

	public function get_all(){
		$result = $this->parent->request('groups/get/all');
		$id = $result[0];

		if(!$id['id']){
			return null;
		}

		return $result ? $result : null;
	}

	public function get($id){
		$data = array(
			'id' => $id,
		);
		$result = $this->parent->request('groups/get/group', $data);
		$result = $result[0];
		return $result ? $result : null;
	}

	public function add($name){
		$data = array(
			'name' => $name,
		);
		$result = $this->parent->request('groups/add', $data);
		return $result ? $result : null;	
	}

	public function remove($id){
		$data = array(
			'id' => $id,
		);
		$result = $this->parent->request('groups/remove', $data);
		return $result ? $result : null;	
	}

}