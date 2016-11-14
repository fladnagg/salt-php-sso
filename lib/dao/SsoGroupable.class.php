<?php namespace sso;

interface SsoGroupable  {
	
	const WITH_GROUP='in_group';
	const EXISTS_NAME = 'exists';

	public static function getGroupType();
	
	public function getId();
	
	public function getNameField();
	
	public function getIdField();
}