<?php
/**
 * Sso class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

use salt\DBException;
use salt\InsertQuery;
use salt\Query;
use salt\SqlExpr;
use salt\UpdateQuery;

/**
 * Sso "private" class : can be used in SSO but not in Applications
 */
class Sso extends SsoClient {

	/**
	 * Retrieve all auth methods for a user
	 * @param string $user the user
	 * @return SsoAuthMethod[] list of AuthMethod to use
	 */
	public function authMethods($user) {
		global $DB;
		try {
			$ssoUser = SsoUser::getById($DB, $user);
		} catch (DBException $ex) {
			if ($ex->getSqlStateErrorCode() === '42S02') {
				header('Location: '.SSO_WEB_RELATIVE.'?page=init', true, 303);
				die();
			}
			throw $ex;
		}
		$auths = array();
		if ($ssoUser !== NULL) {
			$q = SsoAuthMethod::query(TRUE);
			$qElem = SsoGroupElement::query();
			$qElem->whereAnd('type', '=', SsoGroupElement::TYPE_AUTH);
			$qElem->whereAnd('group_id', '=', $ssoUser->auth_group); // avoid distinct select
			$q->join($qElem, 'id', '=', $qElem->ref_id, 'LEFT OUTER');
			$q->whereOr($qElem->group_id, '=', $ssoUser->auth_group); 	// by auth_group
			$q->whereOr('id', '=', $ssoUser->auth);						// or by auth
			$q->orderAsc('name');
			$auths = $DB->execQuery($q)->data;
		} else {
			$q = SsoAuthMethod::query(TRUE);
			$q->whereAnd('default', '=', TRUE);
			$q->orderAsc('name');
			$auths = $DB->execQuery($q)->data;
		}

		if (count($auths) === 0) {
			$auths[]=SsoAuthMethod::getLocal();
		}
		return $auths;
	}

	/**
	 * Check a login/password. Can send HTTP header for redirect user if needed
	 * @param string $user the user
	 * @param string $password the password
	 * @param boolean $sessionOnly TRUE for restrict login to session
	 * @return string error message if any, or NULL if OK.
	 * @throws BusinessException if all auth method failed, return the first exception
	 */
	public function authUser($user, $password, $sessionOnly) {
		global $Input;

		$user = strtolower($user);
		$errorMessage = NULL;

		$ldapUser = NULL;

		$_REQUEST['sso_password'] = NULL;
		$_POST['sso_password'] = NULL;

		try {
			$auths = $this->authMethods($user);

			$authUser = NULL;
			$exceptions = array();
			/** @var $auth SsoAuthMethod */
			foreach($auths as $auth) {
				try {
					$authUser = $auth->auth($user, $password);
					if ($authUser === NULL) {
						//error_log($auth->name.' : Utilisateur inconnu ['.$user.'] ('.__FILE__.':'.__LINE__.')');
						if (!isset($exceptions['unknown'])) {
							$exceptions['unknown'] = new BusinessException(L::error_login_user_unknown($user));
						}
						$exceptions['unknown']->addData($auth->name);
					}
				} catch (BusinessException $ex) {
					//error_log($auth->name.' : '.$ex->getMessage().' ('.__FILE__.':'.__LINE__.')');
					if (!isset($exceptions[$ex->getMessage()])) {
						$exceptions[$ex->getMessage()] = new BusinessException($ex->getMessage(), 0, $ex);
					}
					$exceptions[$ex->getMessage()]->addData($auth->name);
				}

				if ($authUser !== NULL) {
					if ($authUser->isLogged()) {

						$authUser->setAuthFrom($auth->id);

						if ($auth->type === SsoAuthMethod::TYPE_LOCAL) {
							$authUser->local();
							$authUser->setState(SsoUser::STATE_ENABLED);
						} else if ($auth->create) {
							$authUser->setState(SsoUser::STATE_ENABLED);
						} else {
							$authUser->setState(SsoUser::STATE_TO_VALIDATE);
						}
						break;
					} else {

						if (ALLOW_DB_USER_LOGIN) {
							// user found with bad password ? we try database password
							$authUser = $this->authDbUser($user, $password);
						}

						if (($authUser === NULL) || !$authUser->isLogged()) {
							$error = '';
							if (($authUser !== NULL) && (strlen($authUser->getError()) > 0)) {
								$error = ' ('.$authUser->getError().')';
							}
							// error_log($auth->name.' : Mot de passe incorrect pour '.$user.$error.' ('.__FILE__.':'.__LINE__.')');
							// do not store exception if we have found user : it's a password error
							$ex = new BusinessException(L::error_login_bad_password($user).$error);
							$ex->addData($auth->name);
							throw $ex;
						}
					} // not logged
				} // user found
			} // each Auth

			if (ALLOW_DB_USER_LOGIN && (($authUser === NULL) || !$authUser->isLogged())) {
				// last chance : we try database user
				$dbAuthUser = $this->authDbUser($user, $password);
				if (($dbAuthUser !== NULL) && $dbAuthUser->isLogged()) {
					$authUser = $dbAuthUser;
				}
			}

			if (($authUser !== NULL) && $authUser->isLogged()) {
				$this->registerUserLogin($user, $authUser, $sessionOnly);
			} else {
				SsoUser::updateLoginFailed($user);
			}
			if (count($exceptions) > 0) { // we cannot display all messages : 3 auth methods will throw "Unknown user" for example
				throw \salt\first($exceptions);
			}

			return NULL;
		} catch(BusinessException $be) {
			$errorMessage = $be->getMessage();
			$datas = $be->getData();
			if (($datas !== NULL) && (count($datas) > 0)) {
				$errorMessage.=' ('.implode(', ', $datas).')';
			}
		}

		return $errorMessage;
	}

