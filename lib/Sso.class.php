<?php namespace sso;

use salt\DBException;
use salt\InsertQuery;
use salt\Query;
use salt\SqlExpr;
use salt\UpdateQuery;
use salt\Benchmark;

class Sso extends SsoClient {
	/**
	 * Check a login/password. Can send HTTP header for redirect user if needed
	 * @param string $user
	 * @param string $password
	 * @return string error message if any, or NULL if OK.
	 * @throws BusinessException
	 * @throws DBException
	 */
	public function authUser($user, $password, $sessionOnly) {
		global $Input;

		$user = strtolower($user);
		$errorMessage = NULL;

		$ldapUser = NULL;

		$_REQUEST['sso_password'] = NULL;
		$_POST['sso_password'] = NULL;

		try {

			global $DB;
			try {
				$ssoUser = SsoUser::getById($DB, $user);
			} catch (DBException $ex) {
				if ($ex->getSqlStateErrorCode() === '42S02') {
					header('Location: '.SSO_WEB_RELATIVE.'?page=init');
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

			$authUser = NULL;
			$exceptions = array();
			/** @var $auth SsoAuthMethod */
			foreach($auths as $auth) {
				try {
					$authUser = $auth->auth($user, $password);
					if ($authUser === NULL) {
						error_log($auth->name.' : Utilisateur inconnu ['.$user.'] ('.__FILE__.':'.__LINE__.')');
						$exceptions['unknown'] = new BusinessException('Utilisateur inconnu ['.$user.']');
					}
				} catch (BusinessException $ex) {
					error_log($auth->name.' : '.$ex->getMessage().' ('.__FILE__.':'.__LINE__.')');
					$exceptions[] = $ex;
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
						// user found with bad password ? we try database password
						$authUser = $this->authDbUser($user, $password);
						
						if (($authUser === NULL) || !$authUser->isLogged()) {
							$error = '';
							if (($authUser !== NULL) && (strlen($authUser->getError()) > 0)) {
								$error = ' ('.$authUser->getError().')';
							}
							error_log($auth->name.' : Mot de passe incorrect pour '.$user.$error.' ('.__FILE__.':'.__LINE__.')');
							// do not store exception if we have found user : it's a password error
							throw new BusinessException('Mot de passe incorrect pour '.$user.$error);
						}
					} // not logged
				} // user found
			} // each Auth
			
			if (($authUser === NULL) || !$authUser->isLogged()) {
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
		}
		
		return $errorMessage;
	}
	
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
	
	public function checkCredentials($appli) {
		return parent::checkCredentials($appli);
	}
	
	public function refreshUser($sessionOnly = FALSE) {
		global $Input;

		$ssoUser = SsoUser::findFromId($this->session->SSO_LOGIN);
		$authUser = $this->session->SSO_USER;

		if ($authUser->id === $ssoUser->id) {
			$this->session->login($sessionOnly?0:$ssoUser->timeout, $authUser, $ssoUser, SsoCredential::getAllByUser($this->session->SSO_LOGIN));
			SsoProfil::initProfiles($this);
		}
	}

	private function registerUserLogin($login, AuthUser $user, $sessionOnly) {
		global $Input;

		$this->session->SSO_LOGIN = $login;
		$this->session->SSO_USER = $user;
		$this->session->SSO_USERNAME = $user->name;
		$this->session->SSO_LOCAL_AUTH = $user->isLocal();

		$ssoUser = SsoUser::updateLoginOk($this->session->SSO_LOGIN, $user);
		if ($ssoUser === NULL) {
			header('Location: '.SSO_WEB_RELATIVE.'?page=init');
			die();
		}
		
		$this->refreshUser($sessionOnly);
	}

	public function resumeApplication() {
		$uri = $this->session->SSO_REDIRECT;
		unset($this->session->SSO_REDIRECT);

		$params = array();
		
		if ($this->session->SSO_GET !== NULL) {
			$params = $this->session->SSO_GET;
			unset($this->session->SSO_GET);

			unset($params['sso_logout']);
		}
		
		if (count($params) > 0) {
			$params = http_build_query($params);
			$uri.='?'.$params;
		}

		session_write_close();

		header('Location: '.$uri);
		die();
	}

	public function redirectApplications() {
		header('Location: '.SSO_WEB_RELATIVE.'?page=apps');
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