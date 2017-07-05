<?php namespace sso;

/**** Global configuration ****/
$conf = implode(\DIRECTORY_SEPARATOR, array(__DIR__, '..', 'config', 'config.php'));
if (file_exists($conf)) {
	require_once($conf);
} else {
	echo 'Configuration file '.$conf.' does not exists';
	die();
}

/**** Load SALT ****/
set_include_path(get_include_path().\PATH_SEPARATOR.SALT_PATH);
require_once('Salt.class.php');

use salt\Salt;
use salt\In;

/**** Configure ****/
define(__NAMESPACE__.'\SSO_RELATIVE', Salt::relativePath(1));

Salt::config(); // SALT conf

Salt::addClassFolder(SSO_RELATIVE.'lib', __NAMESPACE__);
Salt::addClassFolder(SSO_RELATIVE.'plugins'); // not in SSO namespace

/**** Main SSO class ****/

class SsoClient {

	const AUTH_OK = 10;
	const AUTH_KO_NO_SESSION = 11;
	const AUTH_KO_IP = 20;
	const AUTH_KO_AGENT = 21;
	const AUTH_KO_TIMEOUT = 30;
	const AUTH_KO_INIT_APP = 40;
	const AUTH_KO_UNKNOWN = 99;

	public $session;
	private $currentApplication = NULL;
	private static $instance = NULL;

	private static $logoutReasons = array(
		self::AUTH_KO_AGENT => 'la session est invalide', 	// do not give too more informations 
		self::AUTH_KO_IP => 'la session est invalide',		// do not give too more informations
		self::AUTH_KO_NO_SESSION => 'la session n\'existe plus',
		self::AUTH_KO_TIMEOUT => 'la session a expirée',
		self::AUTH_KO_UNKNOWN => 'une erreur imprévue est survenue',
		self::AUTH_KO_INIT_APP => 'une erreur est survenue lors de l\'authentification à une application',
	);

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
	
	private function __construct($path = NULL) {
		if (!defined(__NAMESPACE__.'\SESSION_PATH')) {
			define(__NAMESPACE__.'\SESSION_PATH', session_save_path());
		}
		if (!defined(__NAMESPACE__.'\SSO_WEB_RELATIVE')) {
			define(__NAMESPACE__.'\SSO_WEB_RELATIVE', $path);
		}
		if (!defined(__NAMESPACE__.'\SSO_WEB_PATH')) {
			$req = implode('/', (explode('/', $_SERVER['REQUEST_URI'], -1)));
			$webPath = substr(Salt::computePath($req, $path), 0, -1);
			define(__NAMESPACE__.'\SSO_WEB_PATH', $webPath);
		}

		$this->session = Session::getInstance();
	}

	public static function getLogoutReason($reason) {
		if (!array_key_exists($reason, self::$logoutReasons)) {
			$reason = self::AUTH_KO_UNKNOWN;
		}
		return self::$logoutReasons[$reason];
	}

