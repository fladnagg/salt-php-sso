<?php
/**
 * SsoGroup class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;
use salt\FieldType;

/**
 * SsoGroup
 *
 * @property int $id
 * @property string $name
 * @property int $defaults
 * @property int $types
 *
 */
class SsoGroup extends Base implements SsoAdministrable {

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {

		parent::registerHelper(__NAMESPACE__.'\SsoGroupViewHelper');

		self::MODEL()
			->registerId('id')
			->registerTableName('sso_group')
			->registerFields(
				Field::newNumber(	'id', 		L::field_id)->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(		'name', 	L::field_name)->sqlType('VARCHAR(64) UNIQUE'),
				Field::newNumber(	'defaults', L::field_default)->sqlType('BIGINT'),
				Field::newNumber(	'types', 	L::field_types)->sqlType('BIGINT')
		);
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $criteria criteria as key => value
	 * @param Pagination $pagination pagination object
	 * @see \sso\SsoAdministrable::search()
	 */
	public static function search(array $criteria, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');
		$q = SsoGroup::query(TRUE);

		foreach($criteria as $k => $v) {
			if ($v !== '') {
				if ($k === 'types') {
					$q->whereAnd('types', '&', pow(2, $v - 1));
				} else if ($k === 'edit') {
					$q->whereAnd('id', '=', $v);
				} else {
					$field = $q->$k;
					if ($field->getType() === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					}
				}
			}
		}

		if (isset($criteria[self::WITH_DETAILS])) {
			$types = array(
				'users' => SsoGroupElement::TYPE_USER,
				'applis' => SsoGroupElement::TYPE_APPLI,
				'auths' => SsoGroupElement::TYPE_AUTH,
			);

			foreach($types as $name => $type) {

				$q->select(SqlExpr::implode(' & ', $q->types, pow(2, $type-1)), 'type_'.$name);
				$q->select(SqlExpr::implode(' & ', $q->defaults, pow(2, $type-1)), 'default_'.$name);
				$q->select(SqlExpr::text(1), $name); // extra fields for display
			}
		}

		$q->orderAsc('name');
		$results = $DB->execQuery($q , $pagination);

		return $results;
	}

	/**
	 * Return groups for active types
	 * @param int $type SsoGroupElement::TYPE_* binary mask
	 * @return string[] id => name : group name indexed by group id
	 */
	public static function getActive($type) {

		static $result = array();
		if (!isset($result[$type])) {
			$result[$type] = array();
			foreach(self::search(array('types' => $type), NULL)->data as $row) {
				$result[$type][$row->id] = $row->name;
			}
		}
		return $result[$type];
	}

	/**
	 * Return all groups
	 * @return string[] id => name
	 */
	public static function getAll() {
		static $result = NULL;
		if ($result === NULL) {
			$result = array();
			foreach(self::search(array(), NULL)->data as $row) {
				$result[$row->id] = $row->name;
			}
		}
		return $result;
	}

	/**
	 * Return default groups for a groupable element
	 * @param SsoGroupable $obj The groupable element
	 * @return \sso\SsoGroupElement[] Default groups for the element
	 */
	public static function getDefaultsGroupElements(SsoGroupable $obj) {
		static $allDefaults = array();
		$type = $obj->getGroupType();
		if (!isset($allDefaults[$type])) {
			$DB = DBHelper::getInstance('SSO');

			$q = SsoGroup::query();
			$q->selectField('id');
			$q->whereAnd('defaults', '&', pow(2, $type-1));
			$q->whereAnd('types', '&', pow(2, $type-1));
			$r = $DB->execQuery($q);
			$allDefaults[$type] = array();
			foreach($r->data as $defaultGroup) {
				$allDefaults[$type][] = $defaultGroup->id;
			}
		}
		$defaultGroups = array();
		foreach($allDefaults[$type] as $groupId) {
			$elem = new SsoGroupElement();
			$elem->group_id = $groupId;
			$elem->type = $type;
			$elem->ref_id = $obj->getId();
			$defaultGroups[] =$elem;
		}
		return $defaultGroups;
	}
}
