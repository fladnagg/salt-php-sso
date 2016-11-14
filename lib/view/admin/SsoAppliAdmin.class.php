<?php namespace sso;

use salt\Base;
use salt\DeleteQuery;

class SsoAppliAdmin extends SsoAdmin {
	
	
	public function __construct() {
		$this->title = 'Applications';
		$this->object = SsoAppli::meta();
		$this->searchFields = array('path', 'name');
		$this->modifiableFields = array('path', 'name', 'handler', 'icon');
		$this->newFields = array('path', 'name', 'handler', 'icon');
		$this->hideFields = array();
	}
	
	public function displayName(Base $obj) {
		return 'L\'application '.$obj->name;
	}
	
	public function isFemaleName() {
		return TRUE;
	}
	
	
	public function createFrom(array $datas) {
		$obj = $this->object->getNew();

		if ((strlen(trim($datas['path'])) > 0) && (substr($datas['path'], 0, 1) !== '/')) {
			$datas['path'] = '/'.$datas['path'];
		}
			
		if ((strlen(trim($datas['icon'])) > 0) && (substr($datas['icon'], 0, 1) !== '/')) {
			$datas['icon'] = '/'.$datas['icon'];
		}
		
		$obj->path = $datas['path'];
		$obj->name = $datas['name'];
		$obj->icon = $datas['icon'];
		$obj->handler = $datas['handler'];
		
		if (!$obj->validate()) {
			$this->addError($obj->lastError());
			return NULL;
		}
		return $obj;
	}
	
	public function relatedObjectsDeleteQueries(Base $obj) {
		
		$result = array();
		
		$q = new DeleteQuery(SsoCredential::meta());
		$q->allowMultipleChange();
		$q->whereAnd('appli', '=', $obj->id);
		$result[] = $q;
		
		$q = new DeleteQuery(SsoGroupElement::meta());
		$q->allowMultipleChange();
		$q->whereAnd('ref_id', '=', $obj->id);
		$q->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
		$result[] = $q;

		return $result;
	}
	
	public function updateFrom(Base $obj, array $data) {
		$obj->path = $data['path'];
		$obj->name = $data['name'];
		$obj->handler = $data['handler'];
		$obj->icon = $data['icon'];
			
		if ($obj->isModified() && !$obj->validate()) {
			$this->addError($obj->lastError());
			return NULL;
		}
		return $obj;
	}
}
