<?php namespace sso;

use salt\DBHelper;
use salt\Query;
use salt\SqlExpr;

class SsoAuthMethodLocal implements SsoAuthMethodInterface {

	public function getOptions($value = NULL) {
		return array();
	}
	
	public function search($user, \stdClass $options) {
		$db = DBHelper::getInstance('SSO');
		
		$authUser = NULL;
		$ssoUser = SsoUser::getById($db, $user);

		if ($ssoUser !== NULL) {
			$authUser = new AuthUser($ssoUser->id, $ssoUser->name, array());
		}
		return $authUser;
	}
	
	public function auth($user, $pass, \stdClass $options) {
		
		$db = DBHelper::getInstance('SSO');
		
		$authUser = $this->search($user, $options);

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
