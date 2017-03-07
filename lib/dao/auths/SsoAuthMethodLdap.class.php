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

	public function auth($user, $pass, \stdClass $options) {

		$authUser = NULL;
		
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
		$r = @ldap_search($ldap, $options->dn, '('.$options->field_id.'='.self::escapeLdap($user).')' );
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			@ldap_close($ldap);
			throw new BusinessException('Unable to search user from LDAP ('.$error.')');
		}
		$r = @ldap_get_entries($ldap, $r);
		if ($r === FALSE) {
			$error = ldap_error($ldap);
			@ldap_close($ldap);
			throw new BusinessException('Unable to browse user details from LDAP ('.$error.')');
		}
		if ($r['count'] === 0) {
			@ldap_close($ldap);
			return NULL; // unknown user
		} else {
			
			for($i = 0; $i < $r['count']; $i++) {
				$dn = $r[$i]['dn'];

				$data = self::normalizeLdapData($r[$i]);
				
				$authUser = new AuthUser($data[$options->field_id], $data[$options->field_name],$data);
				if (@ldap_bind($ldap, $dn, $pass)) {
					$authUser->logged();
					@ldap_close($ldap);
					return $authUser;
				} else {
					$authUser->setError(ldap_error($ldap));
				}
			} // each entries
		
			@ldap_close($ldap);
		}
		
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
