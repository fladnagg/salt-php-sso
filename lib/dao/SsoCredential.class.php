<?php
/**
 * SsoCredential class
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
 * SsoCredential
 *
 * @property int $id
 * @property int $appli
 * @property string $user
 * @property int $appli_group
 * @property int $user_group
 * @property int $status
 * @property string $description
 */
class SsoCredential extends Base implements SsoAdministrable {

	/** Credential asked (not active) */
	const STATUS_ASKED = 0;
	/** Credential refused (not active) */
	const STATUS_REFUSED = 1;
	/** Credential validated (active) */
	const STATUS_VALIDATED = 2;

	/**
	 * @var string last error message of validate() */
	private $lastError = NULL;

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
		parent::registerHelper(__NAMESPACE__.'\SsoCredentialViewHelper');

		self::MODEL()
			->registerId('id')
			->registerTableName('sso_credential')
			->registerFields(
				Field::newNumber('id', 			L::field_id)->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newNumber('appli', 		L::field_application, TRUE),
				Field::newText(	'user', 		L::field_user, TRUE)->sqlType('VARCHAR(32)'),
				Field::newNumber('appli_group', L::field_appli_group, TRUE),
				Field::newNumber('user_group', 	L::field_user_group, TRUE),
				Field::newNumber('status', 		L::field_status, FALSE, self::STATUS_ASKED, array(
						self::STATUS_ASKED => 		L::credential_status_asked,
						self::STATUS_REFUSED => 	L::credential_status_refused,
						self::STATUS_VALIDATED =>	L::credential_status_validated,
				)),
				Field::newText('description', L::field_description, TRUE)->sqlType('TEXT')
						->displayOptions(array('type' => 'textarea', 'cols' => 50, 'rows' => 3))
		);
	}

	/**
	 * Retrieve all applications available for user
	 * @param string $user User ID
	 * @return string[] as applicationPath => applicationHandlerClassName
	 */
	public static function getAllByUser($user) {
		$DB = DBHelper::getInstance('SSO');

// 		select distinct app.path
// 		, app.handler
// 		from sso_credential cred
// 		left outer join sso_group_element gapp on gapp.group_id = cred.appli_group and gapp.type = 2
// 		left outer join sso_group_element gusr on gusr.group_id = cred.user_group and gusr.type = 1
// 		inner join sso_appli app on app.id in (cred.appli, gapp.ref_id)
// 		where '<id>' in (cred.user, gusr.ref_id)
// 		and status = 2

		$q = SsoCredential::query();

		$qApp = SsoGroupElement::query();
		$qApp->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
		$q->join($qApp, 'appli_group', '=', $qApp->group_id, 'LEFT OUTER');

		$qUser = SsoGroupElement::query();
		$qUser->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
		$q->join($qUser, 'user_group', '=', $qUser->group_id, 'LEFT OUTER');

		$qAppli = SsoAppli::query();
		$qAppli->selectField('path')->distinct();
		$qAppli->selectField('handler');
		$q->join($qAppli, 'appli', '=', $qAppli->id);
		$q->joinOnOr($qAppli, $qAppli->id, '=', $qApp->ref_id);

		$q2 = $q->getSubQuery();
		$q2->whereOr('user', '=', $user);
		$q2->whereOr($qUser->ref_id, '=', $user);
		$q->whereAndQuery($q2);

		$q->whereAnd('status', '=', self::STATUS_VALIDATED);

		$creds = array();
		foreach($DB->execQuery($q)->data as $row) {
			$creds[$row->path] = $row->handler;
		}
		return $creds;
	}

	/**
	 * Retrieve all pending user requests
	 * @param string $user user ID
	 * @return \salt\DBResult all pending user requests (not validated requests)
	 */
	public static function getPendingRequests($user) {
		$DB = DBHelper::getInstance('SSO');

		$q = SsoCredential::query();
		$q->selectFields(array('id', 'appli', 'description', 'status'));
		$q->whereAnd('user', '=', $user);
		$q->whereAnd('appli', 'IS', SqlExpr::value(NULL)->not());
		$q->whereAnd('status', '!=', self::STATUS_VALIDATED);

		return $DB->execQuery($q);
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $criteria criteria as key => value
	 * @param Pagination $pagination pagination object
	 * @see \sso\SsoAdministrable::search()
	 */
	public static function search(array $criteria, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');

		$q = SsoCredential::query();
		$q->select($q->id->distinct());
		$q->selectFields(array('user', 'user_group', 'appli', 'appli_group', 'status', 'description'));

		foreach($criteria as $k => $v) {
			if ($v !== '') {
				if ($k === 'user') {

					$qUserGroup = SsoGroupElement::query(); // All user linked groups
					$qUserGroup->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
					$q->join($qUserGroup, 'user_group', '=', $qUserGroup->group_id, 'LEFT OUTER');

					$qUser = SsoUser::query(); // All users linked to credential or a group
					$q->join($qUser, $qUser->id, 'LIKE', '%'.$v.'%');
					$qUserLink = $qUser->getSubQuery();
					$qUserLink->whereOr('id', '=', $q->user);
					$qUserLink->whereOr('id', '=', $qUserGroup->ref_id);
					$q->joinOnAndQuery($qUser, $qUserLink);

				} else if ($k == 'appli') {

					$qAppliGroup = SsoGroupElement::query(); // All linked application groups
					$qAppliGroup->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
					$q->join($qAppliGroup, 'appli_group', '=', $qAppliGroup->group_id, 'LEFT OUTER');

					$qAppli = SsoAppli::query(); // All application linked to credential or a group
					$q->join($qAppli, $qAppli->name, 'LIKE', '%'.$v.'%');
					$qAppliLink = $qAppli->getSubQuery();
					$qAppliLink->whereOr('id', '=', $q->appli);
					$qAppliLink->whereOr('id', '=', $qAppliGroup->ref_id);
					$q->joinOnAndQuery($qAppli, $qAppliLink);

				} else {

					if ($k === 'appli_id') {
						$k = 'appli';
					}
					if ($k === 'user_id') {
						$k = 'user';
					}

					$field = self::MODEL()->$k;
					if ($field->type === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					} else if ($field->type === FieldType::NUMBER) {
						$q->whereAnd($k, '=', $v);
					}
				}
			}
		}
		$q->orderDesc('id');

		return $DB->execQuery($q, $pagination);
	}

	/**
	 * Return last validate() error
	 * @return string error message
	 */
	public function lastError() {
		return $this->lastError;
	}

	/**
	 * Check object values
	 * @return boolean TRUE if values are valid. FALSE otherwise
	 */
	public function validate() {

		// xor
		if (($this->user === NULL) === ($this->user_group === NULL)) {
			$this->lastError = L::error_credential_user_missing;
			return FALSE;
		}

		// xor
		if (($this->appli === NULL) === ($this->appli_group === NULL)) {
			$this->lastError = L::error_credential_app_missing;
			return FALSE;
		}

		return TRUE;
	}
}



