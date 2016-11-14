<?php namespace sso;

/** Absolute path of SALT framework **/
define(__NAMESPACE__.'\SALT_PATH', '...');

/** 
 * Absolute path of SSO Session store path
 * 
 * We use a specific path for session files because some OS (debian and derivated : ubuntu, etc...) use a specific 
 * cron script for clean sessions, based on php.ini files.
 * In the SSO, each user can choose the lifetime of the session, but it does not work well if OS clean session 
 * files after 30mn... 
 **/
define(__NAMESPACE__.'\SESSION_PATH', '...');

/** Absolute path of SSO from web root. Optional. Automatically computed if not provided **/
// define(__NAMESPACE__.'\SSO_WEB_PATH', '/sso');

/** Sso charset **/
define(__NAMESPACE__.'\SSO_CHARSET', 'UTF-8');
if (!defined('salt\CHARSET')) {
	/** Salt charset **/
	define('salt\CHARSET', SSO_CHARSET);
}
/** 
 * Client charset for database as MySQL expected it
 * 
 * It's better if database and website charset are the same, but if not, we have to set here the website charset.
 * MySQL will convert all input/output data to this charset from/to the database charset.
 * @see http://mysql.rjweb.org/doc.php/charcoll#how_mangling_happens_on_insert
 * @see http://dev.mysql.com/doc/en/charset-charsets.html for supported charsets
 */
define(__NAMESPACE__.'\SSO_DB_CHARSET', 'utf8');

/** Database host name **/
define(__NAMESPACE__.'\SSO_DB_HOST', 'localhost');
/** Database port **/
define(__NAMESPACE__.'\SSO_DB_PORT', 3306);
/** Database name **/
define(__NAMESPACE__.'\SSO_DB_DATABASE', 'database');
/** Database user **/
define(__NAMESPACE__.'\SSO_DB_USER', 'root');
/** Database password **/
define(__NAMESPACE__.'\SSO_DB_PASS', 'password');

/** Number of elements displayed in autocomplete list  **/
define(__NAMESPACE__.'\SSO_MAX_AUTOCOMPLETE_ELEMENTS', 8);
/** Number of characters required for an autocomplete search **/
define(__NAMESPACE__.'\SSO_MIN_AUTOCOMPLETE_CHARACTERS', 2);
/** Number of elements displayed in tooltip **/
define(__NAMESPACE__.'\SSO_MAX_TOOLTIP_ELEMENTS', 8);


