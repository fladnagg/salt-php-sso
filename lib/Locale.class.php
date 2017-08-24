<?php
/**
 * Locale class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

use salt\I18n;
use salt\Salt;

// for documentation
if (FALSE) {
	/** Current locale for SSO */
	define('sso\SSO_CURRENT_LOCALE', 'en');
}

/**
 * Initialize and set locale for SSO
 */
class Locale {

	/** Cookie name for locale */
	const COOKIE_NAME = 'sso_locale';
	/** Name of SSO locale constant */
	const SSO_CURRENT_LOCALE = 'sso\SSO_CURRENT_LOCALE';

	/**
	 * List of all available locales
	 * @var string[] keys value of "locales" key in default locale
	 */
	private static $AVAILABLE_LOCALES = NULL;

	/**
	 * Initialize locales constants for SSO and SALT
	 */
	public static function init() {
		static $init = FALSE;

		if ($init) {
			return;
		}

		$locales = self::retrieveLocales();
		
		Salt::config($locales); // SALT conf

		// retrieve available locale in default locale 
		$i18n = I18n::getInstance('SSO', SSO_RELATIVE);
		$default = $i18n->init(SSO_LOCALE)->get();
		self::$AVAILABLE_LOCALES = array_keys($default::locales());

		if (!defined(self::SSO_CURRENT_LOCALE)) {
			foreach($locales as $locale) {
				if (in_array($locale, self::$AVAILABLE_LOCALES)) {
					/**
					 * @ignore
					 */
					define(self::SSO_CURRENT_LOCALE, $locale);
					break;
				}
			}
		}
		// not found : set to default locale
		if (!defined(self::SSO_CURRENT_LOCALE)) {
			/**
			 * @ignore
			 */
			define(self::SSO_CURRENT_LOCALE, SSO_LOCALE);
		}

		$i18n->init(SSO_CURRENT_LOCALE)->alias(__NAMESPACE__.'\L');

		$init = TRUE;
	}

	/**
	 * Register current locale in cookie
	 * @param string $locale locale to set
	 * @return boolean TRUE if cookie is set, FALSE otherwise.
	 */
	public static function set($locale) {
		if (!in_array($locale, self::$AVAILABLE_LOCALES) && ($locale !== '')) {
			$locale = NULL;
		}

		if (($locale !== NULL)
		&& (!isset($_COOKIE[self::COOKIE_NAME]) || ($_COOKIE[self::COOKIE_NAME] !== $locale))) {
			if ($locale === '') {
				setcookie(self::COOKIE_NAME, $locale, time()-4200, '/'); // unset
			} else {
				setcookie(self::COOKIE_NAME, $locale, time()+60*60*24*365, '/');
			}
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

		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			foreach(explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $locale) {
				$locale = explode(';', $locale, 2);
				$locale = self::normalizeLocale(reset($locale));
				$locales[$locale] = $locale;
			}
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
	 * Retrieve all availables locales for options display
	 * @return string[] locale => locale text
	 */
	public static function availables() {
		return array('' => L::label_locales_auto_text)+L::locales();
	}
	
	/**
	 * Return the options array for locales
	 * @return mixed[] locale => text or array for FormHelper::select(...)
	 */
	public static function options() {
		$options = self::availables();
		$options[''] = array('value' => $options[''], 'title' => L::label_locales_auto_tooltip);
		return $options;
	}
}
