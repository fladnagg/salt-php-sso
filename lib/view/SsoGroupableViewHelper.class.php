<?php
/**
 * SsoGroupableViewHelper class
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
 * Parent ViewHelper class for groupable objects
 */
class SsoGroupableViewHelper extends BaseViewHelper {

	/** prefix for group values when select contain element AND group values */
	const PREFIX_GROUP_VALUE = '__g';

	/**
	 * {@inheritDoc}
	 * @param Field $field the field to display
	 * @param string $format format to use for change the output
	 * @see \salt\BaseViewHelper::column()
	 */
	public function column(Field $field, $format = NULL) {
		global $Input;
		switch($field->name) {
			case SsoGroupable::WITH_GROUP:
				return $Input->HTML(L::label_in_group).' '.FormHelper::input(NULL, 'checkbox', FALSE, array(), array('onclick' =>
	"javascript:$(this).closest('table').find('input[type=checkbox]').not('.hidden').not(this).prop('checked', this.checked).change();"
				));
			break;
			case SsoGroupable::GROUPS :
				return $Input->HTML(L::admin_group);
			break;
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
			case SsoGroupable::WITH_GROUP :
				$field = Field::newBoolean(SsoGroupable::WITH_GROUP, L::field_selected, FALSE);
				$result = parent::edit($object, $field, $value, $format, $params);
				$result.= FormHelper::input(SsoGroupable::EXISTS_NAME, 'checkbox', TRUE, array('hidden'));

				$name = SsoGroupable::WITH_GROUP;

				$jsCode=<<<JS
$(function() {
	$("input[type=checkbox][name$='[{$name}]']").change(function() {
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
					.'<img src="'.SSO_WEB_RELATIVE.'images/edit.png" alt="'.$Input->HTML(L::button_modify).'" title="'.$Input->HTML(L::button_modify).'"
						onclick="$(this).parent().next(\'select\').show().removeProp(\'disabled\'); $(this).parent().hide()"/>'.'</span>'
							// FIXME : trick for multiple select. Remove when supported by SALT
					.FormHelper::select(SsoGroupable::GROUPS.'][', $options, $selected, array('hidden'), array('multiple' => 'multiple', 'disabled' => 'disabled', 'size' => max(count($options)-1, 2)));
			break;
		}
		return parent::edit($object, $field, $value, $format, $params);
	}

	/**
	 * {@inheritDoc}
	 * @param Base $object object that contains the value
	 * @param Field $field the field
	 * @param mixed $value the value to display
	 * @param string $format format to use
	 * @param mixed[] $params parameter passed to Base->FORM or Base->VIEW method
	 * @see \salt\BaseViewHelper::show()
	 */
	public function show(Base $object, Field $field, $value, $format, $params) {
		if ($field->name === SsoGroupable::GROUPS) {
			return '';
		}
		return parent::show($object, $field, $value, $format, $params);
	}


	/**
	 * Retrieve groups options for a type
	 * @param int $type SsoGroupElement::TYPE_*
	 * @param mixed[] $contentsByTypeGroup [type => [group => ['count' => number, 'tooltip' => text]]]
	 * @return mixed[] array group => value (for select display)
	 */
	public static function getGroupOptions($type, array $contentsByTypeGroup) {
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