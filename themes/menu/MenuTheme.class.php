<?php
/**
 * MenuTheme class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\themes\menu
 */
namespace sso;

use salt\Field;

/**
 * Classic Menu theme class
 * @property string $position
 */
class MenuTheme extends VisibleTheme {

	/**
	 * {@inheritDoc}
	 * @see \sso\VisibleTheme::metadata()
	 */
	protected function metadata() {
		parent::metadata();
		self::MODEL()->registerFields(
			Field::newText('position', L::label_theme_position_field, FALSE, 'top-right', array(
				'top-left' => L::label_theme_position_top_left,
				'top-right' => L::label_theme_position_top_right,
				'bottom-right' => L::label_theme_position_bottom_right,
				'bottom-left' => L::label_theme_position_bottom_left,
			))
		);
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\Theme::description()
	 */
	public function description() {
		return L::label_theme_description_menu;
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $options fieldName => value
	 * @see \sso\Theme::decodeOptions()
	 */
	public function decodeOptions(array $options) {
		list($vertical, $horizontal) = explode('-', $options['position']);
		unset($options['position']);

		$positions = array('top', 'bottom', 'right', 'left');
		$results = array_merge($options, array_combine($positions, array_fill(0, count($positions), 'auto')));
		if ($vertical === 'bottom') {
			$results['bottom'] = '5px';
		} else {
			$results['top'] = '5px';
		}

		if ($horizontal === 'left') {
			$results['left'] = '5px';
		} else {
			$results['right'] = '5px';
		}

		return $results;
	}
}

