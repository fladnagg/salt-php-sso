<?php namespace sso;

use salt\DBHelper;
use salt\Query;
use salt\SqlExpr;

class SsoAuthMethodLocal implements SsoAuthMethodInterface {

	public function getOptions($value = NULL) {
		return array();
	}
	
	public function search($search, \stdClass $options) {
		$db = DBHelper::getInstance('SSO');
		
		$authUsers = array();
		
		if (is_array($search)) {
			return $authUsers; // not implemented : search on other fields
		}

		$ssoUser = SsoUser::getById($db, $user);

		if ($ssoUser !== NULL) {
			$authUsers[] = new AuthUser($ssoUser->id, $ssoUser->name, array());
		}
		return $authUsers;
	}
	
	public function auth($user, $pass, \stdClass $options) {
		
		$db = DBHelper::getInstance('SSO');
		
		$authUser = \salt\first($this->search($user, $options));

		if ($authUser !== NULL) {
			$q = SsoUser::query();
			$q->whereAnd('id', '=', $user);
			$q->whereAnd('password', '=', SqlExpr::_PASSWORD($pass)->privateBinds());

			$nb = $db->execCountQuery($q);

			if ($nb === 1) {
				$authUser->logged();
			}
		}

		return $authUser;
	}
	
	
}
