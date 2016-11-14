<?php namespace sso;

interface SsoAuthMethodInterface {

	public function getOptions($value = NULL);

	/**
	 * Try to authenticate a user
	 * 
	 * @param string $user user
	 * @param string $pass clear password
	 * @param stdClass $options all auth method options
	 * @return AuthUser|NULL an AuthUser if user found or NULL otherwise.<br/> 
	 * 		If password is OK, call AuthUser->logged() before return.<br/>
	 * 		If an error occured, throw an exception or set an error on user with AuthUser->addError(errorMessage). The errorMessage will be displayed after bad password error.  
	 */
	public function auth($user, $pass, \stdClass $options);
	
}
