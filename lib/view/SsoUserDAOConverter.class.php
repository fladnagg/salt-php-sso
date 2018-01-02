<?php
/**
 * SsoUserDAOConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\Field;
use salt\FormHelper;
use salt\Model;

/**
 * DAOConverter for SsoUser
 */
class SsoUserDAOConverter extends SsoGroupableDAOConverter {

	/**
	 * {@inheritDoc}
	 * @param Base $object The singleton object
	 * @param Field $field the field to display
	 * @param mixed $value the default value
	 * @param string $format format to use for change the output
	 * @param mixed $params others parameters
	 * @see \sso\SsoGroupableDAOConverter::column()
	 */
	public function column(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		if ($field->name === 'auths') {
			return $Input->HTML(L::admin_credential);
		} else if ($field->name === 'password') {
			return parent::column($object, $field, $value, $format, $params).'&nbsp;'.
				'<img src="'.SSO_WEB_RELATIVE.'images/help.png" class="aide" alt="aide" '.
					'title="'.$Input->HTML(L::help_user_password_locale(SsoAuthMethod::MODEL()->type->values[SsoAuthMethod::TYPE_LOCAL])).'"/>';
		} else if ($field->name === 'password2') {
			return $Input->HTML(L::field_confirm_password);
		} else {
			return parent::column($object, $field, $value, $format, $params);
		}
	}

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \salt\DAOConverter::text()
	 */
	public function text(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		switch($field->name) {
			case 'restrictIP' :
			case 'restrictAgent' :
			case 'can_ask' :
			case 'admin' :
				return ($value == 1)?L::yes : L::no;
				break;
			default:
		}

		return parent::text($object, $field, $value, $format, $params);
	}

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \sso\SsoGroupableDAOConverter::show()
	 */
	public function show(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		switch($field->name) {
			case 'auths' ;
				if ($object->isNew()) {
					return '&nbsp;';
				}

				if ($value !== NULL) {
					$value = explode(SsoUser::GROUP_CONCAT_SEPARATOR_CHAR, $value);
				} else {
					$value = array();
				}

				$title = $value;
				if (count($title) > SSO_MAX_TOOLTIP_ELEMENTS) {
					$title = array_slice($title, 0, SSO_MAX_TOOLTIP_ELEMENTS);
					$title[] = '...';
				}

				return '<span class="aide" title="'.$Input->HTML(implode("\n", $title)).'">'.$Input->HTML(count($value))
					.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=credentials&amp;search[user]='.$object->id.'">'
					.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="'.$Input->HTML(L::button_modify).'" title="'.$Input->HTML(L::button_modify).'"/>'
					.'</a></span>';
			break;
			case 'password' :
				if (strlen(trim($value)) > 0) {
					return $Input->HTML(L::yes);
				} else {
					return $Input->HTML(L::no);
				}
			break;
			case 'timeout' :
				$result = '';
				foreach(SsoUser::intTimeoutToArray($value) as $k => $v) {
					$result.=$v.$k.' ';
				}
				return $result;
			break;
		}
		return parent::show($object, $field, $value, $format, $params);
	}

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to edit
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \sso\SsoGroupableDAOConverter::edit()
	 */
	public function edit(Base $object, Field $field, $value, $format, $params) {
		global $Input;

		static $authsMethods = NULL;
		static $applications = NULL;

		switch ($field->name) {
			case 'timeout' :
				$data = SsoUser::intTimeoutToArray($value);
				$result = '';
				foreach($data as $unit => $value) {
					$options = array();
					$options=SsoUser::unitRange($unit);
					$name='timeout['.$unit.']';
					if (strpos(FormHelper::getName('timeout'), ']') !== FALSE) {
						$name='timeout]['.$unit;
					}
					$result.=FormHelper::select($name, $options, $value).$unit.'&nbsp;';
				}
				return $result;
			case 'password' :
				if ($value !== NULL) {
					$value = str_repeat(' ', 3);
					FormHelper::setValue($field->name, $value);
				}
			break;
			case 'password2' :
				return FormHelper::input('password2', 'password', '');
			break;
			case 'auth':

				if ($authsMethods === NULL) {
					$authsMethods = array();
					foreach(SsoAuthMethod::search(array())->data as $row) {
						$authsMethods[$row->id] = array('value'=> $row->name, 'title' => '');
					}
				}

				$groupes = SsoGroupableDAOConverter::getGroupOptions(SsoGroupElement::TYPE_AUTH, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableDAOConverter::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$authsMethods+array(L::admin_group => array('group' => $groupes));

				if (($value === NULL) && ($object->auth_group !== NULL)) {
					$value = SsoGroupableDAOConverter::PREFIX_GROUP_VALUE.$object->auth_group;
				}

				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=auths&amp;edit='.substr($value, strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE)).'">'
				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="'.$Input->HTML(L::button_modify_group).'" title="'.$Input->HTML(L::button_modify_group).'"/>'
				.'</a>';
				;

				return $result;
			break;

			case 'auths':
				if ($format === 'search') {
					if ($applications === NULL) {
						$applications = array('' => '');
						foreach(SsoAppli::search(array())->data as $appli) {
							$applications[$appli->id] = $appli->name;
						}
					}
					return FormHelper::select('auths', $applications);
				}
			break;

			case 'name':
				if ($format === 'search') {
					$params['size'] = 20;
				}
			break;

			case 'admin' :
				if ($format === 'search') {
					return FormHelper::select('admin', array('' => L::all, '1' => L::yes, '0' => L::no));
				}
			break;

			case 'state':
				if ($format === 'search') {
					$field->nullable = TRUE;
					$value = NULL;
				}
			break;

		}
		return parent::edit($object, $field, $value, $format, $params);
	}

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field field
	 * @param mixed $value the value to convert
	 * @param string $format the format of the value
	 * @param mixed[] $params others parameters passed to convert function
	 * @return mixed the converted value
	 * @see \salt\DAOConverter::setterInput()
	 */
	public function setterInput(Base $object, Field $field, $value, $format, $params) {

		switch($field->name) {
			case 'timeout':
				if (is_array($value)) {
					$value = SsoUser::arrayToIntTimeout($value);
				}
			break;
		}

		return parent::setterInput($object, $field, $value, $format, $params);
	}
}