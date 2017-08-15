<?php
/**
 * SsoUser class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Base;
use salt\DBException;
use salt\DBHelper;
use salt\Field;
use salt\FieldType;
use salt\InsertQuery;
use salt\Pagination;
use salt\Query;
use salt\SqlDateFormat;
use salt\SqlExpr;
use salt\UpdateQuery;
use salt\Dual;

/**
 * SsoUser
 *
 * @property string $id
 * @property string $name
 * @property string $password
 * @property int $auth
 * @property int $auth_group
 * @property string $lang
 * @property boolean $admin
 * @property boolean $restrictIP
 * @property boolean $restrictAgent
 * @property int $timeout
 * @property int $last_login
 * @property int $login_count
 * @property int $last_failed_login
 * @property int $failed_login_count
 * @property int $state
 *
 */
class SsoUser extends Base implements SsoAdministrable, SsoGroupable {

	/** Used to convert interval data to timeout */
	public static $CONVERT_TIMEOUT=array(
			'd' => 86400, // 24*60*60, // expression are not supported in literal initialization...
			'h' => 3600, // 60*60, // expression are not supported in literal initialization...
			'm' => 60,
	);

	/** Used to generate select of timeout element */
	public static $TIME_RANGE=array(
			'd' => 7, // max 7 days of inactivity for session timeout
			'h' => 23,
			'm' => 59,
	);

	/** Default timeout */
	const DEFAULT_TIMEOUT = 259200; // 60*60*24*3; // 3 days by default

	/** State disabled */
	const STATE_DISABLED = 0;
	/** State enabled */
	const STATE_ENABLED = 1;
	/** State pending to administrator validation */
	const STATE_TO_VALIDATE = 2;

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
		parent::registerHelper(__NAMESPACE__.'\SsoUserViewHelper');

		self::MODEL()
			->registerId('id')
			->registerTableName('sso_user')
			->registerFields(
				Field::newText(	'id', 			L::field_id)->sqlType('VARCHAR(32) PRIMARY KEY')->displayOptions(array('size'=>8)),
				Field::newText(	'name', 		L::field_name)->sqlType('VARCHAR(64)')->displayOptions(array('size'=>40)),
				Field::newText(	'password', 	L::field_password, TRUE)->sqlType('CHAR(41)')->displayOptions(array('size'=>20, 'type' => 'password')),
				Field::newNumber('auth', 		L::field_auth_method, TRUE),
				Field::newNumber('auth_group', 	L::field_auth_methods, TRUE),
				Field::newText(	'lang', 		L::field_language, TRUE, SSO_LOCALE, Locale::availables())->sqlType('VARCHAR(32)'),
				Field::newBoolean('admin', 		L::field_admin, FALSE, FALSE),
				Field::newBoolean('restrictIP', 	L::field_restrict_ip, FALSE, FALSE),
				Field::newBoolean('restrictAgent', 	L::field_restrict_agent, FALSE, TRUE),
				Field::newNumber('timeout', 		L::field_session_duration, FALSE, self::DEFAULT_TIMEOUT),
				Field::newDate('last_login',		L::field_last_login, SqlDateFormat::DATETIME, 'd/m/Y H:i:s'),
				Field::newNumber('login_count',		L::field_login_count, FALSE, 1),
				Field::newDate('last_failed_login',	L::field_last_failed_login, SqlDateFormat::DATETIME, 'd/m/Y H:i:s', TRUE),
				Field::newNumber('failed_login_count',	L::field_failed_login_count, FALSE, 0),
				Field::newNumber('state', 			L::field_state, FALSE, self::STATE_DISABLED, array(
					self::STATE_ENABLED => 		L::user_state_enabled,
					self::STATE_DISABLED => 	L::user_state_disabled,
					self::STATE_TO_VALIDATE => 	L::user_state_pending,
				))
		);
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\SsoGroupable::getGroupType()
	 */
	public static function getGroupType() {
		return SsoGroupElement::TYPE_USER;
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

		$q = SsoUser::query(TRUE);

		if (isset($criteria[self::WITH_DETAILS])) {
			$gElem = SsoGroupElement::query(); // All groups linked to user
			$gElem->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
			$q->join($gElem, 'id', '=', $gElem->ref_id, 'LEFT OUTER');

			$creds = SsoCredential::query(); // All credentials linked to user or one of his groups
			$credsLink = $creds->getSubQuery();
			$credsLink->whereOr('user', '=', $q->id);
			$credsLink->whereOr('user_group', '=', $gElem->group_id);
			$q->join($creds, $creds->status, '=', 2, 'LEFT OUTER');
			$q->joinOnAndQuery($creds, $credsLink);

			$gAppli = SsoGroupElement::query(); // All application groups linked to credentials
			$gAppli->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
			$q->join($gAppli, $creds->appli_group, '=', $gAppli->group_id, 'LEFT OUTER');

			$applis = SsoAppli::query(); // and finally all applications linked to credential or application group !
			$q->join($applis, $creds->appli, '=', $applis->id, 'LEFT OUTER');
			$q->joinOnOr($applis, $gAppli->ref_id, '=', $applis->id);

			// All theses joins for compute this field : the list of application allowed for the user
			$expr = $applis->name->distinct();
			$expr->template(SqlExpr::TEMPLATE_MAIN.' ORDER BY 1 SEPARATOR '.SqlExpr::TEMPLATE_PARAM, self::GROUP_CONCAT_SEPARATOR_CHAR);
			$q->select(SqlExpr::_GROUP_CONCAT($expr), 'auths');
			$q->select(SqlExpr::text(1), 'password2');


			$qGroupElem = SsoGroup::query(); // Group names linked to user
			$q->join($qGroupElem, $gElem->group_id, '=', $qGroupElem->id, 'LEFT OUTER');
			$expr = $qGroupElem->name->distinct();
			$expr->template(SqlExpr::TEMPLATE_MAIN.' ORDER BY 1 SEPARATOR '.SqlExpr::TEMPLATE_PARAM, self::GROUP_CONCAT_SEPARATOR_CHAR);
			$q->select(SqlExpr::_GROUP_CONCAT($expr), SsoGroupable::GROUPS);
		}

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
		}

