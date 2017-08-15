<?php
/**
 * SsoUserViewHelper class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\Field;
use salt\FormHelper;

/**
 * ViewHelper for SsoUser
 */
class SsoUserViewHelper extends SsoGroupableViewHelper {

	/**
	 * {@inheritDoc}
	 * @param Field $field the field to display
	 * @param string $format format to use for change the output
	 * @see \sso\SsoGroupableViewHelper::column()
	 */
	public function column(Field $field, $format=NULL) {
		global $Input;
		if ($field->name === 'auths') {
			return $Input->HTML(L::admin_credential);
		} else if ($field->name === 'password') {
			return parent::column($field, $format).'&nbsp;'.
				'<img src="'.SSO_WEB_RELATIVE.'images/help.png" class="aide" alt="aide" '.
					'title="'.$Input->HTML(L::help_user_password_locale(SsoAuthMethod::MODEL()->type->values[SsoAuthMethod::TYPE_LOCAL])).'"/>';
		} else if ($field->name === 'password2') {
			return $Input->HTML(L::field_confirm_password);
		} else {
			return parent::column($field, $format);
		}
	}

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \salt\BaseViewHelper::text()
	 */
	public function text(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		switch($field->name) {
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
	 * @see \sso\SsoGroupableViewHelper::show()
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
			case 'restrictIP' :
			case 'restrictAgent' :
				return $Input->HTML(($value)?L::yes : L::no);
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
	 * @see \sso\SsoGroupableViewHelper::edit()
	 */
	public function edit(Base $object, Field $field, $value, $format, $params) {
		global $Input;

		static $authsMethods = NULL;

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

				$groupes = SsoGroupableViewHelper::getGroupOptions(SsoGroupElement::TYPE_AUTH, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$authsMethods+array(L::admin_group => array('group' => $groupes));

				if (($value === NULL) && ($object->auth_group !== NULL)) {
					$value = SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$object->auth_group;
				}

				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=auths&amp;edit='.substr($value, strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE)).'">'
				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="'.$Input->HTML(L::button_modify_group).'" title="'.$Input->HTML(L::button_modify_group).'"/>'
				.'</a>';
				;

				return $result;

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
}