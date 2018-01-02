<?php
/**
 * SsoProfilDAOConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\DAOConverter;
use salt\Field;

/**
 * DAOConverter for SsoProfil
 */
class SsoProfilDAOConverter extends DAOConverter {

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to edit
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \salt\DAOConverter::edit()
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