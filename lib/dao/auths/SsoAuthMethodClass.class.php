<?php
/**
 * SsoAuthMethodClass class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao\auths
 */
namespace sso;

use salt\Field;
use salt\Salt;

/**
 * Auth method based on another class (plugin)
 */
class SsoAuthMethodClass implements SsoAuthMethodInterface {

	/**
	 * Retrive class options
	 * @return Field[] Option list of child class
	 */
	public function getClassOptions() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $value current values, as key => value, each key is a Field name of a previous call.
	 * 	Can be used for display example of current option values in option description for example.
	 * @see \sso\SsoAuthMethodInterface::getOptions()
	 */
	public function getOptions($value = NULL) {

		$classes = Salt::getClassesByPath(realpath(SSO_RELATIVE.'plugins'));

		$extraOptions = array();
		$authMethods = array();
		foreach($classes as $cl => $file) {

			try {
				$c = new $cl();
				if ($c instanceof SsoAuthMethodClass) {
					$authMethods[$cl] = $cl;

					if (isset($value['className']) && ($cl === $value['className'])) {
						$extraOptions = $c->getClassOptions();
					}
				}
			} catch (\Exception $ex) {
				// do nothing : all errors are ignored
				// FIXME : do not really works... parse errors on plugins files interrupt execution :/
				// there is some workaround but complex... like http://php.net/manual/fr/function.php-check-syntax.php#86466
			}
		}

		$authsMethodList = $authMethods;

		return array_merge(array(
			Field::newText('className', L::field_class_name, TRUE, NULL, $authsMethodList)->displayOptions(array('type' => 'select')),
		),$extraOptions);
	}

	/**
	 * Check a class define a method
	 *
	 * @param string $delegate Name of a class
	 * @param string $method Name of a method
	 * @return boolean TRUE if method is defined in class, FALSE if the method if defined only in parent classes or does not exists at all
	 */
	private function isDelegateDefineMethod($delegate, $method) {
		try {
			// avoid recursive call : check delegate really define the method
			$ref = new \ReflectionClass($delegate);
			$m = $ref->getMethod($method);
			$cl = $m->getDeclaringClass();

			if ($cl->name === $ref->name) {
				return TRUE;
			}
		} catch (\Exception $ex) {
			// do nothing
		}
		return FALSE;
	}

	/**
	 * {@inheritDoc}
	 * @param string $user user
	 * @param string $pass clear password
	 * @param stdClass $options all auth method options
	 * @see \sso\SsoAuthMethodInterface::auth()
	 */
	public function auth($user, $pass, \stdClass $options) {
		$authMethod = $options->className;
		$delegate = new $authMethod();
		if (!$this->isDelegateDefineMethod($delegate, 'auth')) {
			return NULL;
		}
		return $delegate->auth($user, $pass, $options);
	}

	/**
	 * {@inheritDoc}
	 * @param string $search search
	 * @param \stdClass $options auth method options
	 * @see \sso\SsoAuthMethodInterface::search()
	 */
	public function search($search, \stdClass $options) {
		$authMethod = $options->className;
		$delegate = new $authMethod();
		if (!$this->isDelegateDefineMethod($delegate, 'search')) {
			return NULL;
		}
		return $delegate->search($search, $options);
	}

}
