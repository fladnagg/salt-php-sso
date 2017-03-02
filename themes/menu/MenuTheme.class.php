<?php namespace sso;

use salt\Field;

class MenuTheme extends VisibleTheme {

	protected function metadata() {
		parent::metadata();
		self::MODEL()->registerFields(
			Field::newText('position', 'Position d\'origine', FALSE, 'top-right', array(
				'top-left' => 'En haut à gauche', 
				'top-right' => 'En haut à droite', 
				'bottom-right' => 'En bas à droite', 
				'bottom-left' => 'En bas à gauche', 
			))
		);
	}
	
	public function description() {
		return 'Un menu vertical déroulant dans un coin de la page';
	}
	
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

