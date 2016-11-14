<?php namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\DBResult;
use salt\Query;

class SsoCredentialAdmin extends SsoAdmin {
	
	
	public function __construct() {
		$this->title = 'Autorisations';
		$this->object = SsoCredential::meta();
		$this->searchFields = array('user', 'appli', 'status');
		$this->modifiableFields = array('user', 'appli', 'status', 'description');
		$this->newFields = array('user', 'appli', 'status');
		$this->hideFields = array('description', 'user_group', 'appli_group');
		$this->tooltipFields = array('description');
	}

	public function displayName(Base $obj) {
		return 'L\'autorisation '.$obj->id;
	}
	
	public function isFemaleName() {
		return TRUE;
	}
	
	public function createFrom(array $datas) {
	
		$obj = $this->object->getNew();

		$datas['appli_group']='';
		$datas['user_group']='';
		
		if (strpos($datas['appli'], SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0) {
			$datas['appli_group'] = substr($datas['appli'], strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE));
			$datas['appli']='';
		}
		if (strpos($datas['user'], SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0) {
			$datas['user_group'] = substr($datas['user'], strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE));
			$datas['user']='';
		}
		
		$obj->appli = $datas['appli'];
		$obj->user = $datas['user'];
		
		$obj->appli_group = $datas['appli_group'];
		$obj->user_group = $datas['user_group'];

		$obj->status = $datas['status'];
		
		if (!$obj->validate()) {
			$this->addError($obj->lastError());
			return NULL;
		}

		return $obj;
	}
	
	public function updateFrom(Base $obj, array $data) {
	
		$data['appli_group']='';
		$data['user_group']='';
			
		if (strpos($data['appli'], SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0) {
			$data['appli_group'] = substr($data['appli'], strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE));
			$data['appli']='';
		}
		if (strpos($data['user'], SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0) {
			$data['user_group'] = substr($data['user'], strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE));
			$data['user']='';
		}
		
		$obj->appli = $data['appli'];
		$obj->user = $data['user'];
		$obj->status = $data['status'];
		$obj->user_group = $data['user_group'];
		$obj->appli_group = $data['appli_group'];
		$obj->description = $data['description'];
		
		if ($obj->isModified() && !$obj->validate()) {
			$this->addError($this->displayName($obj).' comporte une erreur : '.$obj->lastError());
			return NULL;
		}
		
		return $obj;
	}
	
	public function buildViewContext(DBResult $data) {
		
		global $Input;
		$DB = DBHelper::getInstance('SSO');

		$users = array();
		$applis = array();
	
		foreach($data->data as $row) {
			if ($row->user !== NULL) {
				$users[$row->user] = $row->user;
			}
			if ($row->appli !== NULL) {
				$applis[$row->appli] = $row->appli;
			}
		}
		
		if ($Input->P->ISSET->new) {
			$params = $Input->P->RAW->new;
			$users[$params['user']] = $params['user'];
			$applis[$params['appli']] = $params['appli'];
		}
		
		if ($Input->P->ISSET->data) {
			$params = $Input->P->RAW->data;
			foreach($params as $k=>$v) {
				$users[$v['user']] = $v['user'];
				$applis[$v['appli']] = $v['appli'];
			}
		}
		foreach($users as $k => $v) {
			if (($v === '') || (strpos($v, SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0)) {
				unset($users[$k]);
			}
		}
		foreach($applis as $k => $v) {
			if (($v === '') || (strpos($v, SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0)) {
				unset($applis[$k]);
			}
		}
	
		$q = new Query(SsoUser::meta());
		$q->whereAnd('id', 'IN', $users);
		$q->disableIfEmpty($users);
		$q->selectFields(array('id', 'name'));
		$users = array();
		foreach($DB->execQuery($q)->data as $row) {
			$users[$row->id] = $row->name;
		}
		$q = new Query(SsoAppli::meta());
		$q->whereAnd('id', 'IN', $applis);
		$q->disableIfEmpty($applis);
		$q->selectFields(array('id', 'name'));
		$applis = array();
		foreach($DB->execQuery($q)->data as $row) {
			$applis[$row->id] = $row->name;
		}
		
		$groupsElements = SsoGroupElement::getTooltipContents();

		return array('users' => $users, 'applis' => $applis, 'tooltip' => $groupsElements);
	}
}
