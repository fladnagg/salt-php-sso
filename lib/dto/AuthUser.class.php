<?php
/**
 * AuthUser class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dto
 */
namespace sso;

/**
 * User found in an auth method
 *
 * User properties defined in auth method are added as object dynamic properties.<br/>
 * For example, if an LDAP has "dn", "objectclass" and "mail" entries, AuthUser will define $dn, $objectclass and $mail properties :<br/>
 * echo $authUser->dn, $authUser->objectclass, $authUser->mail;
 *
 * @property string $id
 * @property string $name
 */
class AuthUser {

	/**
	 * @var boolean TRUE if user is logged (password is valid) */
	private $__logged = FALSE;
	/**
	 * @var string error message during auth */
	private $__error = NULL;
	/**
	 * @var boolean TRUE if user is an SSO local user */
	private $__local = FALSE;
	/**
	 * @var int state of user : SsoUser::STATE_* */
	private $__state = SsoUser::STATE_DISABLED;
	/**
	 * @var string auth method ID used for authenticate the user */
	private $__authFrom = NULL;

	/**
	 * Build a new AuthUser
	 * @param string $userId User ID
	 * @param string $userName User display name
	 * @param mixed[] $others Other fields to keep. All fields will be registered in class as dynamic properties.
	 */
	public function __construct($userId, $userName, array $others) {
		$this->id = $userId;
		$this->name = $userName;
		unset($others['id']);
		unset($others['name']);

		foreach($others as $name => $data) {
			// convert name to valid PHP name
			$name = self::toPHPName($name);
			$this->$name = $data;
		}
	}

	/**
	 * Convert a string in valid PHP property name
	 * @param string $name field name, from auth source
	 * @return string valid PHP property name
	 */
	private static function toPHPName($name) {
		$name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
		if (is_numeric(substr($name, 0, 1))) {
			$name = '_'.$name;
		}

		return $name;
	}

	/**
	 * Set the auth source
	 * @param string $authFrom ID of the auth method
	 */
	public function setAuthFrom($authFrom) {
		$this->__authFrom = $authFrom;
	}
	/**
	 * Retrieve the auth source
	 * @return string ID of the auth method
	 */
	public function getAuthFrom() {
		return $this->__authFrom;
	}
	/**
	 * Set the user state
	 * @param int $state SsoUser::STATE_*
	 */
	public function setState($state) {
		$this->__state = $state;
	}
	/**
	 * Retrieve the user state
	 * @return int SsoUser::STATE_*
	 */
	public function getState() {
		return $this->__state;
	}
	/**
	 * Set an error on AuthUser
	 * @param string $message error message
	 */
	public function setError($message) {
		$this->__error = $message;
	}
	/**
	 * Return error message
	 * @return string the error message
	 */
	public function getError() {
		return $this->__error;
	}
	/**
	 * Set the logged flag in user
	 * @param boolean $logged set to FALSE for not logged, default TRUE
	 */
	public function logged($logged = TRUE) {
		$this->__logged = $logged;
	}
	/**
	 * Set the local flag in user
	 * @param boolean $local set to FALSE for not local, default TRUE
	 */
	public function local($local = TRUE) {
		$this->__local = $local;
	}
	/**
	 * Check user is logged
	 * @return boolean TRUE if user is logged
	 */
	public function isLogged() {
		return $this->__logged;
	}
	/**
	 * Check user is local
	 * @return boolean TRUE if user is authenticated from local auth method
	 */
	public function isLocal() {
		return $this->__local;
	}
}