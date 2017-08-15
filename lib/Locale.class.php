<?php
/**
 * Locale class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

// for documentation
if (FALSE) {
	/** Locale for SALT */
	define('salt\I18N_LOCALE', 'en');
	/** Current locale for SSO */
	define('sso\SSO_CURRENT_LOCALE', 'en');
}

/**
 * Initialize and set locale for SSO
 */
class Locale {

	/** Cookie name for locale */
	const COOKIE_NAME = 'sso_locale';
	/** Name of SALT locale constant */
	const SALT_LOCALE = 'salt\I18N_LOCALE';
	/** Name of SSO locale constant */
	const SSO_CURRENT_LOCALE = 'sso\SSO_CURRENT_LOCALE';

	/**
	 * List all available locales, for SALT and SSO
	 * @var mixed[] constant_name => [locale => locale name, locale...,  default locale => default locale name]
	 */
	private static $AVAILABLE_LOCALES = array(
		// last locale will be set by default
		self::SALT_LOCALE => array('fr' => 'Français', 'en' => 'English'),
		self::SSO_CURRENT_LOCALE => array('fr' => 'Français', 'en' => 'English'),
	);

	/**
	 * Initialize locales constants for SSO and SALT
	 */
	public static function init() {
		static $init = FALSE;

		if ($init) {
			return;
		}

		$locales = self::retrieveLocales();

		foreach(self::$AVAILABLE_LOCALES as $const => $availables) {
			if (!defined($const)) {
				foreach($locales as $locale) {
					if (in_array($locale, array_keys($availables))) {
						/**
						 * @ignore
						 */
						define($const, $locale);
						break;
					}
				}
			}
			// not found : set to default locale
			if (!defined($const)) {
				/**
				 * @ignore
				 */
				define($const, SSO_LOCALE);
			}
		}

		$init = TRUE;
	}

	/**
	 * Register current locale in cookie
	 * @param string $locale locale to set
	 * @return boolean TRUE if cookie is set, FALSE otherwise.
	 */
	public static function set($locale) {
		if (!isset(self::$AVAILABLE_LOCALES[self::SSO_CURRENT_LOCALE][$locale])) {
			$locale = NULL;
		}
		if (($locale !== NULL)
		&& (!isset($_COOKIE[self::COOKIE_NAME]) || ($_COOKIE[self::COOKIE_NAME] !== $locale))) {
			setcookie(self::COOKIE_NAME, $locale, time()+60*60*24*365, '/');
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Retrieve locales to use
	 * @return string[] List of locale, by preference order
	 */
	private static function retrieveLocales() {
		$locales = array();

		if (isset($_COOKIE[self::COOKIE_NAME])) {
			$locale = self::normalizeLocale($_COOKIE[self::COOKIE_NAME]);
			$locales[$locale] = $locale;
		}

		foreach(explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $locale) {
			$locale = explode(';', $locale, 2);
			$locale = self::normalizeLocale(reset($locale));
			$locales[$locale] = $locale;
		}

		return $locales;
	}

	/**
	 * Normalize a locale
	 * @param string $locale locale to normalize
	 * @return string normalized locale, with only [a-z0-9_]
	 */
	private static function normalizeLocale($locale) {
		return preg_replace('#[^a-z0-9]#', '_', strtolower($locale));
	}

	/**
	 * Retrieve all availables locales
	 * @return string[] locale => locale text
	 */
	public static function availables() {
		$locales = self::$AVAILABLE_LOCALES[self::SSO_CURRENT_LOCALE];
		return $locales;
	}
}
