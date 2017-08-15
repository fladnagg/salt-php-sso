<?php
/**
 * Configuration file example. Rename to config.php
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\config
 */
namespace sso;

/** Absolute path of SALT framework */
define('sso\SALT_PATH', '...');

/**
 * Absolute path of SSO Session store path
 *
 * We use a specific path for session files because some OS (debian and derivated : ubuntu, etc...) use a specific
 * cron script for clean sessions, based on php.ini files.
 * In the SSO, each user can choose the lifetime of the session, but it does not work well if OS clean session
 * files after 30mn...
 **/
define('sso\SESSION_PATH', '...');

/** Absolute path of SSO from web root. Optional. Automatically computed if not provided */
// define('sso\SSO_WEB_PATH', '/sso');

/** Sso charset */
define('sso\SSO_CHARSET', 'UTF-8');
if (!defined('salt\CHARSET')) {
	/** Salt charset */
	define('salt\CHARSET', SSO_CHARSET);
}

/** Default SALT locale **/
define('salt\I18N_DEFAULT_LOCALE', 'en');

/** Default SSO locale **/
define('sso\SSO_LOCALE', 'en');

/**
 * Client charset for database as MySQL expected it
 *
 * It's better if database and website charset are the same, but if not, we have to set here the website charset.
 * MySQL will convert all input/output data to this charset from/to the database charset.
 * @see http://mysql.rjweb.org/doc.php/charcoll#how_mangling_happens_on_insert for how DB charset works
 * @see http://dev.mysql.com/doc/en/charset-charsets.html for supported charsets
 */
define('sso\SSO_DB_CHARSET', 'utf8');

/** Database host name */
define('sso\SSO_DB_HOST', 'localhost');
/** Database port */
define('sso\SSO_DB_PORT', 3306);
/** Database name */
define('sso\SSO_DB_DATABASE', 'database');
/** Database user */
define('sso\SSO_DB_USER', 'root');
/** Database password */
define('sso\SSO_DB_PASS', 'password');

/** Allow the database user to be used for login as administrator */
define('sso\ALLOW_DB_USER_LOGIN', FALSE);

/** Number of elements displayed in autocomplete list  */
define('sso\SSO_MAX_AUTOCOMPLETE_ELEMENTS', 8);
/** Number of characters required for an autocomplete search */
define('sso\SSO_MIN_AUTOCOMPLETE_CHARACTERS', 2);
/** Number of elements displayed in tooltip */
define('sso\SSO_MAX_TOOLTIP_ELEMENTS', 5);


