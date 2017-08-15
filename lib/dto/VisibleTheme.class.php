<?php
/**
 * VisibleTheme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dto
 */
namespace sso;

use salt\Field;

/**
 * Parent class of all visible theme
 * @property string $userBgcolor
 * @property string $userColor
 * @property string $menuBgcolor
 * @property string $menuColor
 * @property string $menuBgHover
 * @property string $menuHover
 * @property string $visible
 */
abstract class VisibleTheme extends Theme {

	/**
	 * {@inheritDoc}
	 * @see \sso\Theme::metadata()
	 */
	protected function metadata() {
		parent::metadata();
		self::MODEL()->registerFields(
			Field::newText('userBgcolor', 	L::field_userBgColor, FALSE, 'InfoBackground'),
			Field::newText('userColor', 	L::field_userColor, FALSE, 'InfoText'),
			Field::newText('menuBgcolor', 	L::field_menuBgColor, FALSE, 'Menu'),
			Field::newText('menuColor', 	L::field_menuColor, FALSE, 'MenuText'),
			Field::newText('menuBgHover', 	L::field_menuBgHover, FALSE, 'highlight'),
			Field::newText('menuHover', 	L::field_menuHover, FALSE, 'highlightText'),
			Field::newText('visible', 		L::field_visible, FALSE, 'fixed', array(
				'fixed' => L::yes,
				'absolute' => L::no,
			))
		);
	}
}

