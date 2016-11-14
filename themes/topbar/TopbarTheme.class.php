<?php namespace sso;

use salt\Field;

class TopbarTheme extends VisibleTheme {

	protected function metadata() {
		return array_merge(parent::metadata(), array(
				Field::newText('bgcolor', 'Couleur de fond', FALSE, 'black'),
		));
	}

	public function description() {
		return 'Un menu fixe horizontal en haut de la page';
	}

	
	public function decodeOptions(array $options) {
		$options['connectedAs'] = 'Connecté en tant que : ';
		return $options;
	}
}

