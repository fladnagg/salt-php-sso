<?php
/**
 * SsoAuthMethodDAOConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Field;
use salt\FormHelper;
use salt\Base;
use salt\Model;

/**
 * DAOConverter for AuthMethod
 */
class SsoAuthMethodDAOConverter extends SsoGroupableDAOConverter {

	/**
	 * @var string[] help text for fields : fieldName => helpText */
	private static $HELP = array(
		'default' => L::help_auth_default,
		'create' => L::help_auth_create,
	);

		/**
	 * {@inheritDoc}
	 * @param Base $object The singleton object
	 * @param Field $field the field to display
	 * @param mixed $value the default value
	 * @param string $format format to use for change the output
	 * @param mixed $params others parameters
	 * @see \salt\DAOConverter::column()
	 */
	public function column(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		$result = parent::column($object, $field, $value, $format, $params);

		if (isset(self::$HELP[$field->name])) {
			$result.='&nbsp;<img src="'.SSO_WEB_RELATIVE.'images/help.png" class="aide" alt="aide" title="'.$Input->HTML(self::$HELP[$field->name]).'" />';
		}

		return $result;
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

		if (($object->type === SsoAuthMethod::TYPE_LOCAL) && ($field->name === 'create')) {
			return $Input->HTML(($value==0) ? L::no : L::yes);
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

		if (($object->type === SsoAuthMethod::TYPE_LOCAL) && (!in_array($field->name, array('in_group', 'default', 'name', 'groups')))) { // READONLY on LOCAL
			return self::show($object, $field, $value, $format, $params);
		}

		switch($field->name) {
			case 'type' :
				$options = $field->values;
				unset($options[SsoAuthMethod::TYPE_LOCAL]);
				if ($format === 'search') {
					$options = array('' => '')+$options;
				}
				return FormHelper::select($field->name, $options, NULL, $params);
			break;
			case 'options' :
				$values = json_decode($value, TRUE);
				if ($values === NULL) {
					$values = array();
				}

				$options = $object->getOptions($values);

				if (count($options) === 0) {
					return '';
				}

				$fields = array();
				$tooltip = array();
				/** @var Field $opt */
				foreach($options as $opt) {
					if (!isset($values[$opt->name])) {
						$values[$opt->name] = NULL;
					}
					$help = '';
					if (isset($opt->displayOptions['title'])) {
						$help = '&nbsp;<img src="'.SSO_WEB_RELATIVE.'images/help.png" alt="aide" title="'.$Input->HTML($opt->displayOptions['title']).'" class="aide" />';
						unset($opt->displayOptions['title']);
					}
					$fields[]='<tr><td class="field">'.$Input->HTML($opt->text).$help.'</td><td class="input">'.FormHelper::field($opt, NULL, NULL).'</td></tr>';
					$tooltip[]=$opt->text.' : '.$values[$opt->name];
				}

				$fields = implode('', $fields);
				$tooltip = implode("\n", $tooltip);

				$result = '<img src="'.SSO_WEB_RELATIVE.'images/edit.png" alt="modify" title="'.$Input->HTML($tooltip).'" onclick="javascript: modifyAuthOptions($(this).next(\'.overlay\')[0], \'show\')"/>';

				$title = $Input->HTML(L::label_modify_parameters_of($object->name));

				$buttonValidate = $Input->HTML(L::button_validate);
				$buttonCancel = $Input->HTML(L::button_cancel);

				$html=<<<HTML
<div class="overlay">
	<div>
		<b>{$title}</b><br/>
		<table>
		{$fields}
		</table>
		<input type="button" value="{$buttonValidate}" onclick="javascript: modifyAuthOptions(this, 'save')" />
		<input type="button" value="{$buttonCancel}" onclick="javascript: modifyAuthOptions(this, 'cancel')"/>
	</div>
</div>
HTML;
				$result.=$html;

				if (!is_array($params)) {
					$params = array();
				}
				$params['type'] = 'hidden';

				$result.=parent::edit($object, $field, $value, $format, $params);

				return $result;
			break;
		}

		return parent::edit($object, $field, $value, $format, $params);
	}

}