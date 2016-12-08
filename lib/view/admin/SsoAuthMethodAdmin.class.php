<?php namespace sso;

use salt\Base;
use salt\Query;
use salt\DeleteQuery;

class SsoAuthMethodAdmin extends SsoAdmin {
	
	
	public function __construct() {
		$this->title = 'Méthodes d\'authentification';
		$this->object = SsoAuthMethod::meta();
		$this->searchFields = array('name', 'type');
		$this->modifiableFields = array('name', 'default', 'create', 'field_id', 'field_name', 'options', 'groups');
		$this->extraFields = array('groups');
		$this->newFields = array('name', 'default', 'create', 'field_id', 'field_name', 'type');
		$this->hideFields = array();
	}
	
	public function displayName(Base $obj) {
		return 'La méthode d\'authentification '.$obj->name;
	}
	
	public function isFemaleName() {
		return TRUE;
	}
	
	
	public function relatedObjectsDeleteQueries(Base $obj) {
		if ($obj->type === SsoAuthMethod::TYPE_LOCAL) {
			$this->addError('Impossible de supprimer la méthode d\'authentification locale');
		}
		$q = new Query(SsoUser::meta());
		$q->whereAnd('auth', '=', $obj->id);
		
		global $DB;
		$nb = $DB->execCountQuery($q);
		if ($nb > 0) {
			$this->addError('Impossible de supprimer la méthode d\'authentification '.$obj->name.' car elle est référencée par '.$nb.' utilisateur(s)');
		}

		$result = array();
		
		$q = new DeleteQuery(SsoGroupElement::meta());
		$q->allowMultipleChange();
		$q->whereAnd('ref_id', '=', $obj->id);
		$q->whereAnd('type', '=', SsoGroupElement::TYPE_AUTH);
		$result[] = $q;
		
		return $result;
	}
	
	public function createFrom(array $datas) {
		$obj = $this->object->getNew();

		$obj->name = $datas['name'];
		$obj->default = isset($datas['default']);
		$obj->create = isset($datas['create']);
		$obj->type = $datas['type'];
		$obj->field_id = $datas['field_id'];
		$obj->field_name = $datas['field_name'];
		
		return $obj;
	}
	
	public function updateFrom(Base $obj, array $data) {
		if ($obj->type !== SsoAuthMethod::TYPE_LOCAL) {
			$obj->field_id = $data['field_id'];
			$obj->field_name = $data['field_name'];
			$obj->options = $data['options'];
			$obj->create = isset($data['create']);
		}
		// default & name are the only fields that can be changed on local auth method
		$obj->name = $data['name'];
		$obj->default = isset($data['default']);
		
		return $obj;
	}
}