		foreach($criteria as $k => $v) {
			if ($v !== '') {
				if ($k === 'group') {
					$qGroup = SsoGroupElement::query();
					$qGroup->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
					$qGroup->whereAnd('group_id', '=', $v);
					$q->join($qGroup, 'id', '=', $qGroup->ref_id);
				} else {
					$field = self::MODEL()->$k;
					if ($field->type === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					} else if ($field->type === FieldType::BOOLEAN) {
						$q->whereAnd($k, '=', ($v == 1));
					} else if ($field->type === FieldType::NUMBER) {
						$q->whereAnd($k, '=', $v);
					}
				}
			}
		}

		// with a count on a left outer field, we have to add a group by on each field
		foreach(self::MODEL()->getFields() as $field) {
			$q->groupBy($field->name);
		}
		$q->orderAsc('name');

		return $DB->execQuery($q, $pagination);
	}

	/**
	 * Return a range for a unit
	 * @param string $unit unit in [dhm] (day, hours, minutes)
	 * @return int[] range for the unit : hours will return array(0, ..., 23)
	 */
	public static function unitRange($unit) {
		return range(0, self::$TIME_RANGE[$unit]);
	}

	/**
	 * Convert a timeout array in second
	 * @param int[] $data unit => value
	 * @return int seconds
	 */
	public static function arrayToIntTimeout($data) {
		$int = 0;
		foreach($data as $unit => $time) {
			$int+=self::$CONVERT_TIMEOUT[$unit]*$time;
		}
		return $int;
	}

	/**
	 * Convert seconds to timeout array
	 * @param int $int timeout in seconds
	 * @return int[] data timeout as unit => value
	 */
	public static function intTimeoutToArray($int) {
		$datas=array();
		foreach(self::$CONVERT_TIMEOUT as $unit => $time) {
			$datas[$unit] = 0;
			$t = floor($int / $time);
			if ($t > 0) {
				$datas[$unit] = $t;
				$int = $int % $time;
			}
		}

		return $datas;
	}

	/**
	 * Retrieve user by ID
	 * @param string $id user Id to retrieve
	 * @return SsoUser|NULL return NULL if user table does not exists at all, return a new SsoUser if the user does not exists in table
	 */
	public static function findFromId($id) {

		$DB = DBHelper::getInstance('SSO');

		try {
			$user = SsoUser::getById($DB, $id);
		} catch (DBException $ex) {
			if ($ex->getSqlStateErrorCode() === DBException::TABLE_DOES_NOT_EXISTS) {
				return NULL;
			} else {
				throw $ex;
			}
		}
		if ($user === NULL) {
			$user = new SsoUser();
			$user->id = $id;
		}

		return $user;
	}

	/**
	 * Check a user is enabled
	 * @return boolean TRUE if user enabled, FALSE otherwise
	 */
	public function isEnabled() {
		return $this->state === self::STATE_ENABLED;
	}

	/**
	 * Register a successfull access for user
	 * @param string $id User ID
	 * @param AuthUser $authUser The AuthUser object from an auth method
	 * @throws Exception
	 * @return \sso\SsoUser The SsoUser (created if it's his first access)
	 */
	public static function updateLoginOk($id, AuthUser $authUser) {
		$DB = DBHelper::getInstance('SSO');

		$user = self::findFromId($id);
		if ($user !== NULL) {
			if (!$user->isNew()) {
				$user->last_login = time();
				if (($user->auth === NULL) && ($user->auth_group === NULL)) {
					$user->auth = $authUser->getAuthFrom();
				}
				$q = new UpdateQuery($user);
				$q->increment('login_count');
				$DB->execUpdate($q);

				$authUser->setState($user->state);
			} else {
				$name = $authUser->name;
				$user->name = ($name === NULL)?$id:$name;
				$user->last_login = time();
				$user->state = $authUser->getState();
				$user->auth = $authUser->getAuthFrom();

				$DB->beginTransaction();
				try {
					$q = new InsertQuery($user);
					$DB->execInsert($q);

					$groupsElems = SsoGroup::getDefaultsGroupElements($user);
					if (count($groupsElems) > 0) {
						$DB->execInsert(new InsertQuery($groupsElems));
					}

					$DB->commit();
				} catch (\Exception $ex) {
					$DB->rollback();
					throw $ex;
				}
			}
		}
		return $user;
	}

	/**
	 * Register a failed login attempt
	 * @param string $id user ID
	 * @return boolean TRUE if user exists
	 */
	public static function updateLoginFailed($id) {
		$DB = DBHelper::getInstance('SSO');

		$user = self::findFromId($id);
		if (($user !== NULL) && (!$user->isNew())) {
			$user->last_failed_login = time();
			$q = new UpdateQuery($user);
			$q->increment('failed_login_count');
			$DB->execUpdate($q);
		}
		return ($user!==NULL);
	}

	/**
	 * Retrieve the user based of SSO database informations, used for initialize the SSO
	 * @return \sso\SsoUser A user with login and password of the database
	 */
	public static function getInitUser() {
		global $DB;

		$initUser = new SsoUser();

		$initUser->id = SSO_DB_USER;
		$initUser->name = SSO_DB_USER;
		$initUser->state = SsoUser::STATE_ENABLED;

		$q = Dual::query();
		$q->select(SqlExpr::_PASSWORD(SSO_DB_PASS)->privateBinds(), 'pass');
		$initUser->password = \salt\first($DB->execQuery($q)->data)->pass;

		$q = SsoAuthMethod::query();
		$q->selectField('id');
		$q->whereAnd('type', '=', SsoAuthMethod::TYPE_LOCAL);
		$auth = \salt\first($DB->execQuery($q, new Pagination(0, 1))->data);
		if ($auth !== NULL) {
			$initUser->auth = $auth->id;
		}

		$initUser->admin = TRUE;
		$initUser->last_login = time();

		return $initUser;
	}
}
