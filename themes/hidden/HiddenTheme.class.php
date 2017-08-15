<?php
/**
 * HiddenTheme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\themes\hidden
 */
namespace sso;

/**
 * Class for hidden menu theme
 */
class HiddenTheme extends Theme {

	/**
	 * {@inheritDoc}
	 * @see \sso\Theme::description()
	 */
	public function description() {
		return L::label_theme_description_hidden;
	}
}

