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
		}
		return parent::edit($object, $field, $value, $format, $params);
	}

}