	/**
	 * Check user with database credentials
	 * @param string $user the user
	 * @param string $password the password
	 * @return AuthUser|NULL the AuthUser for database user
	 */
	private function authDbUser($user, $password) {
		global $DB;

		$authUser = NULL;
		if (($user === SSO_DB_USER) && ($password === SSO_DB_PASS)) {
			$local = SsoAuthMethod::getLocal();
			$authUser = $local->auth($user, $password); // try to log
			if ($authUser === NULL) {
				$dbUser = SsoUser::getInitUser();

				$q = new InsertQuery($dbUser);
				$DB->execInsert($q);

				$authUser = $local->auth($user, $password);

			} else if (!$authUser->isLogged()) { // password have been modified : update to config password
				$q = SsoUser::updateQuery();
				$q->whereAnd('id', '=', SSO_DB_USER);
				$q->set('password', SqlExpr::_PASSWORD(SSO_DB_PASS)->privateBinds());
				$q->set('admin', TRUE);
				$q->set('timeout', SsoUser::DEFAULT_TIMEOUT);

				$DB->execUpdate($q);

				$authUser = $local->auth($user, $password);
			}
		}
		return $authUser;
	}

	/**
	 * {@inheritDoc}
	 * @param string $appli application path
	 * @see \sso\SsoClient::checkCredentials()
	 */
	public function checkCredentials($appli) {
		return parent::checkCredentials($appli);
	}

	/**
	 * Refresh user data after credentials change for example
	 * @param boolean $sessionOnly TRUE for restrict login to session
	 */
	public function refreshUser($sessionOnly = FALSE) {
		global $Input;

		$ssoUser = SsoUser::findFromId($this->session->SSO_LOGIN);
		$authUser = $this->session->SSO_USER;

		if ($authUser->id === $ssoUser->id) {
			$this->session->login($sessionOnly?0:$ssoUser->timeout, $authUser, $ssoUser, SsoCredential::getAllByUser($this->session->SSO_LOGIN));
			SsoProfil::initProfiles($this);
		}
	}

	/**
	 * Register a logged user in session
	 * @param string $login user ID
	 * @param AuthUser $user the AuthUser object
	 * @param boolean $sessionOnly TRUE for restrict logion to session
	 */
	private function registerUserLogin($login, AuthUser $user, $sessionOnly) {
		global $Input;

		$this->session->SSO_LOGIN = $login;
		$this->session->SSO_USER = $user;
		$this->session->SSO_USERNAME = $user->name;
		$this->session->SSO_LOCAL_AUTH = $user->isLocal();

		$ssoUser = SsoUser::updateLoginOk($this->session->SSO_LOGIN, $user);
		if ($ssoUser === NULL) {
			header('Location: '.SSO_WEB_RELATIVE.'?page=init', true, 303);
			die();
		}

		$language = $ssoUser->lang;
		if ($language !== NULL) {
			Locale::set($language);
		}

		$this->refreshUser($sessionOnly);
	}

	/**
	 * Redirect to application list
	 */
	public function redirectApplications() {
		header('Location: '.SSO_WEB_RELATIVE.'?page=apps', true, 303);
		die();
	}

	/**
	 * Check a server is listening
	 * @param string $host the host to check
	 * @param int $port the port to use
	 * @param int $timeout timeout in seconds, 1 by default
	 * @return TRUE si the server is listening on that port, FALSE otherwise
	 */
	public static function pingServer($host, $port, $timeout=1) {
		$op = NULL;
		// clean protocol
		$host = \salt\last(explode('://', $host, 2));
		try {
			ErrorHandler::disable();
			$op = @fsockopen($host, $port, $errno, $errstr, $timeout);
			ErrorHandler::init();
			if ($op === FALSE) {
				return FALSE;
			}
		} catch (\Exception $ex) {
			ErrorHandler::init();
			if (is_resource($op)) @fclose($op);
			return FALSE;
		}
		ErrorHandler::init();
		if (is_resource($op)) @fclose($op);
		return TRUE;
	}
}
