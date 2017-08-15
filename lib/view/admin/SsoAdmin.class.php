<?php
/**
 * SsoAdmin class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\view\admin
 */
namespace sso;

use salt\Base;
use salt\DBResult;
use salt\DeleteQuery;
use salt\InsertQuery;

/**
 * Parent class for all Administrable objects
 *
 */
abstract class SsoAdmin {

	/**
	 * @var Base singleton of object */
	public $object = NULL;
	/**
	 * @var string Title of the admin object page */
	public $title = NULL;
	/**
	 * @var string[] list of field name for filter */
	public $searchFields = NULL;
	/**
	 * @var string[] list of field name that can be modified */
	public $modifiableFields = NULL;
	/**
	 * @var string[] list of field name that can be set in the new row */
	public $newFields = NULL;
	/**
	 * @var string[] list of extra field name to display */
	public $extraFields = NULL;
	/**
	 * @var string[] list of field name to NOT display */
	public $hideFields = array();
	/**
	 * @var string[] list of field name to display in tooltip*/
	public $tooltipFields = array();

	/**
	 * @var string[] list of errors */
	public $errors = array();
	/**
	 * @var string[] list of messages */
	public $messages = array();

	/**
	 * The text to display for object
	 * @param Base $obj object
	 * @return string the object name or id
	 */
	abstract public function displayName(Base $obj);
	/**
	 * Create a new object from form data
	 * @param mixed[] $data key => value
	 * @return Base new object
	 */
	abstract public function createFrom(array $data);
	/**
	 * Update an object
	 * @param Base $obj the object to update
	 * @param mixed[] $data key => value
	 * @return Base modified object or NULL if data not valid
	 */
	abstract public function updateFrom(Base $obj, array $data);

	/**
	 * Retrieve delete queries on other objects to execute when delete object
	 * @param Base $obj the object to delete
	 * @return DeleteQuery[] delete queries
	 */
	public function relatedObjectsDeleteQueries(Base $obj) {
		return array();
	}

	/**
	 * Retrieve delete queries to execute after an update
	 * @param SsoGroupElement $template object to delete with some field setted
	 * @param array $existingIds existing elements id
	 * @param array $deleteIds deleted elements id
	 * @return DeleteQuery[] delete queries
	 */
	public function relatedObjectsDeleteQueriesAfterUpdate(SsoGroupElement $template, array $existingIds, array $deleteIds) {
		return array();
	}
	/**
	 * Retrieve insert queries to execute after an update
	 * @param SsoGroupElement $template object to insert with some field setted
	 * @param array $existingIds existing elements id
	 * @param array $newIds new elements id
	 * @return InsertQuery[] insert queries
	 */
	public function relatedObjectsInsertQueriesAfterUpdate(SsoGroupElement $template, array $existingIds, array $newIds) {
		return array();
	}
	/**
	 * Build a object that will be passed to all FORM method
	 *
	 * Can be used for pre-load data for all rows
	 *
	 * @param DBResult $data all objects
	 * @return NULL|mixed[] array key => value
	 */
	public function buildViewContext(DBResult $data) {
		return NULL;
	}
	/**
	 * Add an error message
	 * @param string $error error message
	 */
	public function addError($error) {
		$this->errors[]=$error;
	}
	/**
	 * Add a report message
	 * @param string $message report message
	 */
	public function addMessage($message) {
		$this->messages[] = $message;
	}
	/**
	 * Check has an error
	 * @return boolean TRUE if addError() was called
	 */
	public function hasErrors() {
		return count($this->errors) > 0;
	}
}
