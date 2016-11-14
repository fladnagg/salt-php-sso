<?php namespace sso;

use salt\Field;

class MobileTheme extends VisibleTheme {

	protected function metadata() {
		
		return array_merge(parent::metadata(), array(
			Field::newText('position', 'Position', FALSE, 'right', array(
				'right' => 'A droite', 
				'left' => 'A gauche', 
			)),
			Field::newNumber('offset', 'Décalage (pixels)', FALSE, 10),
		));
	}
	
	public function description() {
		return 'Un menu vertical caché sur un bord de la page apparaissant au survol de la souris sur le symbole de menu';
	}
	
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

