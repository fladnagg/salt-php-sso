<?php
/**
 * SsoCredentialDAOConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\DAOConverter;
use salt\Field;
use salt\FormHelper;

/**
 * DAOConverter for Credential
 */
class SsoCredentialDAOConverter extends DAOConverter {

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \salt\DAOConverter::show()
	 */
	public function show(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		switch($field->name) {
			case 'description' :
				return nl2br($Input->HTML($value));
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
	 * @see \salt\DAOConverter::edit()
	 */
	public function edit(Base $object, Field $field, $value, $format, $params) {
		global $Input;
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

				$groupes = SsoGroupableDAOConverter::getGroupOptions(SsoGroupElement::TYPE_APPLI, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableDAOConverter::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$applis+array(L::admin_group => array('group' => $groupes));

				if (($value === NULL) && ($object->appli_group !== NULL)) {
					$value = SsoGroupableDAOConverter::PREFIX_GROUP_VALUE.$object->appli_group;
				}

				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
 				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=applis&amp;edit='.substr($value, strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE)).'">'
 				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="'.$Input->HTML(L::button_modify_group).'" title="'.$Input->HTML(L::button_modify_group).'"/>'
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

				$groupes = SsoGroupableDAOConverter::getGroupOptions(SsoGroupElement::TYPE_USER, $params['tooltip']);
				foreach($groupes as $k => $v) {
					$groupes[SsoGroupableDAOConverter::PREFIX_GROUP_VALUE.$k]=$v;
					unset($groupes[$k]);
				}
				$opts = array(''=>array('value' => '', 'title' => ''))+$users+array(L::admin_group => array('group' => $groupes));

				if (($value === NULL) && ($object->user_group !== NULL)) {
					$value = SsoGroupableDAOConverter::PREFIX_GROUP_VALUE.$object->user_group;
				}

				$result = FormHelper::select($field->name, $opts, $value, array('selectTitle'), array('onchange' => 'javascript: selectTitle(this)'))
				.' <a href="'.SSO_WEB_RELATIVE.'?page=admin&amp;subpage=groups&amp;type=users&amp;edit='.substr($value, strlen(SsoGroupableDAOConverter::PREFIX_GROUP_VALUE)).'">'
				.'<img src="'.SSO_WEB_RELATIVE.'images/edit-out.png" alt="'.$Input->HTML(L::button_modify_group).'" title="'.$Input->HTML(L::button_modify_group).'"/>'
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

	/**
	 * Improve a select for autocomplete from ajax URL
	 *
	 * Add an option "Choose element" to select that will display an overlay with autocomplete field, and selected value is added back to select input
	 *
	 * @param string $origin name of the autocomplete input
	 * @param string $url ajax URL to request for values
	 * @return string HTML text for add after select for enable autocomplete
	 */
	private function autocompleteSelect($origin, $url) {
		global $Input;

		$search = $Input->HTML(L::label_search);
		$cancel = $Input->HTML(L::button_cancel);
		$validate = $Input->HTML(L::button_validate);

		$result = <<<FORM
<div class="overlay select_autocomplete">
	<div class="input_autocomplete">
		{$search} : <input class="search_autocomplete" type="text"/>
		<input type="button" value="{$validate}" onclick="javascript: addAutocompleteElement(this, '{$origin}', true)"/>
		<input type="button" value="{$cancel}" onclick="javascript: addAutocompleteElement(this, '{$origin}', false)"/>
	</div>
</div>
<script type="text/javascript">$(function() {setupAutocompleteSelect('{$origin}', '{$url}')})</script>
FORM;

		$minAutocompleteSearch = SSO_MIN_AUTOCOMPLETE_CHARACTERS;

		$chooseElement = L::label_choose_element;

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
	var chooseOption = $('<'+'option value="_select_autocomplete" title=""><'+'/option>');
	chooseOption.text('{$chooseElement}');
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
