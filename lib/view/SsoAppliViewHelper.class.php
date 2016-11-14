<?php namespace sso;

use salt\Field;
use salt\Base;
use salt\FormHelper;
use salt\Salt;

class SsoAppliViewHelper extends SsoGroupableViewHelper {

	private static $HELP = array(
		'path' => "Par rapport à la racine WEB du serveur",
		'handler' => "Correspond aux classes présentes dans le dossier plugins et implémentant sso\Handler",
		'icon' => "Par rapport à la racine de l'application
S'affichera dans la liste des applications du SSO",
	);
	
	public function column(Field $field, $format = NULL) {
		
		global $Input;
		
		$result = parent::column($field, $format);
		
		if (($format === 'columns') && isset(self::$HELP[$field->name])) {
			$result.='&nbsp;<img src="'.SSO_WEB_RELATIVE.'images/help.png" alt="aide" title="'.$Input->HTML(self::$HELP[$field->name]).'" class="aide"/>';
		}

		return $result;
	}
	
	public function edit(Base $object, Field $field, $value, $format, $params) {
		
		switch($field->name) {
			case 'handler':
				return FormHelper::select($field->name, $this->handlersList(), $value);
			break;
		}
		
		return parent::edit($object, $field, $value, $format, $params);
	}

	private function handlersList() {
		$classes = Salt::getClassesByPath(realpath(SSO_RELATIVE.'plugins'));

		$handlers = array();
		foreach($classes as $cl => $file) {
			
			try {
				$c = new $cl();
				if ($c instanceof Handler) {
					$handlers[$cl] = $cl;
				}
			} catch (\Exception $ex) {
				// do nothing : all errors are ignored
				// FIXME : do not really works... parse errors on plugins files interrupt execution :/
				// there is some workaround but complex... like http://php.net/manual/fr/function.php-check-syntax.php#86466
			}
		}
		return array('' => '')+$handlers;
	}
	
}