<?php namespace sso;

use salt\Pagination;

interface SsoAdministrable {
	
	const WITH_DETAILS = 'with-details';

	public static function search(array $criteres, Pagination $pagination = NULL);
}