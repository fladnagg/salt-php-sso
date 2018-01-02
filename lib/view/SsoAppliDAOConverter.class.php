<?php
/**
 * SsoAppliDAOConverter class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view
 */
namespace sso;

use salt\Base;
use salt\Field;
use salt\FormHelper;
use salt\Salt;

/**
 * DAOConverter for SsoAppli
 */
class SsoAppliDAOConverter extends SsoGroupableDAOConverter {

	/**
	 * @var string[] list of help texts : fieldName => helpText */
	private static $HELP = array(
		'path' => L::help_appli_path,
		'handler' => L::help_appli_handler,
		'icon' => L::help_appli_icon,
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

		if (($format === 'columns') && isset(self::$HELP[$field->name])) {
			$result.='&nbsp;<img src="'.SSO_WEB_RELATIVE.'images/help.png" alt="aide" title="'.$Input->HTML(self::$HELP[$field->name]).'" class="aide"/>';
		}

		return $result;
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

		switch($field->name) {
			case 'handler':
				return FormHelper::select($field->name, $this->handlersList(), $value);
			break;
		}

		return parent::edit($object, $field, $value, $format, $params);
	}

	/**
	 * Retrieve handler list
	 * @return string[] className => className
	 */
	private function handlersList() {
		$classes = Salt::getClassesByPath(realpath(SSO_RELATIVE.'plugins'));

		$handlers = array();
		foreach($classes as $cl => $file) {

			try {
				$c = new $cl();
				if ($c instanceof Handler) {
					$handlers[$cl] = $cl;
				}
			} catch (\Exception $ex) {
				// do nothing : all errors are ignored
				// FIXME : do not really works... parse errors on plugins files interrupt execution :/
				// there is some workaround but complex... like http://php.net/manual/fr/function.php-check-syntax.php#86466
			}
		}
		return array('' => '')+$handlers;
	}

}