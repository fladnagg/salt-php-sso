<?php namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;
use salt\FieldType;

/**
 * @property int id
 * @property string name
 * @property int defaults
 * @property int types
 *
 */
class SsoGroup extends Base implements SsoAdministrable {
	
	protected function metadata() {
		parent::registerId('id');
		parent::registerTableName('sso_group');
		parent::registerHelper(__NAMESPACE__.'\SsoGroupViewHelper');

		return array(
				Field::newNumber(	'id', 		'ID')->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(		'name', 	'Nom')->sqlType('VARCHAR(64) UNIQUE'),
				Field::newNumber(	'defaults', 'Par défaut')->sqlType('BIGINT'),
				Field::newNumber(	'types', 	'Types')->sqlType('BIGINT'),
		);
	}
	
	public static function search(array $criteres, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');
		$q = new Query(SsoGroup::meta(), TRUE);

		foreach($criteres as $k => $v) {
			if ($v !== '') {
				if ($k === 'types') {
					$q->whereAnd('types', '&', pow(2, $v - 1));
				} else if ($k === 'edit') {
					$q->whereAnd('id', '=', $v);
				} else {
					$field = $q->getField($k);
					if ($field->getType() === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					}
				}
			}
		}
		
		if (isset($criteres[self::WITH_DETAILS])) {
			$types = array(
				'users' => SsoGroupElement::TYPE_USER, 
				'applis' => SsoGroupElement::TYPE_APPLI,
				'auths' => SsoGroupElement::TYPE_AUTH,
			);
			
			foreach($types as $name => $type) {
				$q->select($q->getField('types')->template(SqlExpr::TEMPLATE_MAIN.' & '.SqlExpr::TEMPLATE_PARAM, pow(2, $type-1)), 'type_'.$name);
				$q->select($q->getField('defaults')->template(SqlExpr::TEMPLATE_MAIN.' & '.SqlExpr::TEMPLATE_PARAM, pow(2, $type-1)), 'default_'.$name);
				$q->select(SqlExpr::text(1), $name); // extra fields for display
			}
		}

		$q->orderAsc('name');
		$results = $DB->execQuery($q , $pagination);

		return $results;
	}
	
	public static function getActive($type) {
		$result = array();
		foreach(self::search(array('types' => $type), NULL)->data as $row) {
			$result[$row->id] = $row->name;
		}
		return $result;
	}
	
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
	
	
	public static function getDefaultsGroupElements(SsoGroupable $obj) {
		static $allDefaults = array();
		$type = $obj->getGroupType();
		if (!isset($allDefaults[$type])) {
			$DB = DBHelper::getInstance('SSO');
			
			$q = new Query(SsoGroup::meta());
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

