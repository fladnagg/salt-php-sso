<?php
/**
 * MobileTheme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\themes\mobile
 */
namespace sso;

use salt\Field;

/**
 * Theme for menu with "mobile" look
 * @property string $position
 */
class MobileTheme extends VisibleTheme {

	/**
	 * {@inheritDoc}
	 * @see \sso\VisibleTheme::metadata()
	 */
	protected function metadata() {
		parent::metadata();
		self::MODEL()->registerFields(
			Field::newText('position', L::label_theme_position_field, FALSE, 'right', array(
				'right' => L::label_theme_position_right,
				'left' => L::label_theme_position_left,
			)),
			Field::newNumber('offset', L::label_theme_offset, FALSE, 10)
		);
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\Theme::description()
	 */
	public function description() {
		return L::label_theme_description_mobile;
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $options fieldName => value
	 * @see \sso\Theme::decodeOptions()
	 */
	public function decodeOptions(array $options) {

		$position =  $options['position'];
		unset($options['position']);

		if ($position === 'right') {
			$options['right'] = '0px';
			$options['left'] = 'auto';
		} else {
			$options['right'] = 'auto';
			$options['left'] = '0px';
		}

		return $options;
	}
}

