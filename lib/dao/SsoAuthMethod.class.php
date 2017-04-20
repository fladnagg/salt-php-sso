<?php namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\FieldType;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;

/**
 * @property string id
 * @property string name
 * @property boolean default
 * @property boolean create
 * @property int type
 * @property string options
 */
class SsoAuthMethod extends Base implements SsoAdministrable, SsoGroupable {

	const TYPE_LOCAL = 0;
	const TYPE_LDAP = 1;
	const TYPE_DB = 2;
	const TYPE_CLASS = 3;
	
	private static $TYPES=array();
	
	protected function metadata() {
		parent::registerHelper(__NAMESPACE__.'\SsoAuthMethodViewHelper');
		
		self::$TYPES = array(
				self::TYPE_DB => new SsoAuthMethodDatabase(),
				self::TYPE_LDAP => new SsoAuthMethodLdap(),
				self::TYPE_LOCAL => new SsoAuthMethodLocal(),
				self::TYPE_CLASS => new SsoAuthMethodClass(),
		);
		
		self::MODEL()
			->registerId('id')
			->registerTableName('sso_auth_method')
			->registerFields(
				Field::newText(	'id', 		'ID')->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(	'name', 	'Nom')->sqlType('VARCHAR(64) UNIQUE'),
				Field::newBoolean('default', 'Par défault', FALSE, FALSE),
				Field::newBoolean('create', 'Création à la volée', FALSE, FALSE),
				Field::newNumber('type', 	'Type', FALSE, self::TYPE_LDAP, array(
						self::TYPE_LOCAL => 'Local',
						self::TYPE_LDAP => 'LDAP',
						self::TYPE_DB => 'Base de données',
						self::TYPE_CLASS => 'Classe',
				)),
				Field::newText('options', 'Paramètres', TRUE)->sqlType('TEXT')
			)
		;
	}

	public function getOptions($value = NULL) {
		return self::$TYPES[$this->type]->getOptions($value);
	}
	
	public static function getLocal() {
		$auth = new SsoAuthMethod();
		$auth->type = self::TYPE_LOCAL;
		$auth->name = 'LOCAL';
		
		$obj = new \stdClass();
		$obj->field_id = 'id';
		$obj->field_name = 'name';
		
		$auth->options = json_encode($obj);

		return $auth;
	}
	
	public function initAfterCreateTable() {
		return array(self::getLocal());
	}
	
	public static function getGroupType() {
		return SsoGroupElement::TYPE_AUTH;
	}
	
	public function getNameField() {
		return 'name';
	}

	public static function search(array $criteres, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');

		$q = SsoAuthMethod::query(TRUE);

		if (isset($criteres[self::WITH_GROUP])) {
			$qGroup = SsoGroupElement::query();
			$qGroup->whereAnd('type', '=', self::getGroupType());
			$qGroup->whereAnd('group_id', '=', $criteres[self::WITH_GROUP]);
			$q->join($qGroup, 'id', '=', $qGroup->ref_id, 'LEFT OUTER');
			$q->select(SqlExpr::_ISNULL($qGroup->group_id)->before(SqlExpr::text('!'))->asBoolean(), self::WITH_GROUP);
			unset($criteres[self::WITH_GROUP]);

			if (isset($criteres[self::EXISTS_NAME])) {
				if ($criteres[self::EXISTS_NAME] !== '') {
					$value = SqlExpr::value(NULL);
					if ($criteres[self::EXISTS_NAME] == 1) {
						$value->not();
					}
					$q->whereAnd($qGroup->group_id, 'IS', $value);
				}
				unset($criteres[self::EXISTS_NAME]);
			}
		} else {
			// with a group concat on a left outer field, we have to add a group by on each field
			foreach($q->getSelectFields() as $select) {
				$q->groupBy($select);
			}
				
			$gElem = SsoGroupElement::query(); // Tout les groupes liés a l'application
			$gElem->whereAnd('type', '=', SsoGroupElement::TYPE_AUTH);
			$q->join($gElem, 'id', '=', $gElem->ref_id, 'LEFT OUTER');
				
			$qGroupElem = SsoGroup::query(); // Nom des groupes liés a l'utilisateur
			$q->join($qGroupElem, $gElem->group_id, '=', $qGroupElem->id, 'LEFT OUTER');
			$expr = $qGroupElem->name->distinct();
			$expr->template(SqlExpr::TEMPLATE_MAIN.' ORDER BY 1 SEPARATOR '.SqlExpr::TEMPLATE_PARAM, self::GROUP_CONCAT_SEPARATOR_CHAR);
			$q->select(SqlExpr::_GROUP_CONCAT($expr), SsoGroupable::GROUPS);
		}

		foreach($criteres as $k => $v) {
			if ($v !== '') {
				if ($k === 'group') {
					$qGroup = SsoGroupElement::query();
					$qGroup->whereAnd('type', '=', self::getGroupType());
					$qGroup->whereAnd('group_id', '=', $v);
					$q->join($qGroup, 'id', '=', $qGroup->ref_id);
				} else {
					$field = self::MODEL()->$k;
					if ($field->type === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					} else if ($field->type === FieldType::NUMBER) {
						$q->whereAnd($k, '=' , $v);
					}
				} // criteria is basic field
			} // value not empty
		}// each criteria

		$q->orderAsc('name');

		return $DB->execQuery($q, $pagination);
	}
	
	/**
	 * 
	 * @param string $user
	 * @param string $pass
	 * @return AuthUser 
	 */
	public function auth($user, $pass) {
		$options = json_decode($this->options);
		if ($options  === NULL) {
			$options = new \stdClass();
		}

		return self::$TYPES[$this->type]->auth($user, $pass, $options);
	}
	
	/**
	 *
	 * @param string $search
	 * @return AuthUser
	 */
	public function searchUser($search) {
		$options = json_decode($this->options);
		if ($options  === NULL) {
			$options = new \stdClass();
		}
	
		return self::$TYPES[$this->type]->search($search, $options);
	}
}