	public function auth($authOnly = FALSE, $fromURL = NULL) {

		$Input = In::getInstance();

		$state = $this->checkUserAuth();

		if ($fromURL === NULL) {
			$fromURL = $Input->S->RAW->REQUEST_URI;
			$params = $_GET;
		} else {
			$params = array();
			$data = explode('?', $fromURL, 2);
			if (count($data) > 1) {
				parse_str(\salt\last($data), $params);
			}
			$fromURL = \salt\first($data);
		}
		
		$this->session->SSO_REDIRECT = \salt\first(explode('?', $fromURL, 2));
		$this->session->SSO_GET = $params;
		
		if ($state !== self::AUTH_OK) {
			header('Location: '.SSO_WEB_RELATIVE.'index.php?reason='.$state, true, 303);
			die();
		}

		if (!$this->isSsoPage()) {
			$this->session->freeze();
		}

		if (!$authOnly) {
			if (!$this->checkCredentials($fromURL)) {
				header('Location: '.SSO_WEB_RELATIVE.'index.php?page=apps&from=client', true, 303);
				die();
			}

			try {
				$this->initApplication();
			} catch (\Exception $ex) {
				error_log('SSO APP INIT ERROR: '.$ex->getMessage().' ('.__FILE__.':'.__LINE__.')');
				header('Location: '.SSO_WEB_RELATIVE.'index.php?sso_logout=1&reason='.self::AUTH_KO_INIT_APP, true, 303);
				die();
			}
		}
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
	
	private function isSsoPage() {
		$Input = In::getInstance();
		
		$req = $Input->S->RAW->REQUEST_URI;
		return $this->isSubPath($req, SSO_WEB_PATH);

		return FALSE;
	}
	
	/**
	 * @param string $appli
	 * @return Handler
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
	
	protected function loadClientHandler($handler, $appli) {
		if (($handler !== NULL) && !empty($handler)) {
			$h = new $handler();
			$h->setPath($appli);
			return $h;
		}
		return NULL;
	}
	
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

	public function clientError($code, $message, $file, $line) {
		$Input = In::getInstance();
		error_log('SSO APP INIT ERROR: '.$message.' ('.$file.':'.$line.')');
		header('Location: '.SSO_WEB_RELATIVE.'index.php?sso_logout=1&reason='.self::AUTH_KO_INIT_APP, true, 303);
		die();
		
	}
	
	public function clientException($ex) {
		$this->clientError(0, $ex->getMessage);
	}
	
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
			error_log('SSO - '.$this->session->SSO_LOGIN.' - Bad IP address. Destroy session ('.__FILE__.':'.__LINE__.')');
			$this->session->logout();
			return self::AUTH_KO_IP;
		}
		if (($this->session->SSO_checkAgent) && ($Input->S->RAW->HTTP_USER_AGENT != $this->session->SSO_lastAgent)) {
			error_log('SSO - '.$this->session->SSO_LOGIN.' - Bad User Agent. Destroy session ('.__FILE__.':'.__LINE__.')');
			$this->session->logout();
			return self::AUTH_KO_AGENT;
		}

		$this->session->SSO_lastTime = time();

		return self::AUTH_OK;
	}

	public function isSsoAdmin() {
		return $this->session->SSO_ADMIN;
	}
	
	public function isLogged() {
		return ($this->getLogin() !== NULL) 
		&& ($this->getUser()->getState() === SsoUser::STATE_ENABLED);
	}

	protected function checkCredentials($appli) {
		$applis = $this->session->SSO_CREDENTIALS;
		
		$appli2 = NULL;
		if (strpos($appli, '://') !== FALSE) {
			$appli2 = '/'.\salt\last(explode('/', \salt\last(explode('://', $appli, 2)), 2));
		}

		if (($applis !== NULL) && ($appli !== NULL)) {
			foreach($applis as $app => $handler) {
				if ($this->isSubPath($appli, $app) || $this->isSubPath($appli2, $app)) {
					$this->currentApplication = $app;
					return TRUE;
				}
			}
			//error_log('SSO - '.$this->session->SSO_LOGIN.' - No credential found for '.$appli.' ('.__FILE__.':'.__LINE__.')');
		}
		return FALSE;
	}

	public function getLogin() {
		if (isset($this->session->SSO_LOGIN)) {
			return $this->session->SSO_LOGIN;
		}
		return NULL;
	}

	public function getUser() {
		if (isset($this->session->SSO_USER)) {
			return $this->session->SSO_USER;
		}
		return NULL;
	}
	

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
		$id.= '='.md5($profil->theme.json_encode($options));
		
		return $id;
	}
	
	public function displayMenuCssHeader($hidden = FALSE) {
		$id= $this->getMenuId($hidden);
		if ($id === '') {
			return;
		}
		
		// id value is for browser cache : using a different URL for each theme/options force browser to not use a cache file
		echo '<link href="'.SSO_WEB_RELATIVE.'css/sso_menu.css.php?'.$id.'" rel="stylesheet" type="text/css" />';
	}

	public function displayMenu($hidden = FALSE) {
		if ($hidden) {
			return;
		}
		$_sso = $this; // for visibility in included page
		include(SSO_RELATIVE.'pages/menu.php');
	}
	
	public function displayFullMenuAfterBody() {
		ob_start();
		$this->displayMenuCssHeader();
		$link = explode(' ', ob_get_contents());
		ob_end_clean();
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

	public function registerGlobals($variables) {
		$this->session->SSO_GLOBALS = $variables;
		foreach($this->session->SSO_GLOBALS as $name => $value) {
			global ${$name};
			${$name} = $value;
		}
	}
	
	public function pagesList() {
		return array(
			'' => 'Identification',
			'init' => 'Initialisation',
			'settings' => 'Profil',
			'admin' => 'Administration',
			'apps' => 'Applications',
		);
	}

}
