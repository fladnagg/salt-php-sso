<?php namespace sso;

use salt\Field;

class SsoAuthMethodLdap implements SsoAuthMethodInterface {

	private static $HELP=array(
		'field_id' => "Nom du champ contenant l'identifiant unique de l'utilisateur retourné par le LDAP",
		'field_name' => "Nom du champ contenant le nom de l'utilisateur retourné par le LDAP",
	);
	
	public function getOptions($value = NULL) {

		return array(
			Field::newText('host', 'Hôte')->displayOptions(array('size'=>40)),
			Field::newNumber('port', 'Port', FALSE, 389)->displayOptions(array('size'=>6)),
			Field::newText('bind_dn', 'Compte connexion')->displayOptions(array('size'=>80)),
			Field::newText('bind_pass', 'Mot de passe')->displayOptions(array('size'=>40)),
			Field::newText('dn', 'Base DN')->displayOptions(array('size'=>80)),
			Field::newText('field_id', 'Champ ID')->displayOptions(array('title' => self::$HELP['field_id'])),
			Field::newText('field_name', 'Champ Name')->displayOptions(array('title' => self::$HELP['field_name'])),
		);
	}

	private function connect(\stdClass $options) {
		$host = $options->host;
		if (strpos(strtolower($host), 'ldap://') === FALSE) {
			$host = 'ldap://'.$host;
		}
		if (!Sso::pingServer($host, $options->port, 5)) {
			throw new BusinessException('Unable to reach LDAP host '.$host.':'.$options->port);
		}
		
		$ldap = @ldap_connect($host, $options->port);
		if ($ldap === FALSE) {
			throw new BusinessException('Unable to connect to the LDAP '.$host.':'.$options->port);
		}
		@ldap_set_option($ldap, \LDAP_OPT_REFERRALS, TRUE);
		$r = @ldap_bind($ldap, $options->bind_dn, $options->bind_pass);
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			@ldap_close($ldap);
			throw new BusinessException('Unable to bind LDAP '.$host.':'.$options->port.' with provided DN and password ('.$error.')');
		}
		return $ldap;
	}
	
	private function disconnect($ldap) {
		@ldap_close($ldap);
	}
	
	public function search($search, \stdClass $options) {

		$ldap = $this->connect($options);
		$authUsers = $this->searchOnLdap($search, $options, $ldap);
		$this->disconnect($ldap);
		
		return $authUsers;
	}
	
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
			throw new BusinessException('Unable to search user from LDAP ('.$error.')');
		}
		$r = @ldap_get_entries($ldap, $r);
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			$this->disconnect($ldap);
			throw new BusinessException('Unable to browse user details from LDAP ('.$error.')');
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
	
	public static function escapeLdap($text) {
		$replaces = array(	'\\' => '\5c',
				'*' => '\2a',
				'(' => '\28',
				')' => '\29',
				'/' => '\2f',
				"\x00" => '\00');
	
		return str_replace(array_keys($replaces), array_values($replaces), $text);
	}
	
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
