<?php namespace sso;
class BusinessException extends \Exception {
	private $data = array();
	
	public function addData($data, $key = NULL) {
		if ($key === NULL) {
			$this->data[] = $data;
		} else {
			$this->data[$key] = $data;
		}
	}
	
	public function getData($key = NULL) {
		if ($key === NULL) {
			return $this->data;
		} 
		return $this->data[$key];
	}
	
}