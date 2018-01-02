<?php
/**
 * SsoGroupAdmin class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view\admin
 */
namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\DBResult;
use salt\DeleteQuery;
use salt\InsertQuery;
use salt\Query;
use salt\SqlExpr;

/**
 * Class for Group admin
 */
class SsoGroupAdmin extends SsoAdmin {

	/**
	 * @var int[] types of groupable element : key => SsoGroupElement::TYPE_* */
	private static $types = array(
		'users' => SsoGroupElement::TYPE_USER,
		'applis' => SsoGroupElement::TYPE_APPLI,
		'auths' => SsoGroupElement::TYPE_AUTH,
	);

	/**
	 * Build a new Group admin class
	 */
	public function __construct() {
		$this->title = L::admin_group;
		$this->object = SsoGroup::singleton();
		$this->searchFields = array('name', 'types');
		$this->modifiableFields = array('name');
		$this->newFields = array('name');
		$this->extraFields = array('users', 'applis', 'auths');
		$this->hideFields = array('defaults', 'types');

		$groupables = array('users', 'applis', 'auths');
		foreach(array_keys(self::$types) as $groupable) {
			$this->modifiableFields[]='type_'.$groupable;
			$this->modifiableFields[]='default_'.$groupable;
			$this->modifiableFields[]= $groupable;

			$this->extraFields[]='type_'.$groupable;
			$this->extraFields[]='default_'.$groupable;

			$this->newFields[]='type_'.$groupable;
			$this->newFields[]='default_'.$groupable;
		}
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

		$defaults = 0;
		$types = 0;
		foreach(self::$types as $name => $value) {
			if (isset($data['type_'.$name])) {
				$types |= pow(2, $value -1);
			}
			if (isset($data['default_'.$name])) {
				$defaults |= pow(2, $value -1);
			}
		}

		$obj->FORM->name = $data['name'];
		$obj->defaults = $defaults;
		$obj->types = $types;

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param Base $obj the object to delete
	 * @see \sso\SsoAdmin::relatedObjectsDeleteQueries()
	 */
	public function relatedObjectsDeleteQueries(Base $obj) {

		$DB = DBHelper::getInstance('SSO');

		$result = array();

		$q = SsoGroupElement::deleteQuery();
		$q->allowMultipleChange();
		$q->whereAnd('group_id', '=', $obj->id);
		$result[] = $q;

		$q = SsoCredential::query();
		$q->whereOr('appli_group', '=', $obj->id);
		$q->whereOr('user_group', '=', $obj->id);

		$nb = $DB->execCountQuery($q);
		if ($nb > 0) {
			$this->addError(L::error_group_delete_used($obj->name, $nb));
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

		$obj->FORM->name = $data['name'];

		$defaults = 0;
		$types = 0;
		foreach(self::$types as $name => $value) {
			if (isset($data['type_'.$name])) {
				$types |= pow(2, $value -1);
			}
			if (isset($data['default_'.$name])) {
				$defaults |= pow(2, $value -1);
			}
		}

		$removedTypes = $obj->types & ~$types; // old AND NOT new : all 1 bits in old but missing in new

		if ($removedTypes > 0) {
			$q = SsoGroupElement::query();
			$q->whereAnd('group_id', '=', $obj->id);

			//(pow(2, t1.type-1) & 5)

			$expr = SqlExpr::_POW(2, $q->type->after(SqlExpr::text(' - 1')));

			$q->whereAnd($expr, '&', $removedTypes);

			$DB = DBHelper::getInstance('SSO');
			$nb = $DB->execCountQuery($q);
			if ($nb > 0) {
				$bin = strrev(decbin($removedTypes));
				$types = array();
				$values = SsoGroupElement::MODEL()->type->values;
				for($i = 0; $i < strlen($bin); $i++) {
					if ($bin[$i] === '1') {
						$types[] = $values[$i+1];
					}
				}
				$this->addError(L::error_group_disable_used(implode(', ', $types), $obj->name, $nb));
				return NULL;
			}
		}

		$obj->defaults = $defaults & $types; // default cannot be activated for disabled types
		$obj->types = $types;

		return $obj;
	}

	/**
	 * {@inheritDoc}
	 * @param SsoGroupElement $template object to delete with some field setted
	 * @param array $existingIds existing elements id
	 * @param array $deleteIds deleted elements id
	 * @see \sso\SsoAdmin::relatedObjectsDeleteQueriesAfterUpdate()
	 */
	public function relatedObjectsDeleteQueriesAfterUpdate(SsoGroupElement $template, array $existingIds, array $deleteIds) {

		$deletedGroups = array_intersect($deleteIds, $existingIds);
		if (count($deletedGroups) > 0) {
			$q = SsoGroupElement::deleteQuery();
			$q->allowMultipleChange();
			$q->whereAnd('group_id', '=', $template->group_id);
			$q->whereAnd('type', '=', $template->type);
			$q->whereAnd('ref_id', 'IN', $deletedGroups);

			return array($q);
		}
		return array();

	}

	/**
	 * {@inheritDoc}
	 * @param SsoGroupElement $template object to insert with some field setted
	 * @param array $existingIds existing elements id
	 * @param array $newIds new elements id
	 * @see \sso\SsoAdmin::relatedObjectsInsertQueriesAfterUpdate()
	 */
	public function relatedObjectsInsertQueriesAfterUpdate(SsoGroupElement $template, array $existingIds, array $newIds) {

		$addedGroups = array_diff($newIds, $existingIds);
		if (count($addedGroups) > 0) {
			foreach($addedGroups as $k => $elemId) {
				$elem  = new SsoGroupElement();
				$elem->group_id = $template->group_id;
				$elem->type = $template->type;
				$elem->ref_id = $elemId;
				$addedGroups[$k] = $elem;
			}
			return array(new InsertQuery($addedGroups));
		}
		return array();
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
