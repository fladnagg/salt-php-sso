<?php
/**
 * SsoGroupable interface
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

/**
 * All DAO that can be grouped will implements it.
 */
interface SsoGroupable  {

	/** character used as separator in GROUP_CONCAT */
	const GROUP_CONCAT_SEPARATOR_CHAR = "\0";
	/** extra column name for group list */
	const GROUPS = 'groups';
	/** extra column name for belong to group */
	const WITH_GROUP='in_group';
	/** extra form name for old in_group value */
	const EXISTS_NAME = 'exists';

	/**
	 * Return group element type
	 * @return int SsoGroupElement::TYPE_*
	 */
	public static function getGroupType();

	/**
	 * ID of group element
	 * @return mixed id
	 */
	public function getId();

	/**
	 * Field name that contain name of group element
	 * @return string field name
	 */
	public function getNameField();

	/**
	 * Field name that contain id of group element
	 * @return string field name
	 */
	public function getIdField();
}