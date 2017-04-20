<?php namespace sso;

use salt\Field;
use salt\DBHelper;

class SsoAuthMethodDatabase implements SsoAuthMethodInterface {

	private static $HELP=array(
		'authQuery' => "Requête devant remonter au moins une ligne si l'utilisateur est connu. Les placeholders :user et :password doivent être utilisés.",
		'dataQuery' => "Requête devant remonter une ligne correspondant à l'utilisateur. Le placeholder :user doit être utilisé. Les champs remontés doivent contenir [Champ ID] et [Champ Name]",
		'field_id' => "Nom du champ contenant l'identifiant unique de l'utilisateur retourné par la requête de donnée",
		'field_name' => "Nom du champ contenant le nom de l'utilisateur retourné par la requête de donnée",
	);
	
	public function getOptions($value = NULL) {
		return array(
			Field::newText('host', 'Hôte'),
			Field::newNumber('port', 'Port', FALSE, 3306),
			Field::newText('database', 'Database'),
			Field::newText('user', 'Utilisateur'),
			Field::newText('password', 'Mot de passe'),
			Field::newText('authQuery', 'Requête de vérification')->displayOptions(array('type' => 'textarea', 'rows' => 4, 'cols' => 80, 'title' => self::$HELP['authQuery'])),
			Field::newText('dataQuery', 'Requête de donnée')->displayOptions(array('type' => 'textarea', 'rows' => 4, 'cols' => 80, 'title' => self::$HELP['dataQuery'])),
			Field::newText('field_id', 'Champ ID')->displayOptions(array('title' => self::$HELP['field_id'])),
			Field::newText('field_name', 'Champ Name')->displayOptions(array('title' => self::$HELP['field_name'])),
		);
	}

	public function auth($user, $pass, \stdClass $options) {
		
		$authUser = \salt\first($this->search($user, $options));
		
		if ($authUser !== NULL) {
			$st = $db->execSQL($options->authQuery, array(':user' => $user, ':password' => $pass));
			if ($st->rowCount() > 0) {
				$authUser->logged();
			} else {
				$authUser->setError('Mot de passe incorrect');
			}
		}

		return $authUser;
	}
	
	public function search($search, \stdClass $options) {
		$name = 'SSO-Auth-DB-'.md5(implode('/', array($options->database, $options->port, $options->user)));
		
		if (!Sso::pingServer($options->host, $options->port, 5)) {
			throw new BusinessException('Unable to reach Database host '.$options->host.':'.$options->port);
		}
		
		DBHelper::register($name,
				$options->host, $options->port, $options->database, $options->user, $options->password, SSO_DB_CHARSET);
		$db = DBHelper::getInstance($name);
		
		$authUsers = array();
		
		if (!is_array($search)) {
			$search = array(':user' => $search);
		} else {
			return $authUsers; // not implemented: how search to other fields without placeholders ?
		}
		
		$st = $db->execSQL($options->dataQuery, $search);
		if ($st->rowCount() === 1) {
			$data = $st->fetch(\PDO::FETCH_ASSOC);
			$authUsers[] = new AuthUser($data[$options->field_id], $data[$options->field_name], $data);
		}
		
		return $authUsers;
	}
}
