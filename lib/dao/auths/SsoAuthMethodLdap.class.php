<?php
/**
 * SsoAuthMethodLdap class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao\auths
 */
namespace sso;

use salt\Field;

/**
 * Method based on LDAP
 */
class SsoAuthMethodLdap implements SsoAuthMethodInterface {

	/**
	 * {@inheritDoc}
	 * @param mixed[] $value current values, as key => value, each key is a Field name of a previous call.
	 * 	Can be used for display example of current option values in option description for example.
	 * @see \sso\SsoAuthMethodInterface::getOptions()
	 */
	public function getOptions($value = NULL) {
		return array(
			Field::newText('host', L::field_host)->displayOptions(array('size'=>40)),
			Field::newNumber('port', L::field_port, FALSE, 389)->displayOptions(array('size'=>6)),
			Field::newText('bind_dn', L::field_bind_dn)->displayOptions(array('size'=>80)),
			Field::newText('bind_pass', L::field_bind_pass)->displayOptions(array('size'=>40)),
			Field::newText('dn', L::field_dn)->displayOptions(array('size'=>80)),
			Field::newText('field_id', L::field_field_id)->displayOptions(array('title' => L::help_ldap_field_id)),
			Field::newText('field_name', L::field_field_name)->displayOptions(array('title' => L::help_ldap_field_name)),
		);
	}

	/**
	 * Connect to LDAP
	 * @param \stdClass $options Options of method
	 * @throws BusinessException if anything go wrong
	 * @return resource LDAP resource after connect
	 */
	private function connect(\stdClass $options) {
		$host = $options->host;
		if (strpos(strtolower($host), 'ldap://') === FALSE) {
			$host = 'ldap://'.$host;
		}
		if (!Sso::pingServer($host, $options->port, 5)) {
			throw new BusinessException(L::error_ldap_not_responding($host, $options->port));
		}

		$ldap = @ldap_connect($host, $options->port);
		if ($ldap === FALSE) {
			throw new BusinessException(L::error_ldap_connect($host, $options->port));
		}
		@ldap_set_option($ldap, \LDAP_OPT_REFERRALS, TRUE);
		$r = @ldap_bind($ldap, $options->bind_dn, $options->bind_pass);
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			$this->disconnect($ldap);
			throw new BusinessException(L::error_ldap_bind($host, $options->port, $error));
		}
		return $ldap;
	}

	/**
	 * Close a LDAP connection
	 * @param resource $ldap LDAP resource
	 */
	private function disconnect($ldap) {
		@ldap_close($ldap);
	}

	/**
	 * {@inheritDoc}
	 * @param string $search search
	 * @param \stdClass $options auth method options
	 * @see \sso\SsoAuthMethodInterface::search()
	 */
	public function search($search, \stdClass $options) {
		$ldap = $this->connect($options);
		$authUsers = $this->searchOnLdap($search, $options, $ldap);
		$this->disconnect($ldap);

		return $authUsers;
	}

	/**
	 * Search on LDAP
	 * @param mixed[]|string $searchData Data to search indexed by field name, or user ID
	 * @param \stdClass $options options of method
	 * @param resource $ldap LDAP resource
	 * @throws Exception if ldap_search() failed
	 * @throws BusinessException if something else failed
	 * @return \sso\AuthUser[] List of users match $searchData
	 */
	private function searchOnLdap($searchData, \stdClass $options, $ldap) {
		if (!is_array($searchData)) {
			$searchData = array($options->field_id => $searchData);
		}
		$search = array();
		foreach($searchData as $field => $value) {
			$value = self::escapeLdap($value);
			if ($field !== $options->field_id) {
				$value = '*'.$value.'*';
			}
			$search[] = '('.$field.'='.$value.')';
		}
		$search = '(&'.implode('', $search).')';

		/**
		 * ldap_search produce a warning if SIZELIMIT is reached, even with @
		 * We don't want that warning, we know what we doing...
		 */
		set_error_handler(function() { /* ignore errors */ }, E_WARNING);
		try {
			$r = @ldap_search($ldap, $options->dn, $search, array(), 0, 10); // max 10 results
		} catch (\Exception $ex) {
			restore_error_handler();
			throw $ex;
		}
		restore_error_handler();
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			$this->disconnect($ldap);
			throw new BusinessException(L::error_ldap_search($error));
		}
		$r = @ldap_get_entries($ldap, $r);
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			$this->disconnect($ldap);
			throw new BusinessException(L::error_ldap_browse($error));
		}

		$authUsers = array();

		if ($r['count'] > 0) {
			foreach($r as $key => $ldapData) {
				if ($key === 'count') {
					continue;
				}
				$data = self::normalizeLdapData($ldapData);
				$authUsers[] = new AuthUser($data[$options->field_id], $data[$options->field_name], $data);
			}
		}

		return $authUsers;
	}

	/**
	 * {@inheritDoc}
	 * @param string $user user
	 * @param string $pass clear password
	 * @param stdClass $options all auth method options
	 * @see \sso\SsoAuthMethodInterface::auth()
	 */
	public function auth($user, $pass, \stdClass $options) {

		$ldap = $this->connect($options);

		$authUser = NULL;

		$authUsers = $this->searchOnLdap($user, $options, $ldap);
		foreach($authUsers as $user) {
			$authUser = $user;
			if (@ldap_bind($ldap, $user->dn, $pass)) {
				$user->logged();
				break;
			} else {
				$user->setError(ldap_error($ldap));
			}
		}

		$this->disconnect($ldap);

		return $authUser;
	}

	/**
	 * Escape a text for usage in LDAP query
	 * @param string $text text
	 * @return string escaped text
	 */
	public static function escapeLdap($text) {
		$replaces = array(	'\\' => '\5c',
				'*' => '\2a',
				'(' => '\28',
				')' => '\29',
				'/' => '\2f',
				"\x00" => '\00');

		return str_replace(array_keys($replaces), array_values($replaces), $text);
	}

	/**
	 * Normalize LDAP data
	 * @param mixed[] $data return of ldap_* functions
	 * @return mixed[] same data without count entries and without array for entries with 1 element only
	 */
	public static function normalizeLdapData(array $data) {
		foreach($data as $k => $v) {
			if (is_numeric($k) || ($k === 'count')) {
				unset($data[$k]);
			} else {
				if (is_array($v)) {
					unset($v['count']);
					if (count($v) === 1) {
						$v = \salt\first($v);
					}
				}
				$data[$k] = $v;
			}
		}

		return $data;
	}

}
