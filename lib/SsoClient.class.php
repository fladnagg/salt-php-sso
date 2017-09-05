<?php
/**
 * SsoClient class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

/**** Global configuration ****/
$conf = implode(\DIRECTORY_SEPARATOR, array(__DIR__, '..', 'config', 'config.php'));
if (file_exists($conf)) {
	require_once($conf);
} else {
	// do not translate: I18n not loaded yet
	echo 'Configuration file '.$conf.' does not exists';
	die();
}

/**** Version file ****/
include_once implode(\DIRECTORY_SEPARATOR, array(__DIR__, '..', 'version.php'));

/**** Load SALT ****/
set_include_path(get_include_path().\PATH_SEPARATOR.SALT_PATH);
require_once('Salt.class.php');

use salt\Salt;
use salt\In;

/** Relative path of SSO */
define('sso\SSO_RELATIVE', Salt::relativePath(1));

/**** Retrieve language ****/
require_once implode(DIRECTORY_SEPARATOR, array(__DIR__, 'Locale.class.php'));
Locale::init(); // also configure SALT

/**** Configure ****/
Salt::addClassFolder(SSO_RELATIVE.'lib', __NAMESPACE__);
Salt::addClassFolder(SSO_RELATIVE.'plugins'); // not in SSO namespace

/**
 * Check all YAML locale files
 */
// use salt\I18n;
// $i18n = I18n::getInstance('SSO');
// $i18n->check(TRUE); die();
/**
 * Generate all locales classes
 */
// use salt\I18n;
// $i18n = I18n::getInstance('SSO');
// $i18n->generate(TRUE); die();

/**
 * Main SSO class
 *
 * Can by used by client application
 */
class SsoClient {

	/** Auth status : OK */
	const AUTH_OK = 10;
	/** Auth status : session does not exists anymore */
	const AUTH_KO_NO_SESSION = 11;
	/** Auth status : IP check failed */
	const AUTH_KO_IP = 20;
	/** Auth status : User Agent check failed */
	const AUTH_KO_AGENT = 21;
	/** Auth status : session has expired */
	const AUTH_KO_TIMEOUT = 30;
	/** Auth status : error occured during application init */
	const AUTH_KO_INIT_APP = 40;
	/** Auth status : unknown error occured */
	const AUTH_KO_UNKNOWN = 99;

	/**
	 * @var Session current session */
	public $session;
	/**
	 * @var string current application path */
	private $currentApplication = NULL;
	/**
	 * @var SsoClient current instance */
	private static $instance = NULL;

	/**
	 * @var string[] all logout reason : int (self::AUTH_KO_*) => logout reason */
	private static $logoutReasons = array(
		self::AUTH_KO_AGENT => L::logout_reason_invalid, 	// do not give too more informations
		self::AUTH_KO_IP => L::logout_reason_invalid,		// do not give too more informations
		self::AUTH_KO_NO_SESSION => L::logout_reason_not_exists,
		self::AUTH_KO_TIMEOUT => L::logout_reason_expire,
		self::AUTH_KO_UNKNOWN => L::logout_reason_unknown,
		self::AUTH_KO_INIT_APP =>  L::logout_reason_auth,
	);

	/**
	 * Retrieve SsoClient instance
	 * @param string $path web path of SSO. Needed for retrieve SSO from client application
	 * @return SsoClient current instance
	 */
	public static function getInstance($path = NULL) {
		if (self::$instance === NULL) {
			if ($path !== NULL) {
				if (substr($path, -1) != '/') {
					$path.='/';
				}
			} else {
				$path = Salt::webRelativePath(1);
			}
			self::$instance = new static($path);
		}
		return self::$instance;
	}

