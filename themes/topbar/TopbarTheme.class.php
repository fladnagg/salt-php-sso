<?php
/**
 * TopbarTheme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\themes\topbar
 */
namespace sso;

use salt\Field;

/**
 * Theme for top menu bar
 * @property string $bgcolor
 */
class TopbarTheme extends VisibleTheme {

	/**
	 * {@inheritDoc}
	 * @see \sso\VisibleTheme::metadata()
	 */
	protected function metadata() {
		parent::metadata();
		self::MODEL()->registerFields(
			Field::newText('bgcolor', L::label_theme_background_color, FALSE, 'black')
		);
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\Theme::description()
	 */
	public function description() {
		return L::label_theme_description_topbar;
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $options fieldName => value
	 * @see \sso\Theme::decodeOptions()
	 */
	public function decodeOptions(array $options) {
		$options['connectedAs'] = L::label_theme_connected_as;
		return $options;
	}
}

