<?php
/**
 * SsoProfilViewHelper class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\BaseViewHelper;
use salt\Field;

/**
 * ViewHelper for SsoProfil
 */
class SsoProfilViewHelper extends BaseViewHelper {

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to edit
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \salt\BaseViewHelper::edit()
	 */
	public function edit(Base $object, Field $field, $value, $format, $params) {
		switch ($field->name) {
			case 'theme' :
				$params['type'] = 'select';
				$params['options'] = SsoProfil::getThemesList();
				if ($object->appliId !== NULL) {
					$params['options'] = array(
						SsoProfil::RECOMMENDED_PROFILE => '-- '.L::label_theme_use_recommended.' --',
					) + $params['options'];
				}
				return parent::edit($object, $field, $value, $format, $params);
			break;
		}
		return parent::edit($object, $field, $value, $format, $params);
	}
}