	/**
	 * Build a new SsoClient instance
	 * @param string $path web path of SSO
	 */
	private function __construct($path = NULL) {
		if (!defined('sso\SESSION_PATH')) {
			/**
			 * @ignore */
			define('sso\SESSION_PATH', session_save_path());
		}
		if (!defined('sso\SSO_WEB_RELATIVE')) {
			/**
			 * @ignore */
			define('sso\SSO_WEB_RELATIVE', $path);
		}
		if (!defined('sso\SSO_WEB_PATH')) {
			$req = implode('/', (explode('/', $_SERVER['REQUEST_URI'], -1)));
			$webPath = substr(Salt::computePath($req, $path), 0, -1);
			/**
			 * @ignore */
			define('sso\SSO_WEB_PATH', $webPath);
		}

		$this->session = Session::getInstance();
	}

	/**
	 * Retrieve the logout reason
	 * @param int $reason self::AUTH_KO_*
	 * @return string logout reason
	 */
	public static function getLogoutReason($reason) {
		if (!array_key_exists($reason, self::$logoutReasons)) {
			$reason = self::AUTH_KO_UNKNOWN;
		}
		return self::$logoutReasons[$reason];
	}

	public function setRedirectUrl($url, $init = FALSE) {
		$redirect = NULL;
		if (($url !== NULL) && !isset($_GET['sso_logout'])) {
			$params = array();
			$data = explode('?', $url, 2);
			if (count($data) > 1) {
				parse_str(\salt\last($data), $params);
			}
			$url = \salt\first($data);
			$url = \salt\first(explode('?', $url, 2));
			$redirect = array('url' => $url, 'params' => $params, 'init' => $init);
		}
		$this->session->SSO_REDIRECT = $redirect;
	}

	/**
	 * Check if user is logged and make appropriate actions
	 *
	 * Redirect user to login page if not
	 *
	 * @param boolean $checkCredentials (default TRUE) FALSE for do not check credentials (only check sso login)
	 * @param boolean $initApplication (default TRUE) FALSE for do not call application handler
	 * @param boolean $redirect (default FALSE) TRUE for redirect to application
	 * @return AuthUser return current user if logged
	 */
	public function auth($checkCredentials = TRUE, $initApplication= TRUE, $redirect = FALSE) {
		$Input = In::getInstance();

		$state = $this->checkUserAuth();

		if ($state !== self::AUTH_OK) {
			if ($checkCredentials && ($this->session->SSO_REDIRECT === NULL) && !$this->isSsoPage()) {
				$this->setRedirectUrl($Input->S->RAW->REQUEST_URI);
			}

			header('Location: '.SSO_WEB_RELATIVE.'index.php?reason='.$state, true, 303);
			die();
		}

		if (!$this->isSsoPage()) {
			$this->session->freeze();
		}
		
		$url = $Input->S->RAW->REQUEST_URI;

		if ($checkCredentials) {

			$from = $this->session->SSO_REDIRECT;
			if ($from !== NULL) {
				$url = $from['url'];
				$initApplication = $from['init'];
			}

			if ($this->isSsoPage($url)) {
				$initApplication = FALSE;

			} else if (!$this->checkCredentials($url)) {
				if ($Input->G->ISSET->from && ($Input->G->RAW->from !== 'client')) {
					header('Location: '.SSO_WEB_RELATIVE.'index.php?page=apps&from=client', TRUE, 303);
				} else {
					header('Location: '.SSO_WEB_RELATIVE.'index.php?page=apps&from=forbidden', TRUE, 303);
				}
				die();
			}

			
			if ($initApplication) {
				try {
					$this->initApplication();
				} catch (\Exception $ex) {
					header('Location: '.SSO_WEB_RELATIVE.'index.php?page=apps&from=init_error', TRUE, 303);
					die();
				}
			}
			
			if ($redirect) {
				$this->resumeApplication();
			}
		}

		return $this->getUser();
	}

	
	
