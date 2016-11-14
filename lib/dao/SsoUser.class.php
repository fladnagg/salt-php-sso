<?php namespace sso;

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
 * @property string id
 * @property string name
 * @property string password
 * @property int auth
 * @property int auth_group
 * @property boolean admin
 * @property boolean restrictIP
 * @property boolean restrictAgent
 * @property int timeout
 * @property int last_login
 * @property int login_count
 * @property int last_failed_login
 * @property int failed_login_count
 * @property int state
 *
 */
class SsoUser extends Base implements SsoAdministrable, SsoGroupable {

	public static $CONVERT_TIMEOUT=array(
			'd' => 86400, // 24*60*60, // expression are not supported in literal initialization...
			'h' => 3600, // 60*60, // expression are not supported in literal initialization...
			'm' => 60,
	);
	
	public static $TIME_RANGE=array(
			'd' => 7, // max 7 days of inactivity for session timeout
			'h' => 23,
			'm' => 59,
	);
	
	const APPLI_SEPARATOR_CHAR = "\0";
	
	const DEFAULT_TIMEOUT = 259200; // 60*60*24*3; // 3 days by default
	
	const STATE_DISABLED = 0;
	const STATE_ENABLED = 1;
	const STATE_TO_VALIDATE = 2;
	
	protected function metadata() {
		parent::registerId('id');
		parent::registerTableName('sso_user');
		parent::registerHelper(__NAMESPACE__.'\SsoUserViewHelper');

		return array(
			Field::newText(	'id', 		'ID')->sqlType('VARCHAR(32) PRIMARY KEY')->displayOptions(array('size'=>8)),
			Field::newText(	'name', 	'Nom')->sqlType('VARCHAR(64)')->displayOptions(array('size'=>40)),
			Field::newText(	'password', 'Mot de passe', TRUE)->sqlType('CHAR(41)')->displayOptions(array('size'=>20, 'type' => 'password')),
			Field::newNumber('auth', 	'Méthode d\'authentification', TRUE),
			Field::newNumber('auth_group', 'Méthodes d\'authentification', TRUE),
			Field::newBoolean('admin', 	'Admin', FALSE, FALSE),
			Field::newBoolean('restrictIP', 		'Vérifier IP', FALSE, FALSE),
			Field::newBoolean('restrictAgent', 	'Vérifier User-Agent', FALSE, TRUE),
			Field::newNumber('timeout', 				'Durée session', FALSE, self::DEFAULT_TIMEOUT),
			Field::newDate('last_login',				'Dernier accès', SqlDateFormat::DATETIME, 'd/m/Y H:i:s'),
			Field::newNumber('login_count',			'Nb succès', FALSE, 1),
			Field::newDate('last_failed_login',	'Dernier échec', SqlDateFormat::DATETIME, 'd/m/Y H:i:s', TRUE),
			Field::newNumber('failed_login_count',	'Nb échecs', FALSE, 0),
			Field::newNumber('state', 	'Etat', FALSE, self::STATE_DISABLED, array(
				self::STATE_ENABLED => 'Actif',
				self::STATE_DISABLED => 'Inactif',
				self::STATE_TO_VALIDATE => 'En attente de validation',
			)),
		);
	}

	public static function getGroupType() {
		return SsoGroupElement::TYPE_USER;
	}
	
	public function getNameField() {
		return 'name';
	}

	public static function search(array $criteres, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');

		$user = SsoUser::meta();
		$q = new Query($user, TRUE);

		if (isset($criteres[self::WITH_DETAILS])) {
			$gElem = new Query(SsoGroupElement::meta()); // Tout les groupes liés a l'utilisateur
			$gElem->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
			$q->join($gElem, 'id', '=', $gElem->getField('ref_id'), 'LEFT OUTER');
			
			$creds = new Query(SsoCredential::meta()); // Toutes les autorisations liés a l'utilisateur ou l'un de ses groupes
			$credsLink = $creds->getSubQuery();
			$credsLink->whereOr('user', '=', $q->getField('id'));
			$credsLink->whereOr('user_group', '=', $gElem->getField('group_id'));
			$q->join($creds, $creds->getField('status'), '=', 2, 'LEFT OUTER');
			$q->joinOnAndQuery($creds, $credsLink);
			
			$gAppli = new Query(SsoGroupElement::meta()); // Tout les groupes d'appli liés aux autorisations
			$gAppli->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
			$q->join($gAppli, $creds->getField('appli_group'), '=', $gAppli->getField('group_id'), 'LEFT OUTER');
			
			$applis = new Query(SsoAppli::meta()); // et enfin toutes les applis liés a un credential ou a un groupe d'applis !
			$q->join($applis, $creds->getField('appli'), '=', $applis->getField('id'), 'LEFT OUTER');
			$q->joinOnOr($applis, $gAppli->getField('ref_id'), '=', $applis->getField('id'));

			// Toutes ces jointures pour calculer ce champ qui est la liste des applis sur lesquelles l'utilisateur est autorisé
			$expr = $applis->getField('name')->distinct();
			$expr->template(SqlExpr::TEMPLATE_MAIN.' ORDER BY 1 SEPARATOR '.SqlExpr::TEMPLATE_PARAM, self::APPLI_SEPARATOR_CHAR);
			$q->select(SqlExpr::func('GROUP_CONCAT', $expr), 'auths');
			$q->select(SqlExpr::text(1), 'password2');
		}
		
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
					$qGroup->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
					$qGroup->whereAnd('group_id', '=', $v);
					$q->join($qGroup, 'id', '=', $qGroup->getField('ref_id'));
				} else {
					$field = $user->getField($k);
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
		foreach($user->getFieldsMetadata() as $field) {
			$q->groupBy($field->name);
		}
		$q->orderAsc('name');

		return $DB->execQuery($q, $pagination);
	}

	public static function unitRange($unit) {
		return range(0, self::$TIME_RANGE[$unit]);
	}

	public static function arrayToIntTimeout($data) {
		$int = 0;
		foreach($data as $unit => $time) {
			$int+=self::$CONVERT_TIMEOUT[$unit]*$time;
		}
		return $int;
	}
	
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
	 * @param string $id user Id to retrieve
	 * @return SsoUser
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

	public function isEnabled() {
		return $this->state === self::STATE_ENABLED;
	}
	
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
	
	public static function getInitUser() {
		global $DB;

		$initUser = new SsoUser();
		
		$initUser->id = SSO_DB_USER;
		$initUser->name = SSO_DB_USER;
		$initUser->state = SsoUser::STATE_ENABLED;
		
		$q = new Query(Dual::meta());
		$q->select(SqlExpr::func('PASSWORD', SSO_DB_PASS)->privateBinds(), 'pass');
		$initUser->password = \salt\first($DB->execQuery($q)->data)->pass;
		
		$q = new Query(SsoAuthMethod::meta());
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
