<?php
/**
 * SsoUserAdmin class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view\admin
 */
namespace sso;

use salt\Base;
use salt\DeleteQuery;
use salt\Dual;
use salt\SqlExpr;
use salt\Query;
use salt\DBResult;

/**
 * Class for User admin
 */
class SsoUserAdmin extends SsoAdmin {

	/**
	 * Build a new User admin class
	 */
	public function __construct() {
		$this->title = L::admin_user;
		$this->object = SsoUser::singleton();
		$this->searchFields = array('id', 'name', 'admin', 'state', 'auths');
		$this->modifiableFields = array('name', 'auth', 'password', 'password2', 'state', 'admin', 'can_ask', 'restrictIP', 'restrictAgent', 'timeout', 'groups', 'lang');
		$this->newFields = array('id', 'name', 'auth', 'password', 'password2', 'state', 'admin');
		$this->extraFields = array('auths', 'groups');
		$this->tooltipFields = array('lang', 'restrictIP', 'restrictAgent', 'timeout', 'can_ask', 'password', 'last_login', 'login_count', 'last_failed_login', 'failed_login_count');
		$this->hideFields = array_merge($this->tooltipFields, array('password2', 'auth_group'));
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj object
	 * @see \sso\SsoAdmin::displayName()
	 */
	public function displayName(Base $obj) {
		return '['.$obj->name.']';
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::createFrom()
	 */
	public function createFrom(array $data) {

		$obj = $this->object->getNew();

		if (strlen(trim($data['id']))===0) {
			$this->addError(L::error_user_id_missing);
			return NULL;
		}
		$obj->FORM->id = trim($data['id']);
		$obj->FORM->name = trim($data['name']);

		$obj->FORM->state = $data['state'];

		$obj->FORM->admin = $data['admin'];
		$obj->last_login = 1;
		$obj->login_count = 0;

		$data['auth_group']='';
		if (strpos($data['auth'], SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0) {
			$data['auth_group'] = substr($data['auth'], strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE));
			$data['auth']='';
		}

		$obj->FORM->auth_group = $data['auth_group'];
		$obj->FORM->auth = $data['auth'];

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to delete
	 * @see \sso\SsoAdmin::relatedObjectsDeleteQueries()
	 */
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
			$this->addError(L::error_user_delete_current);
			return array();
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to update
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::updateFrom()
	 */
	public function updateFrom(Base $obj, array $data) {
		global $sso;

		$admin = ($data['admin'] == 1);
		if (!$admin) {
			if (($obj->id === $sso->getLogin()) && $obj->admin) {
				$this->addError(L::error_user_admin_current);
				return NULL;
			} else {
				$obj->admin = $admin;
			}
		} else {
			$obj->admin = $admin;
		}

		if (($obj->id === $sso->getLogin()) && ($obj->state != $data['state'])) {
			$this->addError(L::error_user_state_current);
			return NULL;
		}
		$obj->FORM->state = $data['state'];

		if (trim($data['password']) !== '') { // password exists

			if ($obj->id === SSO_DB_USER) {
				$this->addError(L::error_user_password_db_user);
				return NULL;
			}

			if ($data['password2'] !== $data['password']) {
				$this->addError($this->displayName($obj).' : '.L::error_user_password_mismatch);
				return NULL;
			}

			global $DB;
			$q = Dual::query();

			$q->select(SqlExpr::_PASSWORD($data['password'])->privateBinds(), 'pass');
			$obj->FORM->password = \salt\first($DB->execQuery($q)->data)->pass;
		} else if (($data['password'] === '') && ($obj->password !== '')) { // password removed

			if ($obj->id === SSO_DB_USER) {
				$this->addError(L::error_user_password_db_user);
				return NULL;
			}

			$obj->password = NULL;
		}

		$obj->FORM->name = $data['name'];
		if ($obj->id === $sso->getLogin()) {
			$sso->session->SSO_USERNAME = $obj->name;
		}

		$obj->FORM->lang = $data['lang'];

		$obj->FORM->restrictIP = $data['restrictIP'];
		$obj->FORM->restrictAgent = $data['restrictAgent'];
		$obj->FORM->can_ask = $data['can_ask'];
		$obj->FORM->timeout = $data['timeout'];

		$data['auth_group'] = '';
		if (strpos($data['auth'], SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0) {
			$data['auth_group'] = substr($data['auth'], strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE));
			$data['auth'] = '';
		}

		$obj->FORM->auth_group = $data['auth_group'];
		$obj->FORM->auth = $data['auth'];

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param DBResult $data all objects
	 * @see \sso\SsoAdmin::buildViewContext()
	 */
	public function buildViewContext(DBResult $data) {
		$groupsElements = SsoGroupElement::getTooltipContents();
		return array('tooltip' => $groupsElements);
	}
}
