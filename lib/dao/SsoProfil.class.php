<?php namespace sso;

use salt\Base;
use salt\Field;
use salt\Query;
use salt\In;
use salt\DBHelper;
use salt\SqlExpr;

/**
 * @property int id
 * @property string userId
 * @property int appliId
 * @property string theme
 * @property boolean enabled
 * @property string options
 */
class SsoProfil extends Base {

	const DEFAULT_THEME = 'menu';
	const RECOMMENDED_PROFILE = 'recommended';

	const PREVIEW_KEY = 'SSO_PREVIEW';
	
	private static $internalProfiles = array(self::RECOMMENDED_PROFILE);
	private static $themesList = NULL;
	
	public $path = NULL; // not persisted
	
	protected function metadata() {
		parent::registerHelper(__NAMESPACE__.'\SsoProfilViewHelper');

		self::MODEL()
			->registerId('id')
			->registerTableName('sso_profile')
			->registerFields(
				Field::newNumber('id', 		'ID')->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(	'userId', 	'User', TRUE)->sqlType('VARCHAR(32)'),
				Field::newNumber('appliId', 'Application'),
				Field::newText(	'theme',	'Thème')->sqlType('VARCHAR(32)'),
				Field::newBoolean('enabled','Actif'),
				Field::newText(	'options',	'Options', FALSE)->sqlType('TEXT')
		);
	}
	
	public static function isPreview() {
		$session = Session::getInstance();
		return isset($session->SSO_PROFIL_PREVIEW);
	}
	
	public static function setPreview(SsoProfil $profil) {
		$session = Session::getInstance();
		$session->SSO_PROFIL_PREVIEW = $profil;
	}
	
	public static function clearPreview() {
		$session = Session::getInstance();
		unset($session->SSO_PROFIL_PREVIEW);
	}

	public static function initProfiles(Sso $sso) {
		$profiles = array();
		
		$q = SsoProfil::query(TRUE);
		$q->whereAnd('enabled', '=', TRUE);
		$qUser = $q->getSubQuery();
		$qUser->whereOr('userId', '=', $sso->getLogin());
		$qUser->whereOr('userId', 'IS', NULL);
		$q->whereAndQuery($qUser);

		$qAppli = SsoAppli::query();
		$qAppli->selectField('path');
		$q->join($qAppli, 'appliId', '=', $qAppli->id);
		
		$q->orderDesc('userId'); // NULL user at end : recommended profile
		
		$DB = DBHelper::getInstance('SSO');
		foreach($DB->execQuery($q)->data as $p) {
			if (!isset($profiles[$p->path.'/'])) {
				$profiles[$p->path.'/'] = $p;
			}
		}
		$sso->session->SSO_PROFIL = $profiles;
	}
	
	/**
	 * 
	 * @param SsoClient $sso
	 * @return SsoProfil 
	 */
	public static function getCurrent(SsoClient $sso, $application = NULL) {
		if (isset($sso->session->SSO_PROFIL_PREVIEW)) {
			return $sso->session->SSO_PROFIL_PREVIEW;
		}

		$Input = In::getInstance();
		$request = \salt\first(explode('?', $Input->S->RAW->REQUEST_URI, 2));
		if ($application !== NULL) {
			$request = $application;
		}
		$request.='/';

		if (!isset($sso->session->SSO_PROFIL) || !is_array($sso->session->SSO_PROFIL)) {
			$sso->session->logout();
			$sso->auth(TRUE); // force display login page
		}

		if (($application === NULL) && (strpos($Input->S->RAW->REQUEST_URI, SSO_WEB_PATH) === 0)) { // force default theme on SSO
			$result = SsoProfil::createNew(NULL, NULL, $sso->getLogin());
		} else {
			$profiles = $sso->session->SSO_PROFIL;

			$result = NULL;
			foreach($profiles as $appli => $profile) {
				if (($appli !== '/') && (strpos($request, $appli) === 0)) {
					$result = $profile;
					break;
				}
			}

			if (($result === NULL) && (isset($profiles['/']))) {
				$result = $profiles['/']; // global profile
			}

			if ($result === NULL) {
				$result = SsoProfil::createNew(NULL, NULL, $sso->getLogin());
			}
		}

		return $result;
	}
	
	public static function createNew($appliId, $appliName, $user) {
		$profil = new SsoProfil(NULL, array('name', 'path'));
		if ($appliId === NULL) {
			$profil->theme = self::DEFAULT_THEME;
		} else {
			$profil->theme = self::RECOMMENDED_PROFILE;
			$profil->appliId = $appliId;
		}
		$profil->name = $appliName;
		$profil->userId = $user;
		$profil->enabled = TRUE;
		$profil->path = $appliId;
		return $profil;
	}
	
	public static function getThemesList() {
		if (self::$themesList === NULL) {
			// find existing themes
			$themesPath = SSO_RELATIVE.'themes/';
			$dir = opendir($themesPath);
			$themesList = array();
			while(($file = readdir($dir)) !== FALSE) {
				if (is_dir($themesPath.$file) && (substr($file, 0, 1) !== '.') && (!self::isInternalTheme($file))) {
					$themesList[] = $file;
				}
			}
			self::$themesList = array_combine($themesList, $themesList);
		}
		return self::$themesList;
	}
	
	public static function isInternalTheme($theme) {
		return in_array($theme, self::$internalProfiles);
	}
	
	public static function isThemeValid($theme, $appliId) {
		$themes = self::getThemesList();
		return isset($themes[$theme]) || (self::isInternalTheme($theme) && ($appliId !== NULL));
	}
	
	public static function getInternalProfile(SsoClient $sso, $appli, $theme) {
		if (self::isInternalTheme($theme)) {

			$q = SsoProfil::query(TRUE);
			$q->whereAnd('enabled', '=', TRUE);
			if ($theme === self::RECOMMENDED_PROFILE) {
				$q->whereAnd('userId', 'IS', SqlExpr::value(NULL));
			} else {
				$q->whereAnd('userId', '=', $sso->getLogin());
			}
			$q->whereAnd('appliId', '=', $appli);

			$qAppli = SsoAppli::query();
			$qAppli->selectField('path');
			$q->join($qAppli, 'appliId', '=', $qAppli->id);

			$DB = DBHelper::getInstance('SSO');
			$profile = \salt\first($DB->execQuery($q)->data);
			
			return $profile;
			
		}
		return NULL;
	}
	
	/**
	 * @return Theme
	 */
	public function getThemeObject() {
		return Theme::get($this);
	}
	
	public function setThemeObject(Theme $theme) {
		Theme::set($this, $theme);
	}
}
