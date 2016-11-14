<?php namespace sso;

use salt\Base;
use salt\BaseViewHelper;
use salt\Field;

class SsoProfilViewHelper extends BaseViewHelper {

	public function edit(Base $object, Field $field, $value, $format, $params) {
		switch ($field->name) {
			case 'theme' :
				$params['type'] = 'select';
				$params['options'] = SsoProfil::getThemesList();
				if ($object->appliId !== NULL) {
					$params['options'] = array(
						SsoProfil::RECOMMENDED_PROFILE => '-- Utiliser le profil recommandé --',
					) + $params['options'];
				}
				return parent::edit($object, $field, $value, $format, $params);
			break;
		}
		return parent::edit($object, $field, $value, $format, $params);
	}
}