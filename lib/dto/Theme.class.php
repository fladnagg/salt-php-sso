<?php
/**
 * Theme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dto
 */
namespace sso;

use salt\Base;
use salt\Field;

/**
 * Parent class for all themes
 * @property string $id
 */
abstract class Theme extends Base {

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
		self::MODEL()
			->registerTableName('') // not for persistence as a table
			->registerFields(
				Field::newText('id', L::field_id)
		);
	}

	/**
	 * Retrieve Theme object from SsoProfil
	 * @param SsoProfil $profil The profile
	 * @throws BusinessException if theme is unknown
	 * @return Theme the theme object in profile
	 */
	public static function getTheme(SsoProfil $profil) {
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
			throw new BusinessException(L::error_theme_unknown($profil->theme));
		}
		return $theme;
	}

	/**
	 * Set a theme in a profile
	 * @param SsoProfil $profil The profile
	 * @param Theme $theme The theme
	 */
	public static function setTheme(SsoProfil $profil, Theme $theme) {
		$options = array();
		foreach($theme->MODEL()->getFields() as $fieldName => $_) {
			if ($fieldName !== 'id') {
				$options[$fieldName] = $theme->$fieldName;
			}
		}

		$profil->options = json_encode($options);
	}

	/**
	 * Retrieve the CSS content of the theme
	 * @return string CSS content
	 */
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

	/**
	 * Retrieve CSS file name
	 * @return string CSS file name
	 */
	public function getCssFile() {
		return SSO_RELATIVE.'themes/'.$this->id.'/'.$this->id.'.css';
	}

	/**
	 * Retrieve all theme options
	 * @return mixed[] fieldName => value
	 */
	public function getOptions() {
		$options = array();
		foreach(parent::MODEL()->getFields() as $field => $_) {
			if ($field !== 'id') {
				$options[$field] = $this->$field;
			}
		}
		return $options;
	}

	/**
	 * Retrieve theme description
	 * @return string description
	 */
	abstract public function description();

	/**
	 * Decode options for flatten them and use simple replacement in css pattern file
	 *
	 * @param mixed[] $options fieldName => value
	 * @return mixed[] fieldName => value
	 */
	public function decodeOptions(array $options) {
		return $options;
	}
}