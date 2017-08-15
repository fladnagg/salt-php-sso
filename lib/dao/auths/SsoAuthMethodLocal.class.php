<?php
/**
 * SsoAuthMethodLocal class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao\auths
 */
namespace sso;

use salt\DBHelper;
use salt\Query;
use salt\SqlExpr;

/**
 * Local method : will use SSO user table
 */
class SsoAuthMethodLocal implements SsoAuthMethodInterface {

	/**
	 * {@inheritDoc}
	 * @param mixed[] $value current values, as key => value, each key is a Field name of a previous call.
	 * 	Can be used for display example of current option values in option description for example.
	 * @see \sso\SsoAuthMethodInterface::getOptions()
	 */
	public function getOptions($value = NULL) {
		return array();
	}

	/**
	 * {@inheritDoc}
	 * @param string $search search
	 * @param \stdClass $options auth method options
	 * @see \sso\SsoAuthMethodInterface::search()
	 */
	public function search($search, \stdClass $options) {
		$db = DBHelper::getInstance('SSO');

		$authUsers = array();

		if (is_array($search)) {
			return $authUsers; // not implemented : search on other fields
		}

		$ssoUser = SsoUser::getById($db, $search);

		if ($ssoUser !== NULL) {
			$authUsers[] = new AuthUser($ssoUser->id, $ssoUser->name, array());
		}
		return $authUsers;
	}

	/**
	 * {@inheritDoc}
	 * @param string $user user
	 * @param string $pass clear password
	 * @param stdClass $options all auth method options
	 * @see \sso\SsoAuthMethodInterface::auth()
	 */
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
