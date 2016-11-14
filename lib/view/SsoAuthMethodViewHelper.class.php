<?php namespace sso;

use salt\Field;
use salt\FormHelper;
use salt\Base;

class SsoAuthMethodViewHelper extends SsoGroupableViewHelper {

	private static $HELP = array(
		'default' => "Sera utilisé dans l'ordre alphabétique si aucune méthode n'est spécifiée pour un utilisateur",
		'create' => "Un nouvel utilisateur authentifié depuis cette source sera créé dynamiquement dans le SSO. Dans le cas contraire, l'utilisateur sera quand même créé mais n'aura accès a rien et son compte devra être validé par un administrateur.",
		'field_id' => "Nom du champ contenant l'identifiant de l'utilisateur",
		'field_name' => "Nom du champ contenant le nom de l'utilisateur",
	);
	
	public function column(Field $field, $format = NULL) {
		global $Input;
		$result = parent::column($field, $format);
		
		if (isset(self::$HELP[$field->name])) {
			$result.='&nbsp;<img src="'.SSO_WEB_RELATIVE.'images/help.png" class="aide" alt="aide" title="'.$Input->HTML(self::$HELP[$field->name]).'" />';
		}
		
		return $result;
	}

	public function edit(Base $object, Field $field, $value, $format, $params) {
		global $Input;
		
		if (($object->type === SsoAuthMethod::TYPE_LOCAL) && (!in_array($field->name, array('in_group', 'default', 'name')))) { // READONLY on LOCAL
			return parent::show($object, $field, $value, $format, $params);
		}
		
		switch($field->name) {
			case 'type' :
				$options = $object->getField($field->name)->values;
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
				foreach($options as $opt) {
					if (!isset($values[$opt->name])) {
						$values[$opt->name] = NULL;
					}
					$fields[]='<tr><td class="field">'.$Input->HTML($opt->text).'</td><td class="input">'.FormHelper::field($opt, NULL, NULL).'</td></tr>';
					$tooltip[]=$opt->text.' : '.$values[$opt->name];
				}
				
				$fields = implode('', $fields);
				$tooltip = implode("\n", $tooltip);
				
				$result = '<img src="'.SSO_WEB_RELATIVE.'images/edit.png" alt="Modifier" title="'.$Input->HTML($tooltip).'" onclick="javascript: modifyAuthOptions($(this).next(\'.overlay\')[0], \'show\')"/>';
				
				$name = $Input->HTML($object->name);
				
				$html=<<<HTML
<div class="overlay">
	<div>
		<b>Modifier les paramètres de {$name}</b><br/>
		<table>
		{$fields}
		</table>
		<input type="button" value="Valider" onclick="javascript: modifyAuthOptions(this, 'save')" />
		<input type="button" value="Annuler" onclick="javascript: modifyAuthOptions(this, 'cancel')"/>
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