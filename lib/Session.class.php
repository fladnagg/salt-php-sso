<?php
/**
 * Session class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

use salt\In;

/**
 * Session class
 *
 * @property int $SSO_TIMEOUT timeout of the session cookie
 * @property mixed[] $SSO_GLOBALS list of variables to register/define in application handlers
 * @property mixed[] $SSO_REDIRECT data (appli, url, etc...) for redirect after login
 * @property mixed[] $SSO_CREDENTIALS list of credentials applications for current user
 * @property string $SSO_LOGIN user id
 * @property boolean $SSO_checkIP TRUE if we have to check IP on each page
 * @property string $SSO_lastIP the last IP address
 * @property boolean $SSO_checkAgent TRUE if we have to check User Agent on each page
 * @property string $SSO_lastAgent the last User Agent
 * @property boolean $SSO_ADMIN TRUE if the user is a SSO administrator
 * @property AuthUser $SSO_USER the full AuthEntry object that represent the user
 * @property string $SSO_USERNAME the user name
 * @property SsoProfil $SSO_PROFIL_PREVIEW the profil to use for the next page (preview theme feature)
 * @property string $SSO_RETURN_URL the previous page to return if user access to SSO from another application
 * @property int $SSO_lastTime last access time to the session
 * @property boolean $SSO_LOCAL_AUTH true if user is logged via local auth mecanism, allow user to change their password
 */
class Session {

	/** name of SSO Session */
	const SSO_SESSION_NAME = 'SSO_SESSID';

	/** list of session attributes default values : session key => value */
	private static $SESSION_ATTRIBUTES = array(
		'session.gc_probability' => 1,
		'session.gc_divisor' => 100,
		'session.gc_maxlifetime' => 604800, // = 60*60*24*7 = 7 days in second = max session lifetime (7 days = SsoUser::$TIME_RANGE['d'])
	);

	/**
	 * @var mixed[] SSO session data, key => value */
	private $data = array();
	/**
	 * @var Session instance */
	private static $instance = NULL;
	/**
	 * @var mixed[] old session data, key => value */
	private $old = array();

	/**
	 * Retrieve SSO instance
	 * @return Session new or current instance
	 */
	public static function getInstance() {
		if (self::$instance === NULL) {
			self::$instance = new Session();
		}
		return self::$instance;
	}

	/**
	 * Initialize new session
	 * @throws BusinessException if header already sent
	 */
	private function __construct() {
		if (headers_sent($file, $num)) {
			throw new BusinessException(L::error_session_header_sent($file, $num));
		}

		$this->savePreviousSession();

		// configure SSO session
		session_save_path(SESSION_PATH);
		session_name(self::SSO_SESSION_NAME);
		session_set_cookie_params(self::$SESSION_ATTRIBUTES['session.gc_maxlifetime'], '/');
		foreach(self::$SESSION_ATTRIBUTES as $k => $v) {
			ini_set($k, $v);
		}

		session_start();

		$this->data =& $_SESSION;
		$Input = In::getInstance();
		if ($Input->G->ISSET->sso_logout) {
			$this->logout();
		}

		if (!isset($this->SSO_GLOBALS)) {
			$this->SSO_GLOBALS=array();
		}

		// restore global application variables
		foreach($this->SSO_GLOBALS as $k => $v) {
			global ${$k};
			${$k} = $v;
		}
	}

	/**
	 * save previous session
	 */
	private function savePreviousSession() {
		$this->old = array(
				'name' => session_name(),
				'id' => session_id(),
				'save' => session_save_path(),
		);

		foreach(self::$SESSION_ATTRIBUTES as $k => $v) {
			$this->old[$k] = ini_get($k);
		}
		$this->old['cookie'] = session_get_cookie_params();

		if ($this->old['id'] !== '') {
			$this->old['data'] = $_SESSION;
			// multiple request on same session can fail to destroy session, but not an issue : the session is destroyed by one request
			// without @ we have annoying and useless "Session object destruction failed" messages
			@session_destroy();
		}
	}

