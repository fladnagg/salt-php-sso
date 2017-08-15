<?php
/**
 * BusinessException class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

/**
 * Business Exception class
 */
class BusinessException extends \Exception {
	/** @var mixed[] extra data */
	private $data = array();

	/**
	 * Add a data
	 * @param mixed $data extra data to add
	 * @param string $key key to use for bind the data (optional)
	 */
	public function addData($data, $key = NULL) {
		if ($key === NULL) {
			$this->data[] = $data;
		} else {
			$this->data[$key] = $data;
		}
	}

	/**
	 * Retrieve all data or specific
	 * @param string $key a key for retrieve specific data, NULL for all data
	 * @return mixed|mixed[] return all data if $key is NULL, the data bind to $key otherwise
	 */
	public function getData($key = NULL) {
		if ($key === NULL) {
			return $this->data;
		}
		return $this->data[$key];
	}
}
