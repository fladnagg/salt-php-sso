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
 * @property string field_id
 * @property string field_name
 * @property string options
 */
class SsoAuthMethod extends Base implements SsoAdministrable, SsoGroupable {

	const TYPE_LOCAL = 0;
	const TYPE_LDAP = 1;
	const TYPE_DB = 2;
	const TYPE_CLASS = 3;
	
	private static $TYPES=array();
	
	protected function metadata() {
		parent::registerId('id');
		parent::registerTableName('sso_auth_method');
		parent::registerHelper(__NAMESPACE__.'\SsoAuthMethodViewHelper');

		self::$TYPES = array(
			self::TYPE_DB => new SsoAuthMethodDatabase(),
			self::TYPE_LDAP => new SsoAuthMethodLdap(),
			self::TYPE_LOCAL => new SsoAuthMethodLocal(),
			self::TYPE_CLASS => new SsoAuthMethodClass(),
		);
		
		return array(
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
			Field::newText('field_id', 'Champ ID')->sqlType('VARCHAR(32)'),
			Field::newText('field_name', 'Champ Name')->sqlType('VARCHAR(32)'),
			Field::newText('options', 'Paramètres', TRUE)->sqlType('TEXT'),
		);
	}

	public function getOptions($value = NULL) {
		return self::$TYPES[$this->type]->getOptions($value);
	}
	
	public static function getLocal() {
		$auth = new SsoAuthMethod();
		$auth->type = self::TYPE_LOCAL;
		$auth->name = 'LOCAL';
		$auth->field_id = 'id';
		$auth->field_name = 'name';
		
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

		$auth = SsoAuthMethod::meta();
		$q = new Query($auth, TRUE);

		if (isset($criteres[self::WITH_GROUP])) {
			$qGroup = new Query(SsoGroupElement::meta());
			$qGroup->whereAnd('type', '=', self::meta()->getGroupType());
			$qGroup->whereAnd('group_id', '=', $criteres[self::WITH_GROUP]);
			$q->join($qGroup, 'id', '=', $qGroup->getField('ref_id'), 'LEFT OUTER');
			$q->select(SqlExpr::func('!ISNULL', $qGroup->getField('group_id'))->asBoolean(), self::WITH_GROUP);
			unset($criteres[self::WITH_GROUP]);

			if (isset($criteres[self::EXISTS_NAME])) {
				if ($criteres[self::EXISTS_NAME] !== '') {
					$value = SqlExpr::value(NULL);
					if ($criteres[self::EXISTS_NAME] == 1) {
						$value->not();
					}
					$q->whereAnd($qGroup->getField('group_id'), 'IS', $value);
				}
				unset($criteres[self::EXISTS_NAME]);
			}
		}

		foreach($criteres as $k => $v) {
			if ($v !== '') {
				if ($k === 'group') {
					$qGroup = new Query(SsoGroupElement::meta());
					$qGroup->whereAnd('type', '=', self::getGroupType());
					$qGroup->whereAnd('group_id', '=', $v);
					$q->join($qGroup, 'id', '=', $qGroup->getField('ref_id'));
				} else {
					$field = self::meta()->getField($k);
					if ($field->type === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					} else if ($field->type === FieldType::NUMBER) {
						$q->whereAnd($k, '=' , $v);
					}
				}
			}
		}

		// with a count on a left outer field, we have to add a group by on each field
		foreach(self::meta()->getFieldsMetadata() as $field) {
			$q->groupBy($field->name);
		}
		$q->orderAsc('name');

		return $DB->execQuery($q, $pagination);
	}
	
	/**
	 * 
	 * @param unknown $user
	 * @param unknown $pass
	 * @return AuthUser 
	 */
	public function auth($user, $pass) {
		$options = json_decode($this->options);
		if ($options  === NULL) {
			$options = new \stdClass();
		}
		$options->field_id = $this->field_id;
		$options->field_name = $this->field_name;

		return self::$TYPES[$this->type]->auth($user, $pass, $options);
	}
}
