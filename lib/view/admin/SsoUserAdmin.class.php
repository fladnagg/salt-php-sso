<?php namespace sso;

use salt\Base;
use salt\DeleteQuery;
use salt\Dual;
use salt\SqlExpr;
use salt\Query;
use salt\DBResult;

class SsoUserAdmin extends SsoAdmin {
	
	
	public function __construct() {
		$this->title = 'Utilisateurs';
		$this->object = SsoUser::singleton();
		$this->searchFields = array('id', 'name', 'admin', 'state');
		$this->modifiableFields = array('name', 'auth', 'password', 'password2', 'state', 'admin', 'restrictIP', 'restrictAgent', 'timeout', 'groups');
		$this->newFields = array('id', 'name', 'auth', 'password', 'password2', 'state', 'admin');
		$this->extraFields = array('auths', 'groups');
		$this->tooltipFields = array('restrictIP', 'restrictAgent', 'timeout', 'password', 'last_login', 'login_count', 'last_failed_login', 'failed_login_count');
		$this->hideFields = array_merge($this->tooltipFields, array('password2', 'auth_group'));
	}
	
	public function displayName(Base $obj) {
		return 'L\'utilisateur '.$obj->name;
	}
	
	public function createFrom(array $datas) {

		$obj = $this->object->getNew();
		
		if (strlen(trim($datas['id']))===0) {
			$this->addError('L\'ID de l\'utilisateur est obligatoire.');
			return NULL;
		}
		$obj->id = trim($datas['id']);
		$obj->name = trim($datas['name']);
		
		$obj->state = $datas['state'];
		
		$obj->admin = array_key_exists('admin', $datas);
		$obj->last_login = 1;

		$datas['auth_group']='';
		if (strpos($datas['auth'], SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0) {
			$datas['auth_group'] = substr($datas['auth'], strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE));
			$datas['auth']='';
		}
		
		$obj->auth_group = $datas['auth_group'];
		$obj->auth = $datas['auth'];
		
		return $obj;
	}
	
	public function relatedObjectsDeleteQueries(Base $obj) {
	
		global $sso;
		
		$result = array();
		
		$q = SsoGroupElement::deleteQuery();
		$q->allowMultipleChange();
		$q->whereAnd('ref_id', '=', $obj->id);
		$q->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
		$result[] = $q;

		$q = SsoCredential::deleteQuery();
		$q->allowMultipleChange();
		$q->whereAnd('user', '=', $obj->id);
		$result[] = $q;

		if ($obj->id === $sso->getLogin()) {
			$this->addError('Il est impossible de supprimer l\'utilisateur courant');
			return array();
		}
		
		return $result;
	}
	
	
	public function updateFrom(Base $obj, array $data) {
		global $sso;

		$admin = array_key_exists('admin', $data);
		if (!$admin) {
			if (($obj->id === $sso->getLogin()) && $obj->admin) {
				$this->addError('Il est impossible d\'enlever les droits administrateurs de l\'utilisateur courant');
				return NULL;
			} else {
				$obj->admin = $admin;
			}
		} else {
			$obj->admin = $admin;
		}

		if (($obj->id === $sso->getLogin()) && ($obj->state != $data['state'])) {
			$this->addError('Il est impossible de modifier l\'état de l\'utilisateur courant');
			return NULL;
		}
		$obj->state = $data['state'];
		
		if (trim($data['password']) !== '') { // password exists
			
			if ($obj->id === SSO_DB_USER) {
				$this->addError('Vous ne pouvez pas changer le mot de passe de l\'utilisateur ['.SSO_DB_USER.']');
				return NULL;
			}
			
			if ($data['password2'] !== $data['password']) {
				$this->addError($this->displayName($obj).' : Les mots de passe ne correspondent pas');
				return NULL;
			}
			
			global $DB;
			$q = Dual::query();

			$q->select(SqlExpr::_PASSWORD($data['password'])->privateBinds(), 'pass');
			$obj->password = \salt\first($DB->execQuery($q)->data)->pass;
		} else if (($data['password'] === '') && ($obj->password !== '')) { // password removed
			
			if ($obj->id === SSO_DB_USER) {
				$this->addError('Vous ne pouvez pas changer le mot de passe de l\'utilisateur ['.SSO_DB_USER.']');
				return NULL;
			}
			
			$obj->password = '';
		}
		
		$obj->name = $data['name'];
		if ($obj->id === $sso->getLogin()) {
			$sso->session->SSO_USERNAME = $obj->name;
		}
		$obj->restrictIP = array_key_exists('restrictIP', $data);
		$obj->restrictAgent = array_key_exists('restrictAgent', $data);
		$obj->timeout = SsoUser::arrayToIntTimeout($data['timeout']);

		$data['auth_group']='';
		if (strpos($data['auth'], SsoGroupableViewHelper::PREFIX_GROUP_VALUE) === 0) {
			$data['auth_group'] = substr($data['auth'], strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE));
			$data['auth']='';
		}
		
		$obj->auth_group = $data['auth_group'];
		$obj->auth = $data['auth'];

		return $obj;
	}

	public function buildViewContext(DBResult $data) {
		$groupsElements = SsoGroupElement::getTooltipContents();
		return array('tooltip' => $groupsElements);
	}
}
