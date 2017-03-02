<?php namespace sso;

use salt\Base;
use salt\Field;

abstract class Theme extends Base {

	protected function metadata() {
		self::MODEL()
			->registerTableName('') // not for persistence as a table
			->registerFields(
				Field::newText('id', 'ID')
		);
	}
	
	public static function get(SsoProfil $profil) {
		$class = ucfirst(strtolower($profil->theme)).'Theme';
		$file = SSO_RELATIVE.'themes/'.$profil->theme.'/'.$class.'.class.php';
			
		if (file_exists($file)) {
			include_once($file);
			$class = __NAMESPACE__.'\\'.$class;
			$theme = new $class();
			$theme->id = $profil->theme;
			try {
				$options = json_decode($profil->options, TRUE);
			} catch (\Exception $ex) {
				// do nothing
			}
			if (is_array($options)) {
				foreach($options as $field => $value) {
					try {
						$theme->$field = $value;
					} catch (\Exception $ex) {
						// do nothing : if a theme change of parameters, field can not exists anymore.
						// we don't load it, and it will not be persisted at last save
					}
				}
			}
		} else {
			throw new BusinessException('Theme inconnu : '.$profil->theme);
		}
		return $theme;
	}
	
	public static function set(SsoProfil $profil, Theme $theme) {
		$options = array();
		foreach($theme->MODEL()->getFields() as $fieldName => $_) {
			if ($fieldName !== 'id') {
				$options[$fieldName] = $theme->$fieldName;
			}
		}
		
		$profil->options = json_encode($options);
	}
	
	public function displayCss() {
		$content = file_get_contents($this->getCssFile());
		$options = $this->decodeOptions($this->getOptions());
		
		// not a full css escape, but good enought, and we don't want to escape all
		// meta characters : user can enter #ffffff instead of white
		foreach($options as $name => $value) {
			$value = preg_replace('/(["\\\$;\{\}])/', '\\\$1', $value, -1, $count);
			if (preg_match('/\s/', $value) || ($count > 0)) {
				$value = '"'.$value.'"';
			}
			$content = str_replace('$'.$name.'$', $value, $content);
		}
		
		return $content;
	}
	
	public function getCssFile() {
		return SSO_RELATIVE.'themes/'.$this->id.'/'.$this->id.'.css';
	}
	
	public function getOptions() {
		$options = array();
		foreach(parent::MODEL()->getFields() as $field => $_) {
			if ($field !== 'id') {
				$options[$field] = $this->$field;
			}
		}
		return $options;
	}

	abstract public function description();
	public function decodeOptions(array $options) {
		return $options;
	}
}