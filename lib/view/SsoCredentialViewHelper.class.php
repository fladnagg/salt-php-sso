<?php namespace sso;

use salt\Base;
use salt\BaseViewHelper;
use salt\Field;
use salt\FormHelper;

class SsoCredentialViewHelper extends BaseViewHelper {
	
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
	
	public function show(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		switch($field->name) {
			case 'description' :
				return nl2br($Input->HTML($value));
			break;
		}

		return parent::show($object, $field, $value, $format, $params);
	}
	
	public function edit(Base $object, Field $field, $value, $format, $params) {
		static $applis = NULL;
		static $users = NULL;

		if (in_array($field->name, array('appli', 'user'))) {
			// keeping previous values in case of errors
			$v = FormHelper::getValue($field->name);
			if (isset($v)) {
				$value = $v;
			}
		}
		
		switch($field->name) {
			case 'appli':
				if ($format === 'search') {
					return FormHelper::input($field->name, 'text');
				}

				$applis=array();

				if (isset($params['applis'][$value])) {
					$applis[$value] = $params['applis'][$value];
				}
				
				$groupes = $this->getGroupOptions(SsoGroupElement::TYPE_APPLI, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$applis+array('Groupes' => array('group' => $groupes));
				
				if (($value === NULL) && ($object->appli_group !== NULL)) {
					$value = SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$object->appli_group;
				}
				
				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
 				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=applis&amp;edit='.substr($value, strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE)).'">'
 				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="Modifier le groupe" title="Modifier le groupe"/>'
 				.'</a>';
				;
				
				$result.=$this->autocompleteSelect(FormHelper::getName($field->name), SSO_WEB_RELATIVE.'ajax.php?applis');
				
				return $result;

			break;
			case 'user':
				if ($format === 'search') {
					return FormHelper::input($field->name, 'text');
				}
				
				$users=array();
				if (isset($params['users'][$value])) {
					$users[$value] = $params['users'][$value]; 
				}

				$groupes = $this->getGroupOptions(SsoGroupElement::TYPE_USER, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$users+array('Groupes' => array('group' => $groupes));
				
				if (($value === NULL) && ($object->user_group !== NULL)) {
					$value = SsoGroupableViewHelper::PREFIX_GROUP_VALUE.$object->user_group;
				}

				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=users&amp;edit='.substr($value, strlen(SsoGroupableViewHelper::PREFIX_GROUP_VALUE)).'">'
				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="Modifier le groupe" title="Modifier le groupe"/>'
				.'</a>';
				;

				$result.=$this->autocompleteSelect(FormHelper::getName($field->name), SSO_WEB_RELATIVE.'ajax.php?users');
				
				return $result;
			break;
			case 'status':
				if ($format === 'search') {
					return FormHelper::select($field->name, array_merge(array('' => ''), $field->values));
				}
			break;
			default:
		}

		return parent::edit($object, $field, $value, $format, $params);
	}
	
	private function autocompleteSelect($origin, $url) {
		$result = <<<FORM
<div class="overlay select_autocomplete">
	<div class="input_autocomplete">
		Recherche : <input class="search_autocomplete" type="text"/>
		<input type="button" value="Valider" onclick="javascript: addAutocompleteElement(this, '{$origin}', true)"/>
		<input type="button" value="Annuler" onclick="javascript: addAutocompleteElement(this, '{$origin}', false)"/>
	</div>
</div>
<script type="text/javascript">$(function() {setupAutocompleteSelect('{$origin}', '{$url}')})</script>
FORM;

		$minAutocompleteSearch = SSO_MIN_AUTOCOMPLETE_CHARACTERS;
		
		$js=<<<JS
function addAutocompleteElement(selected, origin, add) {

	var origin=$('[name="'+origin+'"]');

	var newValue = origin.data('old-selected');
	
	if (add)  {
		var element=$(selected).parent('.input_autocomplete').find('.search_autocomplete');
		var item = element.data('item');
		if ((item == undefined) || (item.label != element[0].value)) {
			return false;
		}
		
		if (origin.find('option[value="'+item.value+'"]').length == 0) {
			var last = origin.children('option[value="_select_autocomplete"]')
			var chooseOption = '<'+'option value="'+item.value+'" title="">'+item.label+'<'+'/option>';
			last.before(chooseOption);
		}
		newValue = item.value;
	}
	origin.find(':selected').prop('selected', false);
	origin.find('option[value="'+newValue+'"]').prop('selected', true);
	origin.change();
	
	$(selected).closest('.overlay').hide();
}

function setupAutocompleteSelect(origin, url) {
	var origin=$('[name="'+origin+'"]')
	var last = origin.children('option').last();
	var chooseOption = '<'+'option value="_select_autocomplete" title="">Choisir un élément...<'+'/option>';
	last.after(chooseOption);

	origin.change(function() {
		if (this.value == '_select_autocomplete') {
			var div=$(this).siblings('.select_autocomplete');
			div.find('.search_autocomplete').val('');
			showOverlayDialog(div);
		} else {
			$(this).data('old-selected', this.value);
		}
	}).change();
	var ac = origin.siblings('.select_autocomplete').find('.search_autocomplete');
	ac.data('baseURL', url);
}

$(function() {
	$('.search_autocomplete').autocomplete({
		minLength: {$minAutocompleteSearch},
		search: function(e, ui) {
			var prev = $(this).data('previous-search');

			if (prev !== this.value) {
				$(this).autocomplete('option', 'source', $(this).data('baseURL'));
			}
			$(this).data('previous-search', this.value);
		},
		select: function(e, ui) {
			if (ui.item.offset !== undefined) {
				e.preventDefault();
				
				var that = $(this);
				that.autocomplete('option', 'source', that.data('baseURL')+'&'+'offset='+ui.item.offset );

				setTimeout(function() {
					that.autocomplete("search");
				}, 1);
				return;
			}
			$(this).data('item', {'value': ui.item.value, 'label': ui.item.label});
			ui.item.value= ui.item.label; // display real value in text
		},
	})
});
JS;
		FormHelper::registerJavascript('autocompleteSelect', $js);
		
		return $result;
	}
	
}