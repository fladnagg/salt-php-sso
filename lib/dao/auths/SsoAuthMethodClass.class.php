<?php namespace sso;

use salt\Field;
use salt\Salt;

class SsoAuthMethodClass implements SsoAuthMethodInterface {

	public function getClassOptions() {
		return array();
	}
	
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
			Field::newText('className', 'Nom classe', TRUE, NULL, $authsMethodList)->displayOptions(array('type' => 'select')),
		),$extraOptions);
	}
	
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
	
	public function auth($user, $pass, \stdClass $options) {
		$authMethod = $options->className;
		$delegate = new $authMethod();
		if (!$this->isDelegateDefineMethod($delegate, 'auth')) {
			return NULL;
		}
		return $delegate->auth($user, $pass, $options);
	}
	
	public function search($user, \stdClass $options) {
		$authMethod = $options->className;
		$delegate = new $authMethod();
		if (!$this->isDelegateDefineMethod($delegate, 'search')) {
			return NULL;
		}
		return $delegate->search($user, $options);
	}
	
}
