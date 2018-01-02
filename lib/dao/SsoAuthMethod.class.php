<?php
/**
 * SsoAuthMethod class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\FieldType;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;

/**
 * SsoAuthMethod
 *
 * @property string $id
 * @property string $name
 * @property boolean $default
 * @property boolean $create
 * @property int $type
 * @property string $options
 */
class SsoAuthMethod extends Base implements SsoAdministrable, SsoGroupable {

	/** Local type */
	const TYPE_LOCAL = 0;
	/** LDAP type */
	const TYPE_LDAP = 1;
	/** Database type */
	const TYPE_DB = 2;
	/** Class type */
	const TYPE_CLASS = 3;

	/**
	 * List of type classes
	 * @var SsoAuthMethodInterface[] self::TYPE_* => SsoAuthMethodInterface
	 */
	private static $TYPES=array();

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
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
				Field::newText(	'id', 			L::field_id)->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(	'name', 		L::field_name)->sqlType('VARCHAR(64) UNIQUE'),
				Field::newBoolean('default', 	L::field_default, FALSE, FALSE),
				Field::newBoolean('create', 	L::field_create_on_fly, FALSE, FALSE),
				Field::newNumber('type', 		L::field_type, FALSE, self::TYPE_LDAP, array(
						self::TYPE_LOCAL => L::auth_type_local,
						self::TYPE_LDAP => 	L::auth_type_ldap,
						self::TYPE_DB => 	L::auth_type_db,
						self::TYPE_CLASS => L::auth_type_class,
				)),
				Field::newText('options', 		L::field_parameters, TRUE)->sqlType('TEXT')
			)
		;
	}

	/**
	 * Retrieve options of auth type
	 * @param mixed[] $value current values as key => value
	 * @return \salt\Field[] Options list
	 */
	public function getOptions($value = NULL) {
		return self::$TYPES[$this->type]->getOptions($value);
	}

	/**
	 * Retrieve the local auth method
	 * @return \sso\SsoAuthMethod
	 */
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

	/**
	 * {@inheritDoc}
	 * @param DBHelper $db database where table is created
	 * @see \salt\Base::initAfterCreateTable()
	 */
	public function initAfterCreateTable(DBHelper $db) {
		return array(self::getLocal());
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\SsoGroupable::getGroupType()
	 */
	public static function getGroupType() {
		return SsoGroupElement::TYPE_AUTH;
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\SsoGroupable::getNameField()
	 */
	public function getNameField() {
		return 'name';
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $criteria criteria as key => value
	 * @param Pagination $pagination pagination object
	 * @see \sso\SsoAdministrable::search()
	 */
	public static function search(array $criteria, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');

		$q = SsoAuthMethod::query(TRUE);

		if (isset($criteria[self::WITH_GROUP])) {
			$qGroup = SsoGroupElement::query();
			$qGroup->whereAnd('type', '=', self::getGroupType());
			$qGroup->whereAnd('group_id', '=', $criteria[self::WITH_GROUP]);
			$q->join($qGroup, 'id', '=', $qGroup->ref_id, 'LEFT OUTER');
			$q->select(SqlExpr::_ISNULL($qGroup->group_id)->before(SqlExpr::text('!'))->asBoolean(), self::WITH_GROUP);
			unset($criteria[self::WITH_GROUP]);

			if (isset($criteria[self::EXISTS_NAME])) {
				if ($criteria[self::EXISTS_NAME] !== '') {
					$value = SqlExpr::value(NULL);
					if ($criteria[self::EXISTS_NAME] == 1) {
						$value->not();
					}
					$q->whereAnd($qGroup->group_id, 'IS', $value);
				}
				unset($criteria[self::EXISTS_NAME]);
			}
		} else {
			// with a group concat on a left outer field, we have to add a group by on each field
			foreach($q->getSelectFields() as $select) {
				$q->groupBy($select);
			}

			$gElem = SsoGroupElement::query(); // All groups linked to application
			$gElem->whereAnd('type', '=', SsoGroupElement::TYPE_AUTH);
			$q->join($gElem, 'id', '=', $gElem->ref_id, 'LEFT OUTER');

			$qGroupElem = SsoGroup::query(); // Group names linked to user
			$q->join($qGroupElem, $gElem->group_id, '=', $qGroupElem->id, 'LEFT OUTER');
			$expr = $qGroupElem->name->distinct();
			$expr->template(SqlExpr::TEMPLATE_MAIN.' ORDER BY 1 SEPARATOR '.SqlExpr::TEMPLATE_PARAM, self::GROUP_CONCAT_SEPARATOR_CHAR);
			$q->select(SqlExpr::_GROUP_CONCAT($expr), SsoGroupable::GROUPS);
		}

		foreach($criteria as $k => $v) {
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
	 * Try to authenticate a user
	 * @param string $user user name
	 * @param string $pass user password
	 * @return AuthUser The user object, identified of not (isLogged())
	 */
	public function auth($user, $pass) {
		$options = json_decode($this->options);
		if ($options  === NULL) {
			$options = new \stdClass();
		}

		return self::$TYPES[$this->type]->auth($user, $pass, $options);
	}

	/**
	 * Search a user
	 * @param string[]|string $search User ID or fields to search as key => value
	 * @return AuthUser The user object
	 */
	public function searchUser($search) {
		$options = json_decode($this->options);
		if ($options  === NULL) {
			$options = new \stdClass();
		}

		return self::$TYPES[$this->type]->search($search, $options);
	}
}

