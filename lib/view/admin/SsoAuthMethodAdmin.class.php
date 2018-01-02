<?php
/**
 * SsoAuthMethodAdmin class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view\admin
 */
namespace sso;

use salt\Base;
use salt\Query;
use salt\DeleteQuery;

/**
 * Class for Auth methods admin
 */
class SsoAuthMethodAdmin extends SsoAdmin {

	/**
	 * Build a new auth method admin class
	 */
	public function __construct() {
		$this->title = L::admin_auth;
		$this->object = SsoAuthMethod::singleton();
		$this->searchFields = array('name', 'type');
		$this->modifiableFields = array('name', 'default', 'create', 'options', 'groups');
		$this->extraFields = array('groups');
		$this->newFields = array('name', 'default', 'create', 'type');
		$this->hideFields = array();
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
	 * @param Base $obj the object to delete
	 * @see \sso\SsoAdmin::relatedObjectsDeleteQueries()
	 */
	public function relatedObjectsDeleteQueries(Base $obj) {
		if ($obj->type === SsoAuthMethod::TYPE_LOCAL) {
			$this->addError(L::error_auth_delete_local);
		}
		$q = SsoUser::query();
		$q->whereAnd('auth', '=', $obj->id);

		global $DB;
		$nb = $DB->execCountQuery($q);
		if ($nb > 0) {
			$this->addError(L::error_auth_delete_used($obj->name, $nb));
		}

		$result = array();

		$q = SsoGroupElement::deleteQuery();
		$q->allowMultipleChange();
		$q->whereAnd('ref_id', '=', $obj->id);
		$q->whereAnd('type', '=', SsoGroupElement::TYPE_AUTH);
		$result[] = $q;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::createFrom()
	 */
	public function createFrom(array $data) {
		$obj = $this->object->getNew();

		$obj->FORM->name = $data['name'];
		$obj->FORM->default = $data['default'];
		$obj->FORM->create = $data['create'];
		$obj->FORM->type = $data['type'];

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to update
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::updateFrom()
	 */
	public function updateFrom(Base $obj, array $data) {
		if ($obj->type !== SsoAuthMethod::TYPE_LOCAL) {
			$obj->FORM->options = $data['options'];
			$obj->FORM->create = $data['create'];
		}
		// default & name are the only fields that can be changed on local auth method
		$obj->FORM->name = $data['name'];
		$obj->FORM->default = $data['default'];

		return $obj;
	}
}

