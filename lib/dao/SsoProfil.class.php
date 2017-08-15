<?php
/**
 * SsoProfil class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Base;
use salt\Field;
use salt\Query;
use salt\In;
use salt\DBHelper;
use salt\SqlExpr;

/**
 * SsoProfil class. A Profil is a container for Theme class
 *
 * @property int $id
 * @property string $userId
 * @property int $appliId
 * @property string $theme
 * @property boolean $enabled
 * @property string $options
 */
class SsoProfil extends Base {

	/** default theme ID */
	const DEFAULT_THEME = 'menu';
	/** recommended theme internal ID */
	const RECOMMENDED_PROFILE = 'recommended';

	/** menu.css.php key for preview theme, also used as application path for preview */
	const PREVIEW_KEY = 'SSO_PREVIEW';

	/**
	 * @var string[] list of internal profiles */
	private static $internalProfiles = array(self::RECOMMENDED_PROFILE);

	/**
	 * @var string Application path */
	public $path = NULL; // not persisted

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
		parent::registerHelper(__NAMESPACE__.'\SsoProfilViewHelper');

		self::MODEL()
			->registerId('id')
			->registerTableName('sso_profile')
			->registerFields(
				Field::newNumber('id', 		L::field_id)->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(	'userId', 	L::field_user, TRUE)->sqlType('VARCHAR(32)'),
				Field::newNumber('appliId', L::field_application),
				Field::newText(	'theme',	L::field_theme)->sqlType('VARCHAR(32)'),
				Field::newBoolean('enabled',L::field_enabled),
				Field::newText(	'options',	L::field_options, FALSE)->sqlType('TEXT')
		);
	}

	/**
	 * Check is theme preview
	 * @return TRUE if we set preview, FALSE otherwise
	 */
	public static function isPreview() {
		$session = Session::getInstance();
		return isset($session->SSO_PROFIL_PREVIEW);
	}

	/**
	 * Set theme preview
	 * @param SsoProfil $profil The profile to preview
	 */
	public static function setPreview(SsoProfil $profil) {
		$session = Session::getInstance();
		$session->SSO_PROFIL_PREVIEW = $profil;
	}

	/**
	 * Remove theme preview
	 */
	public static function clearPreview() {
		$session = Session::getInstance();
		unset($session->SSO_PROFIL_PREVIEW);
	}

	/**
	 * Initialize profiles
	 *
	 * set SSO_PROFIL key in session
	 *
	 * @param Sso $sso SSO instance
	 */
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
	 * Retrieve current profil to use
	 * @param SsoClient $sso SSO instance
	 * @param string $application application, for check again
	 * @return SsoProfil profil to use
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

	/**
	 * Create a new profile
	 * @param int $appliId application ID. If NULL, use default theme, recommended theme otherwise
	 * @param string $appliName application name
	 * @param string $user User ID
	 * @return \sso\SsoProfil the new profile
	 */
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

	/**
	 * Retrieve all themes in "themes" directory
	 * @return string[] themes as themeName => themeName
	 */
	public static function getThemesList() {
		static $themesList = NULL;

		if ($themesList === NULL) {
			// find existing themes
			$themesPath = SSO_RELATIVE.'themes/';
			$dir = opendir($themesPath);
			$list = array();
			while(($file = readdir($dir)) !== FALSE) {
				if (is_dir($themesPath.$file) && (substr($file, 0, 1) !== '.') && (!self::isInternalTheme($file))) {
					$list[] = $file;
				}
			}
			$themesList = array_combine($list, $list);
		}
		return $themesList;
	}

	/**
	 * Check a theme is internal
	 * @param string $theme theme name
	 * @return boolean TRUE if internal, FALSE otherwise
	 */
	public static function isInternalTheme($theme) {
		return in_array($theme, self::$internalProfiles);
	}

	/**
	 * Check a theme is valid
	 *
	 * @param string $theme theme name
	 * @param int $appliId application ID
	 * @return boolean TRUE if theme exists, or recommended (with appliId not null)
	 */
	public static function isThemeValid($theme, $appliId) {
		$themes = self::getThemesList();
		return isset($themes[$theme]) || (self::isInternalTheme($theme) && ($appliId !== NULL));
	}

	/**
	 * Retrieve internal profil for internal theme
	 * @param SsoClient $sso SSO instance
	 * @param int $appli Application ID
	 * @param string $theme theme name
	 * @return NULL|SsoProfil return NULL if theme is not an internal theme
	 */
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
	 * Retrieve Theme object
	 * @return Theme
	 */
	public function getThemeObject() {
		return Theme::get($this);
	}

	/**
	 * Set the Theme object
	 * @param Theme $theme
	 */
	public function setThemeObject(Theme $theme) {
		Theme::set($this, $theme);
	}
}