	/**
	 * Redirect to current application (setted by setRedirectUrl() in session->SSO_REDIRECT)
	 * 
	 * If user have credentials for this application, call the init handler and redirect.<br/>
	 * If not, redirect to Application List page
	 */
	private function resumeApplication() {
		
		$from = $this->session->SSO_REDIRECT;
		
		if ($from === NULL) {
			return;
		}

		$uri = $from['url'];
		$params = $from['params'];

		if ($params !== NULL) {
			unset($params['sso_logout']);
		}

		if (count($params) > 0) {
			$params = http_build_query($params);
			$uri.='?'.$params;
		}
		
		// on supprime pour éviter d'y retourner la prochaine fois
		$this->setRedirectUrl(NULL);
		
		session_write_close();
		header('Location: '.$uri, TRUE, 303);
		die();
	}
	
	/**
	 * Check a fullpath is a subpath of a basepath
	 *
	 * Examples : <br/>
	 * /a/b/c is a subpath of /a/b<br/>
	 * /a/b is a subpath of /a/b<br/>
	 * /a/bc is NOT a subpath of /a/b<br/>
	 *
	 * @param string $fullPath full path to check
	 * @param string $basePath the base path the full path have to begin with
	 * @return TRUE if $fullPath start with $basePath and match exactly for last path element.
	 */
	public function isSubPath($fullPath, $basePath) {
		return ((strpos($fullPath, $basePath) === 0)
			// Check last path element
			&& (strlen($fullPath) === strlen($basePath)
			|| substr($fullPath, strlen($basePath), 1)==='/'));
	}

	/**
	 * Check current or provided page is a SSO page
	 * @param string $url URL to check, NULL will check current page
	 * @return boolean TRUE if it's an SSO page
	 */
	private function isSsoPage($url = NULL) {
		static $isSsoPage = array();
		
		if ($url === NULL) {
			$Input = In::getInstance();
			$url = $Input->S->RAW->REQUEST_URI;
		}

		if (!isset($isSsoPage[$url])) {
			$isSsoPage[$url] = $this->isSubPath($url, SSO_WEB_PATH);
		}
		return $isSsoPage[$url];
	}

	/**
	 * Retrieve handler for application (or current application)
	 * @param string $appli application path, or NULL for use current application
	 * @return Handler the Handler instance for this application. Can be NULL
	 */
	protected function getClientHandler($appli = NULL) {
		if ($appli === NULL) {
			$appli = $this->currentApplication;
		}
		if ($appli !== NULL) {
			$handler = $this->session->SSO_CREDENTIALS[$appli];
			return $this->loadClientHandler($handler, $appli);
		}
		return NULL;
	}

	/**
	 * Initialize an application Handler
	 * @param string $handler handler class name
	 * @param string $appli application path
	 * @return Handler instance
	 */
	protected function loadClientHandler($handler, $appli) {
		if (($handler !== NULL) && !empty($handler)) {
			$h = new $handler();
			$h->setPath($appli);
			return $h;
		}
		return NULL;
	}

	/**
	 * Try to init a client application
	 * @throws Exception if something go wrong during init
	 */
	private function initApplication() {
		$handler = $this->getClientHandler();
		if ($handler !== NULL) {
			try {
				set_error_handler(array($this, 'clientError'));
				set_exception_handler(array($this, 'clientException'));
				$handler->init($this->getUser(), $this);
				restore_exception_handler();
				restore_error_handler();
			} catch (\Exception $ex) {
				restore_exception_handler();
				restore_error_handler();
				throw $ex;
			}
		}
	}

	/**
	 * Redirect to login page when error occured during client init
	 * @param int $code error code
	 * @param string $message error message
	 * @param string $file file name
	 * @param int $line line number
	 */
	public function clientError($code, $message, $file, $line) {
		$Input = In::getInstance();
		error_log('SSO APP INIT ERROR: '.$message.' ('.$file.':'.$line.')');
		// do nothing, will be redirected later
		//header('Location: '.SSO_WEB_RELATIVE.'index.php?reason='.self::AUTH_KO_INIT_APP, true, 303);
		//die();

	}

