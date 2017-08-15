<?php
/**
 * SsoAuthMethodInterface interface
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao\auths
 */
namespace sso;

use salt\Field;

/**
 * Interface for Authentication Methods
 */
interface SsoAuthMethodInterface {

	/**
	 * Options of the method
	 * @param mixed[] $value current values, as key => value, each key is a Field name of a previous call.
	 * 	Can be used for display example of current option values in option description for example.
	 * @return Field[] all options
	 */
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

	/**
	 * Search an user
	 *
	 * @param string $search search
	 * @param \stdClass $options auth method options
	 * @return AuthUser[] AuthUser found, can be empty.
	 */
	public function search($search, \stdClass $options);
}
