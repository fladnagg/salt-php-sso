<?php
/**
 * SsoGroupViewHelper class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\BaseViewHelper;
use salt\Field;
use salt\FormHelper;

/**
 * ViewHelper for Group class
 */
class SsoGroupViewHelper extends BaseViewHelper {

	/**
	 * @var string[] help text by field : fieldName => helpText */
	private static $HELP=array(
		'type' => L::help_group_type,
		'default' => L::help_group_default,
	);

	/**
	 * {@inheritDoc}
	 * @param Field $field the field to display
	 * @param string $format format to use for change the output
	 * @see \salt\BaseViewHelper::column()
	 */
	public function column(Field $field, $format = NULL) {
		global $Input;

		$colValue = NULL;
		$name = \salt\first(explode('_', $field->name));

		switch($name) {
			case 'users':
				$colValue = L::group_type_user;
			break;
			case 'applis':
				$colValue = L::group_type_appli;;
			break;
			case 'auths':
				$colValue = L::group_type_auth;
			break;
			case 'type':
				$colValue = L::label_admin_enabled;
			break;
			case 'default':
				$colValue = L::field_default;
			break;
			case 'types':
				return $Input->HTML(L::label_admin_enabled_for);
			break;
		}

		if ($colValue !== NULL) {
			$colValue = $Input->HTML($colValue);
			if (in_array($name, array('type', 'default'))) {
				if ($format === 'columns') {
					return NULL;
				} else {
					$result = $colValue;
					if (isset(self::$HELP[$name])) {
						$result.='&nbsp;<img src="'.SSO_WEB_RELATIVE.'images/help.png" alt="aide" class="aide" title="'.$Input->HTML(self::$HELP[$name]).'" />';
					}
					return $result;
				}
			} else {
				if ($format === 'columns') {
					return array($colValue => array('type_'.$field->name, 'default_'.$field->name, $field->name));
				} else if ($format === 'subcolumns') {
					return '#';
				}
			}
		}

		return parent::column($field, $format);
	}

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
		global $Input;

		switch ($field->name) {
			case 'type_users':
				return FormHelper::input($field->name, 'checkbox', (bool)($object->types & pow(2, SsoGroupElement::TYPE_USER -1)));
			break;
			case 'type_applis':
				return FormHelper::input($field->name, 'checkbox', (bool)($object->types & pow(2, SsoGroupElement::TYPE_APPLI -1)));
			break;
			case 'type_auths':
				return FormHelper::input($field->name, 'checkbox', (bool)($object->types & pow(2, SsoGroupElement::TYPE_AUTH -1)));
			break;

			case 'default_users':
				if (($object->types & pow(2, SsoGroupElement::TYPE_USER - 1)) === 0) {
					return '';
				}
				return FormHelper::input($field->name, 'checkbox', (bool)($object->defaults & pow(2, SsoGroupElement::TYPE_USER -1)));
				break;
			case 'default_applis':
				if (($object->types & pow(2, SsoGroupElement::TYPE_APPLI - 1)) === 0) {
					return '';
				}
				return FormHelper::input($field->name, 'checkbox', (bool)($object->defaults & pow(2, SsoGroupElement::TYPE_APPLI -1)));
				break;
			case 'default_auths':
				if (($object->types & pow(2, SsoGroupElement::TYPE_AUTH - 1)) === 0) {
					return '';
				}
				return FormHelper::input($field->name, 'checkbox', (bool)($object->defaults & pow(2, SsoGroupElement::TYPE_AUTH -1)));
			break;

			case 'users' :
				return $this->editType($object, $params, 'users', SsoGroupElement::TYPE_USER);
			break;

			case 'applis' :
				return $this->editType($object, $params, 'applis', SsoGroupElement::TYPE_APPLI);
			break;

			case 'auths' :
				return $this->editType($object, $params, 'auths', SsoGroupElement::TYPE_AUTH);
				;
			break;

			case 'name' :
				$data = explode('-', $format, 2);
				$f = \salt\first($data);
				if (($f === 'list') && (count($data) > 1)) {
					$type = \salt\last($data);

					$params['name'] = 'group';
					$params['type'] = 'select';
					$params['options'] = SsoGroup::getActive($type);
					$params['options'] = array('' => '')+$params['options'];
				}
			break;

			case 'types' :

				$options = SsoGroupElement::MODEL()->type->values;
				if ($format === 'search') {
					$options = array('' => '')+$options;
				}

				return FormHelper::select($field->name, $options);
			break;

		}
		return parent::edit($object, $field, $value, $format, $params);
	}

	/**
	 * Return edit link for grouped object
	 * @param SsoGroup $object group
	 * @param mixed $params parameters from FORM(), contain tooltip data
	 * @param string $name type name
	 * @param int $type type ID SsoGroupElement::TYPE_*
	 * @return string HTML text with link for go to group element page for this type
	 */
	private function editType(SsoGroup $object, $params, $name, $type) {
		global $Input;
		$tooltipData = $params['tooltip'][$type][$object->id];
		$title = implode("\n", $tooltipData['tooltip']);

		if (($object->types & pow(2, $type - 1)) === 0) {
			return '';
		}

		$result='<span title="'.$title.'" class="aide">'.$tooltipData['count'].'</span>'
		.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;edit='.$object->id.'&amp;type='.$name.'">'
		.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="'.$Input->HTML(L::button_modify).'" title="'.$Input->HTML(L::button_modify).'"/>'
		.'</a>';

		return $result;
	}

}