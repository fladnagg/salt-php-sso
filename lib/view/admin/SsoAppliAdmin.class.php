<?php
/**
 * SsoAppliAdmin class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view\admin
 */
namespace sso;

use salt\Base;
use salt\DeleteQuery;

/**
 * Class for Application admin
  */
class SsoAppliAdmin extends SsoAdmin {

	/**
	 * Build a new Application admin class
	 */
	public function __construct() {
		$this->title = L::admin_appli;
		$this->object = SsoAppli::singleton();
		$this->searchFields = array('path', 'name');
		$this->modifiableFields = array('path', 'name', 'handler', 'icon', 'groups');
		$this->extraFields = array('groups');
		$this->newFields = array('path', 'name', 'handler', 'icon');
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
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::createFrom()
	 */
	public function createFrom(array $data) {
		$obj = $this->object->getNew();

		if ((strlen(trim($data['path'])) > 0) && (substr($data['path'], 0, 1) !== '/')) {
			$data['path'] = '/'.$data['path'];
		}

		if ((strlen(trim($data['icon'])) > 0) && (substr($data['icon'], 0, 1) !== '/')) {
			$data['icon'] = '/'.$data['icon'];
		}

		$obj->path = $data['path'];
		$obj->name = $data['name'];
		$obj->icon = $data['icon'];
		$obj->handler = $data['handler'];

		if (!$obj->validate()) {
			$this->addError($obj->lastError());
			return NULL;
		}
		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to delete
	 * @see \sso\SsoAdmin::relatedObjectsDeleteQueries()
	 */
	public function relatedObjectsDeleteQueries(Base $obj) {

		$result = array();

		$q = SsoCredential::deleteQuery();
		$q->allowMultipleChange();
		$q->whereAnd('appli', '=', $obj->id);
		$result[] = $q;

		$q = SsoGroupElement::deleteQuery();
		$q->allowMultipleChange();
		$q->whereAnd('ref_id', '=', $obj->id);
		$q->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
		$result[] = $q;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to update
	 * @param mixed[] $data key => value
	 * @see \sso\SsoAdmin::updateFrom()
	 */
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
