<?php namespace sso;

/**
 * @property string id
 * @property string name
 */
class AuthUser {

	private $__logged = FALSE;
	private $__error = NULL;
	private $__local = FALSE;
	private $__state = SsoUser::STATE_DISABLED;
	private $__authFrom = NULL;

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
	
	private static function toPHPName($name) {
		$name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
		if (is_numeric(substr($name, 0, 1))) {
			$name = '_'.$name;
		}

		return $name;
	}
	
	public function setAuthFrom($authFrom) {
		$this->__authFrom = $authFrom;
	}
	public function getAuthFrom() {
		return $this->__authFrom;
	}
	public function setState($state) {
		$this->__state = $state;
	}
	
	public function getState() {
		return $this->__state;
	}
	
	public function setError($message) {
		$this->__error = $message;
	}
	
	public function logged($logged = TRUE) {
		$this->__logged = $logged;
	}
	
	public function local($local = TRUE) {
		$this->__local = $local;
	}
	
	public function isLogged() {
		return $this->__logged;
	}
	
	public function getError() {
		return $this->__error;
	}
	
	public function isLocal() {
		return $this->__local;
	}
}