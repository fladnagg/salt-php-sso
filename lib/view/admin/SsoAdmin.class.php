<?php namespace sso;

use salt\Base;
use salt\DBResult;

abstract class SsoAdmin {
	
	public $object = NULL;
	public $title = NULL;
	public $searchFields = NULL;
	public $modifiableFields = NULL;
	public $newFields = NULL;
	public $extraFields = NULL;
	public $hideFields = array();
	public $tooltipFields = array();
	
	public $errors = array();
	public $messages = array();
	
	abstract public function displayName(Base $obj);
	abstract public function createFrom(array $data);
	/**
	 * 
	 * @param Base $obj
	 * @param array $data
	 * @return Base
	 */
	abstract public function updateFrom(Base $obj, array $data);
	
	public function isFemaleName() {
		return FALSE;
	}
	
	
	public function relatedObjectsDeleteQueries(Base $obj) {
		return array();
	}
	
	public function relatedObjectsDeleteQueriesAfterUpdate(Base $template, array $existingIds, array $deleteIds) {
		return array();
	}
	public function relatedObjectsInsertQueriesAfterUpdate(Base $template, array $existingIds, array $newIds) {
		return array();
	}
	public function buildViewContext(DBResult $data) {
		return NULL;
	}
	
	
	public function addError($error) {
		$this->errors[]=$error;
	}
	
	public function addMessage($message) {
		$this->messages[] = $message;
	}
	
	public function hasErrors() {
		return count($this->errors) > 0;
	}
}