	/**
	 * restore previous saved session
	 */
	private function restorePreviousSession() {
		session_save_path($this->old['save']);
		session_name($this->old['name']);
		foreach(self::$SESSION_ATTRIBUTES as $k => $v) {
			ini_set($k, $this->old[$k]);
		}

		session_set_cookie_params(
			$this->old['cookie']['lifetime'],
			$this->old['cookie']['path'],
			$this->old['cookie']['domain'],
			$this->old['cookie']['secure'],
			$this->old['cookie']['httponly']
		);

		if ($this->old['id'] !== '') {
			session_id($this->old['id']);
		} else {
			/***
			 * If we don't have a session before SSO, we don't have 'real' session_id to set.
			 * PHP don't provide function to "reset" session_id to a default value. The only way to
			 * retrieve session_id is to open another session, and we don't want to do that.
			 *
			 * If we don't do nothing, the next session started by application after SSO will have the
			 * SAME session id than the sso. The storage is not at the same place, but it's not great
			 * for comprehension.
			 *
			 * For avoid that, we have to generate a custom session_id. All methods with md5 and microtime()
			 * or remote IP are predictable and not secured... so, we use md5 on a known secure string...
			 * the previous session_id !
			 */
			session_id(md5(session_id()));
		}

		if (isset($this->old['data'])) {
			session_start();
			$_SESSION = $this->old['data'];
		} else {
			session_unset();
			$_SESSION = array();
		}

	}

	/**
	 * Logout and remove SSO session
	 */
	public function logout() {
		// multiple request on same session can fail to destroy session, but not an issue : the session is destroyed by one request
		// without @ we have annoying and useless "Session object destruction failed" messages
		@session_destroy();
		session_start();
		session_regenerate_id(TRUE);
		$this->data =& $_SESSION;
	}

	/**
	 * Register logged user in session
	 * @param int $timeout timeout of the session
	 * @param AuthUser $authUser user of logger user
	 * @param SsoUser $ssoUser user in SSO database
	 * @param string[] $credentials list of allowed applications for user : applicationPath => applicationHandlerClassName
	 */
	public function login($timeout, AuthUser $authUser, SsoUser $ssoUser, array $credentials) {

		if ($timeout === 0) {
			// session only cookie
			setcookie(self::SSO_SESSION_NAME.'0', session_id(), 0, '/');
		}
		$this->SSO_TIMEOUT = $timeout;
		$this->SSO_USER = $authUser;

		$this->SSO_LOGIN = $ssoUser->id;
		$this->SSO_USERNAME = $ssoUser->name;
		$this->SSO_ADMIN = $ssoUser->admin;
		$this->SSO_CREDENTIALS = $credentials;

		$this->SSO_checkIP = $ssoUser->restrictIP;
		$this->SSO_checkAgent = $ssoUser->restrictAgent;

		$Input = In::getInstance();
		$this->SSO_lastIP = $Input->S->RAW->REMOTE_ADDR;
		$this->SSO_lastAgent = $Input->S->RAW->HTTP_USER_AGENT;
		$this->SSO_lastTime = time();
	}

	/**
	 * Freeze current session data and restore previous session
	 *
	 * All SSO session data can still be retrieve from Session instance but cannot be modified
	 */
	public function freeze() {
		$data = $this->data;

		/**
		 * We wanted to break link between $this->data and $_SESSION.<br/>
		 * The only way to do that is to unset() one of those variable, but :<br/>
		 * - We cannot call unset on $_SESSION, because it break the $_SESSION register in $_GLOBALS<br/>
		 * - We cannot call unset on a property because it really unset the declared property, and subsequent call<br/>
		 *   of __get and __set produce an error "Indirect modification of overloaded property Session::$data has no effect"
		 * <br/>
		 * So, we will first replace the link with another, and then unset the dummy variable<br/>
		 * <br/>
		 * After this method, session data can still be accessed with this Session object but without being linked to
		 *   $_SESSION anymore
		 */
		$dummy = 1;
		$this->data =& $dummy;
		unset($dummy);

		session_write_close();

		$this->restorePreviousSession();

		$this->data = $data;
	}

	/**
	 * Retrieve a session variable
	 * @param string $var variable name
	 * @return mixed value
	 */
	public function __get($var) {
		if (isset($this->data[$var])) {
			return $this->data[$var];
		}
		return NULL;
	}

	/**
	 * Test if variable exists
	 * @param string $var variable name
	 * @return boolean TRUE if variable exists in session, FALSE otherwise
	 */
	public function __isset($var) {
		return isset($this->data[$var]);
	}

	/**
	 * Remove a variable from session
	 * @param string $var variable name
	 */
	public function __unset($var) {
		unset($this->data[$var]);
	}

	/**
	 * Set a variable in session
	 * @param string $var variable name
	 * @param mixed $value variable value
	 */
	public function __set($var, $value) {
		$this->data[$var] = $value;
	}

	/**
	 * display session content (debug only)
	 */
	public function dump() {
		echo 'NAME/ID : '.session_name().' = '.session_id().'<br/>';
		var_dump($this->data);
	}
}