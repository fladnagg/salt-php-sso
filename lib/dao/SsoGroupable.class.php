<?php namespace sso;

interface SsoGroupable  {
	
	const GROUP_CONCAT_SEPARATOR_CHAR = "\0";

	const GROUPS = 'groups';
	const WITH_GROUP='in_group';
	const EXISTS_NAME = 'exists';

	public static function getGroupType();
	
	public function getId();
	
	public function getNameField();
	
	public function getIdField();
}