<?php namespace sso;

use salt\Base;
use salt\Field;
use salt\FormHelper;

class SsoUserViewHelper extends SsoGroupableViewHelper {

	public function column(Field $field, $format=NULL) {
		global $Input;
		if ($field->name === 'auths') {
			return $Input->HTML('Autorisations');
		} else if ($field->name === 'password') {
			return parent::column($field, $format).'&nbsp;'.
				'<img src="'.SSO_WEB_RELATIVE.'images/help.png" class="aide" alt="aide" '.
					'title="Le mot de passe n\'est nécessaire que pour le type d\'authentification &quot;'.
					SsoAuthMethod::MODEL()->type->values[SsoAuthMethod::TYPE_LOCAL].'&quot;"/>';
		} else if ($field->name === 'password2') {
			return $Input->HTML('Confirmer le mot de passe');
		} else {
			return parent::column($field, $format);
		}
	}

	public function text(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		switch($field->name) {
			case 'admin' :
				return ($value == 1)?'Oui':'Non';
				break;
			default:
		}

		return parent::text($object, $field, $value, $format, $params);
	}

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
					.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="Modifier" title="Modifier"/>'
					.'</a></span>';
			break;
			case 'password' :
				if (strlen(trim($value)) > 0) {
					return 'Oui';
				} else {
					return 'Non';
				}
			break;
			
			case 'restrictIP' :
				return ($value)?'Oui':'Non';
			break;
			case 'restrictAgent' :
				return ($value)?'Oui':'Non';
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
	
	public function edit(Base $object, Field $field, $value, $format, $params) {
		
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

				$groupes = $this->getGroupOptions(SsoGroupElement::TYPE_AUTH, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$authsMethods+array('Groupes' => array('group' => $groupes));
				
				if (($value === NULL) && ($object->auth_group !== NULL)) {
					$value = SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$object->auth_group;
				}
				
				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=auths&amp;edit='.substr($value, strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE)).'">'
				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="Modifier le groupe" title="Modifier le groupe"/>'
				.'</a>';
				;

				return $result;

			break;
			
			case 'admin' :
				if ($format === 'search') {
					return FormHelper::select('admin', array('' => 'Tous', '1' => 'Oui', '0' => 'Non'));
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
	
	private function getGroupOptions($type, array $contentsByTypeGroup) {
		static $results = array();
		if (!isset($results[$type])) {
			$options = SsoGroup::getActive($type);
			$elements = $contentsByTypeGroup[$type];
			foreach($options as $k => $v) {
				$options[$k] = array('value' => $v.' ('.$elements[$k]['count'].')', 'title' => implode("\n", $elements[$k]['tooltip']));
			}
			$results[$type] = $options;
		}
	
		return $results[$type];
	}
}