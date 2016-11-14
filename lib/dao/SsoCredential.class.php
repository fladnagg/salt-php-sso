<?php namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\FieldType;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;

/**
 * @property int id
 * @property int appli
 * @property string user
 * @property int appli_group
 * @property int user_group
 * @property int status
 * @property string description
 */
class SsoCredential extends Base implements SsoAdministrable {

	const STATUS_ASKED = 0;
	const STATUS_REFUSED = 1;
	const STATUS_VALIDATED = 2;

	private $lastError = NULL;

	protected function metadata() {
		parent::registerId('id');
		parent::registerTableName('sso_credential');
		parent::registerHelper(__NAMESPACE__.'\SsoCredentialViewHelper');

		return array(
				Field::newNumber('id', 'ID')->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newNumber('appli', 'Application', TRUE),
				Field::newText(	'user', 	'Utilisateur', TRUE)->sqlType('VARCHAR(32)'),
				Field::newNumber('appli_group', 'Groupe d\'application', TRUE),
				Field::newNumber('user_group', 'Groupe d\'utilisateur', TRUE),
				Field::newNumber('status', 'Status', FALSE, self::STATUS_ASKED, array(
						self::STATUS_ASKED=>'Demandée',
						self::STATUS_REFUSED=>'Refusée',
						self::STATUS_VALIDATED=>'Validée'
				)),
				Field::newText('description', 'Description', TRUE)->sqlType('TEXT')
						->displayOptions(array('type' => 'textarea', 'cols' => 50, 'rows' => 3)),
		);
	}

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
		
		$q = new Query(SsoCredential::meta());

		$qApp = new Query(SsoGroupElement::meta());
		$qApp->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
		$q->join($qApp, 'appli_group', '=', $qApp->getField('group_id'), 'LEFT OUTER');
		
		$qUser = new Query(SsoGroupElement::meta());
		$qUser->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
		$q->join($qUser, 'user_group', '=', $qUser->getField('group_id'), 'LEFT OUTER');

		$qAppli = new Query(SsoAppli::meta());
		$qAppli->selectField('path')->distinct();
		$qAppli->selectField('handler');
		$q->join($qAppli, 'appli', '=', $qAppli->getField('id'));
		$q->joinOnOr($qAppli, $qAppli->getField('id'), '=', $qApp->getField('ref_id'));
		
		$q2 = $q->getSubQuery();
		$q2->whereOr('user', '=', $user);
		$q2->whereOr($qUser->getField('ref_id'), '=', $user);
		$q->whereAndQuery($q2);

		$q->whereAnd('status', '=', self::STATUS_VALIDATED);

		$creds = array();
		foreach($DB->execQuery($q)->data as $row) {
			$creds[$row->path] = $row->handler;
		}
		return $creds;
	}

	public static function getDemandes($user) {
		$DB = DBHelper::getInstance('SSO');

		$q = new Query(SsoCredential::meta());
		$q->selectFields(array('id', 'appli', 'description', 'status'));
		$q->whereAnd('user', '=', $user);
		$q->whereAnd('appli', 'IS', SqlExpr::value(NULL)->not());

		return $DB->execQuery($q);
	}

	public static function search(array $criteres, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');

		$cred = SsoCredential::meta();
		$q = new Query($cred);
		$q->select($q->getField('id')->distinct());
		$q->selectFields(array('user', 'user_group', 'appli', 'appli_group', 'status', 'description'));
		
		foreach($criteres as $k => $v) {
			if ($v !== '') {
				if ($k === 'user') {
				
					$qUserGroup = new Query(SsoGroupElement::meta()); // Tout les groupes d'utilisateurs liés
					$qUserGroup->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
					$q->join($qUserGroup, 'user_group', '=', $qUserGroup->getField('group_id'), 'LEFT OUTER');
				
					$qUser = new Query(SsoUser::meta()); // Tout les utilisateurs liés a l'autorisation ou a un groupe
					$q->join($qUser, $qUser->getField('id'), 'LIKE', '%'.$v.'%');
					$qUserLink = $qUser->getSubQuery();
					$qUserLink->whereOr('id', '=', $q->getField('user'));
					$qUserLink->whereOr('id', '=', $qUserGroup->getField('ref_id'));
					$q->joinOnAndQuery($qUser, $qUserLink);
				
				} else if ($k == 'appli') {
				
					$qAppliGroup = new Query(SsoGroupElement::meta()); // Tout les groupes d'applis liés
					$qAppliGroup->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
					$q->join($qAppliGroup, 'appli_group', '=', $qAppliGroup->getField('group_id'), 'LEFT OUTER');
				
					$qAppli = new Query(SsoAppli::meta()); // Toutes les applis liées a l'autorisation ou a un groupe
					$q->join($qAppli, $qAppli->getField('name'), 'LIKE', '%'.$v.'%');
					$qAppliLink = $qAppli->getSubQuery();
					$qAppliLink->whereOr('id', '=', $q->getField('appli'));
					$qAppliLink->whereOr('id', '=', $qAppliGroup->getField('ref_id'));
					$q->joinOnAndQuery($qAppli, $qAppliLink);
					
				} else {
					
					if ($k === 'appli_id') {
						$k = 'appli';
					}
					
					$field = $cred->getField($k);
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
	

	public function lastError() {
		return $this->lastError;
	}
	
	public function validate() {
	
		// xor
		if (($this->user === NULL) === ($this->user_group === NULL)) {
			$this->lastError = 'Un utilisateur ou un groupe d\'utilisateur doit être indiqué';
			return FALSE;
		}

		// xor
		if (($this->appli === NULL) === ($this->appli_group === NULL)) {
			$this->lastError = 'Une application ou un groupe d\'application doit être indiqué';
			return FALSE;
		}
		
		
		return TRUE;
	}
}



