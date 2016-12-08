<?php namespace sso;

use salt\Base;
use salt\BaseViewHelper;
use salt\Field;
use salt\FormHelper;

class SsoGroupableViewHelper extends BaseViewHelper {

	const PREFIX_GROUP_VALUE = '__g';
	
	public function column(Field $field, $format = NULL) {
		switch($field->name) {
			case SsoGroupable::WITH_GROUP: 
				return 'Appartient au groupe '.FormHelper::input(NULL, 'checkbox', FALSE, array(), array('onclick' =>
	"javascript:$(this).closest('table').find('input[type=checkbox]').not('.hidden').not(this).prop('checked', this.checked).change();"
				));
			break;
			case SsoGroupable::GROUPS : 
				return 'Groupes';
			break;
		}
		return parent::column($field, $format);
	}
	
	public function edit(Base $object, Field $field, $value, $format, $params) {
		global $Input;

		
		switch ($field->name) {
			case SsoGroupable::WITH_GROUP :
				$field = Field::newBoolean(SsoGroupable::WITH_GROUP, 'Sélectionné', FALSE);
				$result = parent::edit($object, $field, $value, $format, $params);
				$result.= FormHelper::input(SsoGroupable::EXISTS_NAME, 'checkbox', TRUE, array('hidden'));
				
				$jsCode=<<<'JS'
$(function() {
	$("input[type=checkbox][name$='[in_group]']").change(function() {
		$(this).closest('tr')[0].style.fontWeight=this.checked?'bold':'';
	}).change()
})
JS;
				FormHelper::registerJavascript('bold TR selected', $jsCode);
				
				return $result;
			break;
			case SsoGroupable::GROUPS:
				if ($value !== NULL) {
					$value = explode(SsoUser::GROUP_CONCAT_SEPARATOR_CHAR, $value);
				} else {
					$value = array();
				}
				
				$options = SsoGroup::getActive($object->getGroupType());
				
				$selected = array();
				foreach($options as $k => $v) {
					if (in_array($v, $value)) {
						$selected[] = $k;
					}
				}
				$options+=array('-1' => array('value' => '', 'class' => 'hidden', 'selected' => 'selected'));

				$title = $value;
				if (count($title) > SSO_MAX_TOOLTIP_ELEMENTS) {
					$title = array_slice($title, 0, SSO_MAX_TOOLTIP_ELEMENTS);
					$title[] = '...';
				}
				
				return '<span class="aide" title="'.$Input->HTML(implode("\n", $title)).'">'.$Input->HTML(count($value)).'&nbsp;'
					.'<img src="'.SSO_WEB_RELATIVE.'images/edit.png" alt="Modifier" title="Modifier" onclick="$(this).parent().next(\'select\').show().removeProp(\'disabled\'); $(this).parent().hide()"/>'.'</span>'
							// FIXME : trick for multiple select. Remove when supported by SALT
					.FormHelper::select(SsoGroupable::GROUPS.'][', $options, $selected, array('hidden'), array('multiple' => 'multiple', 'disabled' => 'disabled', 'size' => count($options)-1));
			break;
		}
		return parent::edit($object, $field, $value, $format, $params);
	}
	
	public function show(Base $object, Field $field, $value, $format, $params) {
		if ($field->name === SsoGroupable::GROUPS) {
			return '';
		}
		return parent::show($object, $field, $value, $format, $params);
	}

}