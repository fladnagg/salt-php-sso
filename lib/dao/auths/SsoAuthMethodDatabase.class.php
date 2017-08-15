<?php
/**
 * SsoAuthMethodDatabase class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao\auths
 */
namespace sso;

use salt\Field;
use salt\DBHelper;

/**
 * Method based on database
 */
class SsoAuthMethodDatabase implements SsoAuthMethodInterface {

	/**
	 * {@inheritDoc}
	 * @param mixed[] $value current values, as key => value, each key is a Field name of a previous call.
	 * 	Can be used for display example of current option values in option description for example.
	 * @see \sso\SsoAuthMethodInterface::getOptions()
	 */
	public function getOptions($value = NULL) {
		return array(
			Field::newText('host', 			L::field_host),
			Field::newNumber('port', 		L::field_port, FALSE, 3306),
			Field::newText('database', 		L::field_database),
			Field::newText('user', 			L::field_db_user),
			Field::newText('password', 		L::field_db_password),
			Field::newText('authQuery', 	L::field_authQuery)->displayOptions(array('type' => 'textarea', 'rows' => 4, 'cols' => 80, 'title' => L::help_db_authQuery)),
			Field::newText('dataQuery', 	L::field_dataQuery)->displayOptions(array('type' => 'textarea', 'rows' => 4, 'cols' => 80, 'title' => L::help_db_dataQuery)),
			Field::newText('field_id', 		L::field_field_id)->displayOptions(array('title' => L::help_db_field_id)),
			Field::newText('field_name', 	L::field_field_name)->displayOptions(array('title' => L::help_db_field_name)),
		);
	}

	/**
	 * {@inheritDoc}
	 * @param string $user user
	 * @param string $pass clear password
	 * @param stdClass $options all auth method options
	 * @see \sso\SsoAuthMethodInterface::auth()
	 */
	public function auth($user, $pass, \stdClass $options) {

		$authUser = \salt\first($this->search($user, $options));

		if ($authUser !== NULL) {
			$st = $db->execSQL($options->authQuery, array(':user' => $user, ':password' => $pass));
			if ($st->rowCount() > 0) {
				$authUser->logged();
			} else {
				$authUser->setError(L::error_bad_password);
			}
		}

		return $authUser;
	}

	/**
	 * {@inheritDoc}
	 * @param string $search search
	 * @param \stdClass $options auth method options
	 * @see \sso\SsoAuthMethodInterface::search()
	 */
	public function search($search, \stdClass $options) {
		$name = 'SSO-Auth-DB-'.md5(implode('/', array($options->database, $options->port, $options->user)));

		if (!Sso::pingServer($options->host, $options->port, 5)) {
			throw new BusinessException(L::error_database_not_responding($options->host, $options->port));
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
