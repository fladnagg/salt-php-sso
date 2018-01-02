<?php
/**
 * SsoCredentialAdmin class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view\admin
 */
namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\DBResult;
use salt\Query;

/**
 * Class for Credential admin
 */
class SsoCredentialAdmin extends SsoAdmin {

	/**
	 * Build a new credential admin class
	 */
	public function __construct() {
		$this->title = L::admin_credential;
		$this->object = SsoCredential::singleton();
		$this->searchFields = array('user', 'appli', 'status');
		$this->modifiableFields = array('user', 'appli', 'status', 'description');
		$this->newFields = array('user', 'appli', 'status');
		$this->hideFields = array('description', 'user_group', 'appli_group');
		$this->tooltipFields = array('description');
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj object
	 * @see \sso\SsoAdmin::displayName()
	 */
	public function displayName(Base $obj) {
		return '['.$obj->id.']';
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::createFrom()
	 */
	public function createFrom(array $data) {

		$obj = $this->object->getNew();

		$data['appli_group']='';
		$data['user_group']='';

		if (strpos($data['appli'], SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0) {
			$data['appli_group'] = substr($data['appli'], strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE));
			$data['appli']='';
		}
		if (strpos($data['user'], SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0) {
			$data['user_group'] = substr($data['user'], strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE));
			$data['user']='';
		}

		$obj->FORM->appli = $data['appli'];
		$obj->FORM->user = $data['user'];

		$obj->FORM->appli_group = $data['appli_group'];
		$obj->FORM->user_group = $data['user_group'];

		$obj->FORM->status = $data['status'];

		if (!$obj->validate()) {
			$this->addError($obj->lastError());
			return NULL;
		}

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to update
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::updateFrom()
	 */
	public function updateFrom(Base $obj, array $data) {

		$data['appli_group']='';
		$data['user_group']='';

		if (strpos($data['appli'], SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0) {
			$data['appli_group'] = substr($data['appli'], strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE));
			$data['appli']='';
		}
		if (strpos($data['user'], SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0) {
			$data['user_group'] = substr($data['user'], strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE));
			$data['user']='';
		}

		$obj->FORM->appli = $data['appli'];
		$obj->FORM->user = $data['user'];
		$obj->FORM->status = $data['status'];
		$obj->FORM->user_group = $data['user_group'];
		$obj->FORM->appli_group = $data['appli_group'];
		$obj->FORM->description = $data['description'];

		if ($obj->isModified() && !$obj->validate()) {
			$this->addError(L::error_object_has_error($this->displayName($obj), $obj->lastError()));
			return NULL;
		}

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param DBResult $data all objects
	 * @see \sso\SsoAdmin::buildViewContext()
	 */
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
			if (($v === '') || (strpos($v, SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0)) {
				unset($users[$k]);
			}
		}
		foreach($applis as $k => $v) {
			if (($v === '') || (strpos($v, SsoGroupableDAOConverter::PREFIX_GROUP_VALUE) === 0)) {
				unset($applis[$k]);
			}
		}

		$q = SsoUser::query();
		$q->whereAnd('id', 'IN', $users);
		$q->disableIfEmpty($users);
		$q->selectFields(array('id', 'name'));
		$users = array();
		foreach($DB->execQuery($q)->data as $row) {
			$users[$row->id] = $row->name;
		}
		$q = SsoAppli::query();
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