	/**
	 * Redirect to login page when exception occured during client init
	 * @param Exception $ex the exception
	 */
	public function clientException($ex) {
		$this->clientError(0, $ex->getMessage);
	}

	/**
	 * Check session validity
	 * @return int status of session : self::AUTH_*
	 */
	private function checkUserAuth() {
		$Input = In::getInstance();

		if (!$this->isLogged()) {
			return self::AUTH_KO_NO_SESSION;
		}
		if ($this->session->SSO_TIMEOUT === 0) {
			$sessionid = $Input->C->RAW->{Session::SSO_SESSION_NAME.'0'};
			if (session_id() !== $sessionid) {
				//error_log('SSO - '.$this->session->SSO_LOGIN.' - Expired session cookie. Destroy session ('.__FILE__.':'.__LINE__.')');
				$this->session->logout();
				return self::AUTH_KO_TIMEOUT;
			}
		} else {
			if ($this->session->SSO_lastTime + $this->session->SSO_TIMEOUT < time()) {
				//error_log('SSO - '.$this->session->SSO_LOGIN.' - Expired session. Destroy session ('.__FILE__.':'.__LINE__.')');
				$this->session->logout();
				return self::AUTH_KO_TIMEOUT;
			}
		}

		if (($this->session->SSO_checkIP) && ($Input->S->RAW->REMOTE_ADDR !== $this->session->SSO_lastIP)) {
			// error log only : do not translate
			error_log('SSO - '.$this->session->SSO_LOGIN.' - Bad IP address. Destroy session ('.__FILE__.':'.__LINE__.')');
			$this->session->logout();
			return self::AUTH_KO_IP;
		}
		if (($this->session->SSO_checkAgent) && ($Input->S->RAW->HTTP_USER_AGENT != $this->session->SSO_lastAgent)) {
			// error log only : do not translate
			error_log('SSO - '.$this->session->SSO_LOGIN.' - Bad User Agent. Destroy session ('.__FILE__.':'.__LINE__.')');
			$this->session->logout();
			return self::AUTH_KO_AGENT;
		}

		$this->session->SSO_lastTime = time();

		return self::AUTH_OK;
	}

	/**
	 * Check user is SSO Admin
	 * @return boolean TRUE if logged user is an SSO admin
	 */
	public function isSsoAdmin() {
		return $this->session->SSO_ADMIN;
	}

	/**
	 * Check user is logged
	 * @return boolean TRUE if user is logged and enabled
	 */
	public function isLogged() {
		return ($this->getLogin() !== NULL)
		&& ($this->getUser()->getState() === SsoUser::STATE_ENABLED);
	}

	/**
	 * Check user can access to an application path
	 *
	 * currentApplication became $appli if user have access
	 *
	 * @param string $appli application path
	 * @return boolean TRUE if user can access to this application, FALSE otherwise
	 */
	protected function checkCredentials($appli) {
		$applis = $this->session->SSO_CREDENTIALS;

		// remove protocol if needed
		if (strpos($appli, '://') !== FALSE) {
			$appli = '/'.\salt\last(explode('/', \salt\last(explode('://', $appli, 2)), 2));
		}

		if (($applis !== NULL) && ($appli !== NULL)) {
			foreach($applis as $app => $handler) {
				if ($this->isSubPath($appli, $app)) {
					$this->currentApplication = $app;
					return TRUE;
				}
			}
			//error_log('SSO - '.$this->session->SSO_LOGIN.' - No credential found for '.$appli.' ('.__FILE__.':'.__LINE__.')');
		}
		return FALSE;
	}

	/**
	 * Retrieve login user name
	 * @return string login user name
	 */
	public function getLogin() {
		if (isset($this->session->SSO_LOGIN)) {
			return $this->session->SSO_LOGIN;
		}
		return NULL;
	}

	/**
	 * Retrieve AuthUser
	 * @return AuthUser the AuthUser returned by an auth method
	 */
	public function getUser() {
		if (isset($this->session->SSO_USER)) {
			return $this->session->SSO_USER;
		}
		return NULL;
	}

