<?php namespace sso;

use salt\Field;
use salt\DBHelper;

class SsoAuthMethodDatabase implements SsoAuthMethodInterface {

	private static $HELP=array(
		'authQuery' => "Requête devant remonter au moins une ligne si l'utilisateur est connu. Les placeholders :user et :password doivent être utilisés.",
		'dataQuery' => "Requête devant remonter une ligne correspondant à l'utilisateur. Le placeholder :user doit être utilisé. Les champs remontés doivent contenir [Champ ID] et [Champ Name]",
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
		);
	}

	public function auth($user, $pass, \stdClass $options) {
		$name = 'SSO-Auth-DB-'.md5(implode('/', array($options->database, $options->port, $options->user)));

		DBHelper::register($name, 
				$options->host, $options->port, $options->database, $options->user, $options->password, SSO_DB_CHARSET);
		$db = DBHelper::getInstance($name);

		$authUser = NULL;
		
		$st = $db->execSQL($options->dataQuery, array(':user' => $user));
		if ($st->rowCount() === 1) {
			$data = $st->fetch(\PDO::FETCH_ASSOC);
			$authUser = new AuthUser($data[$options->field_id], $data[$options->field_name], $data);
			
			$st = $db->execSQL($options->authQuery, array(':user' => $user, ':password' => $pass));
			if ($st->rowCount() > 0) {
				$authUser->logged();
			} else {
				$authUser->setError('Mot de passe incorrect');
			}
		}

		return $authUser;
	}
	
	
}
