<?php
/**
 * SsoAdministrable interface
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Pagination;
use salt\DBResult;

/**
 * All DAO that can be modified in administration page will implement it
 */
interface SsoAdministrable {

	/** criteria for retrieve object with linked objects and not the object fields only */
	const WITH_DETAILS = 'with-details';

	/**
	 * Retrieve objects
	 * @param mixed[] $criteria criteria as key => value
	 * @param Pagination $pagination pagination object
	 * @return DBResult objects matchs criteria
	 */
	public static function search(array $criteria, Pagination $pagination = NULL);
}