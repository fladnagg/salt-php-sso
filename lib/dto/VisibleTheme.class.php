<?php namespace sso;

use salt\Field;

/**
 * @property string userBgcolor
 * @property string userColor
 * @property string menuBgcolor
 * @property string menuColor
 * @property string menuBgHover
 * @property string menuHover
 *
 */
abstract class VisibleTheme extends Theme {

	protected function metadata() {
		parent::metadata();
		self::MODEL()->registerFields(
			Field::newText('userBgcolor', 'Couleur de fond du bloc Utilisateur', FALSE, 'InfoBackground'),
			Field::newText('userColor', 'Couleur de texte du bloc Utilisateur', FALSE, 'InfoText'),
			Field::newText('menuBgcolor', 'Couleur de fond du bloc Menu', FALSE, 'Menu'),
			Field::newText('menuColor', 'Couleur de texte du bloc Menu', FALSE, 'MenuText'),
			Field::newText('menuBgHover', 'Couleur de fond au survol du Menu', FALSE, 'highlight'),
			Field::newText('menuHover', 'Couleur de texte au survol du Menu', FALSE, 'highlightText')
		);
	}
}