	/**
	 * Retrieve user name
	 * @return string user name for display
	 */
	public function getUserName() {
		$name = '';
		if (isset($this->session->SSO_USERNAME)) {
			$name = $this->session->SSO_USERNAME;
		}
		if (empty($name)) {
			$name = $this->getLogin();
		}
		return $name;
	}

	/**
	 * Retrieve an ID that identify the SSO menu (for handle browser cache)
	 * @param boolean $hidden TRUE for do not display menu
	 * @return string an ID prefixed by destination : application=ID
	 */
	private function getMenuId($hidden = FALSE) {
		if (!$this->isLogged() || ($hidden)) {
			return '';
		}
		$profil = SsoProfil::getCurrent($this);

		$theme = $profil->getThemeObject();
		$options = $theme->getOptions();
		$Input = In::getInstance();
		$id = $Input->URL($profil->path);
		if (strlen($id) === 0) {
			$id = 'global';
		}
		if (strpos($Input->S->RAW->REQUEST_URI, SSO_WEB_PATH) === 0) {
			$id = 'sso'; // different URL on SSO because SSO do not use global theme
		}
		if (SsoProfil::isPreview() || $Input->P->ISSET->preview) {
			$id = SsoProfil::PREVIEW_KEY;
		}

		$hash = md5($profil->theme.json_encode($options));
		$hash = base_convert($hash, 16, 36); // shorten the hash
		$id.= '='.$hash;

		return $id;
	}

	/**
	 * Return HTML link tag for CSS SSO menu
	 * @param boolean $hidden TRUE for do not display menu
	 */
	public function displayMenuCssHeader($hidden = FALSE) {
		$id= $this->getMenuId($hidden);
		if ($id === '') {
			return;
		}

		// id value is for browser cache : using a different URL for each theme/options force browser to not use a cache file
		echo '<link href="'.SSO_WEB_RELATIVE.'css/sso_menu.css.php?'.$id.'" rel="stylesheet" type="text/css" />';
	}

	/**
	 * display the SSO menu
	 * @param boolean $hidden TRUE for do not display menu
	 */
	public function displayMenu($hidden = FALSE) {
		if ($hidden) {
			return;
		}
		$_sso = $this; // for visibility in included page
		include(SSO_RELATIVE.'pages/menu.php');
	}

	/**
	 * Display the SSO menu in page without the CSS in header.
	 *
	 * CSS will be added by javascript during page load
	 */
	public function displayFullMenuAfterBody() {
		ob_start();
		$this->displayMenuCssHeader();
		$link = explode(' ', ob_get_clean());
		foreach($link as $attr) {
			$attr = explode('=', $attr, 2);
			if ($attr[0] === 'href') {
				$link = $attr[1];
				break;
			}
		}
		echo '<div id="sso_menu_after_body">';
		$this->displayMenu();
		echo '</div>';
		echo <<<JS
<script type="text/javascript">
	var head = document.getElementsByTagName('head')[0];
	var cssLink = document.createElement('link');
	cssLink.rel='stylesheet';
	cssLink.type='text/css';
	cssLink.href=$link;
	head.appendChild(cssLink);
</script>
JS;
	}

	/**
	 * Register variables in session
	 *
	 * Variables will be restored at each page in global variables
	 *
	 * @param mixed[] variableName => variableValue
	 */
	public function registerGlobals($variables) {
		$this->session->SSO_GLOBALS = $variables;
		foreach($this->session->SSO_GLOBALS as $name => $value) {
			global ${$name};
			${$name} = $value;
		}
	}

	/**
	 * SSO pages list
	 * @return string[] key => text
	 */
	public function pagesList() {
		return array(
			'' => L::menu_login,
			'init' => L::menu_init,
			'settings' => L::menu_profile,
			'admin' => L::menu_admin,
			'apps' => L::menu_applications,
		);
	}

}
