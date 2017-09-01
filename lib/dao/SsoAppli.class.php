<?php
/**
 * SsoAppli class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\FieldType;
use salt\In;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;

/**
 * SsoAppli
 *
 * @property int $id
 * @property string $path
 * @property string $name
 * @property string $description
 * @property string $handler
 * @property string $icon
 */
class SsoAppli extends Base implements SsoAdministrable, SsoGroupable {

	/** @var string Last error after validate() call */
	private $lastError = NULL;

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {

		parent::registerHelper(__NAMESPACE__.'\SsoAppliViewHelper');

		self::MODEL()
			->registerId('id')
			->registerTableName('sso_appli')
			->registerFields(
				Field::newNumber(	'id', 		L::field_id)->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(		'path', 	L::field_path)->sqlType('VARCHAR(64) UNIQUE'),
				Field::newText(		'name', 	L::field_name)->sqlType('VARCHAR(64)'),
				Field::newText(		'description', L::field_description, TRUE)->sqlType('TEXT')->displayOptions(array('type'=>'textarea', 'cols' => 100, 'rows' => 10)),
				Field::newText(		'handler', 	L::field_handler)->sqlType('VARCHAR(128)')
															->displayOptions(array('size'=>32)),
				Field::newText(		'icon', 	L::field_image)->sqlType('VARCHAR(128)')
															->displayOptions(array('size'=>32))
		);
	}

	/**
	 * Search application by path
	 * @param string[] $paths list of paths
	 * @return \salt\DBResult All application with theses paths
	 */
	public static function getByPath(array $paths) {
		$DB = DBHelper::getInstance('SSO');
		$q = SsoAppli::query(TRUE);
		$q->whereAnd('path', 'IN', $paths);
		$q->orderAsc('name');
		$q->disableIfEmpty($paths);
		return $DB->execQuery($q);
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\SsoGroupable::getGroupType()
	 */
	public static function getGroupType() {
		return SsoGroupElement::TYPE_APPLI;
	}

	/**
	 * {@inheritDoc}
	 * @see \sso\SsoGroupable::getNameField()
	 */
	public function getNameField() {
		return 'name';
	}

	/**
	 * {@inheritDoc}
	 * @param mixed[] $criteria criteria as key => value
	 * @param Pagination $pagination pagination object
	 * @see \sso\SsoAdministrable::search()
	 */
	public static function search(array $criteria, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');
		$q = SsoAppli::query(TRUE);

		if (isset($criteria[self::WITH_GROUP])) {
			$qGroup = SsoGroupElement::query();
			$qGroup->whereAnd('type', '=', self::getGroupType());
			$qGroup->whereAnd('group_id', '=', $criteria[self::WITH_GROUP]);
			$q->join($qGroup, 'id', '=', $qGroup->ref_id, 'LEFT OUTER');
			$q->select(SqlExpr::_ISNULL($qGroup->group_id)->before(SqlExpr::text('!'))->asBoolean(), self::WITH_GROUP);
			unset($criteria[self::WITH_GROUP]);

			if (isset($criteria[self::EXISTS_NAME])) {
				if ($criteria[self::EXISTS_NAME] !== '') {
					$value = SqlExpr::value(NULL);
					if ($criteria[self::EXISTS_NAME] == 1) {
						$value->not();
					}
					$q->whereAnd($qGroup->group_id, 'IS', $value);
				}
				unset($criteria[self::EXISTS_NAME]);
			}
		} else {

			foreach($q->getSelectFields() as $select) {
				$q->groupBy($select);
			}

			$gElem = SsoGroupElement::query(); // All groups linked to application
			$gElem->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
			$q->join($gElem, 'id', '=', $gElem->ref_id, 'LEFT OUTER');

			$qGroupElem = SsoGroup::query(); // Group names linked to user
			$q->join($qGroupElem, $gElem->group_id, '=', $qGroupElem->id, 'LEFT OUTER');
			$expr = $qGroupElem->name->distinct();
			$expr->template(SqlExpr::TEMPLATE_MAIN.' ORDER BY 1 SEPARATOR '.SqlExpr::TEMPLATE_PARAM, self::GROUP_CONCAT_SEPARATOR_CHAR);
			$q->select(SqlExpr::_GROUP_CONCAT($expr), SsoGroupable::GROUPS);

		}

		foreach($criteria as $k => $v) {
			if ($v !== '') {
				if ($k === 'group') {
					$qGroup = SsoGroupElement::query();
					$qGroup->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
					$qGroup->whereAnd('group_id', '=', $v);
					$q->join($qGroup, 'id', '=', $qGroup->ref_id);
				} else if ($k === 'except') {
					if (count($v) > 0) {
						$q->whereAnd('id', 'NOT IN', $v);
					}
				} else {
					$field = self::MODEL()->$k;
					if ($field->type === FieldType::TEXT) {
						$q->whereAnd($k, 'LIKE' , '%'.$v.'%');
					} else if ($field->type === FieldType::BOOLEAN) {
						$q->whereAnd($k, '=', ($v == 1));
					} else if ($field->type === FieldType::NUMBER) {
						if (is_array($v)) {
							$q->whereAnd($k, 'IN', $v);
						} else {
							$q->whereAnd($k, '=', $v);
						}
					}
				} // criteria is basic field of object
			} // value not empty
		} // each criteria
		$q->orderAsc('name');

		return $DB->execQuery($q , $pagination);
	}

	/**
	 * Check an URL exists
	 * @param string $url URL to check
	 * @throws Exception if curl failed
	 * @return boolean TRUE if URL exists, FALSE otherwise
	 */
	private function urlExists($url) {

		return TRUE; // FIXME does not work everytime... maybe need more SSO configuration...

		$headers = array();
		$ch = curl_init();
		try {
			curl_setopt($ch, \CURLOPT_URL, $url);
			curl_setopt($ch, \CURLOPT_HEADER, true);
			curl_setopt($ch, \CURLOPT_NOBODY, true);
			curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, \CURLOPT_TIMEOUT, 10);

			$host = parse_url($url, PHP_URL_HOST);
			if (in_array($host, array('localhost', '127.0.0.1'))) { // avoid proxy on localhost
				curl_setopt($ch, \CURLOPT_PROXY, '');
			}

			$headers = curl_exec($ch);

		} catch (Exception $ex) {
			curl_close($ch);
			throw $ex;
		}
		curl_close($ch);

		if ($headers !== FALSE) {
			$result = explode("\n", $headers);
			$result = explode(' ', $result[0]);
			$result = intval($result[1]);
			if ($result < 400) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Retrieve last validation error
	 * @return string error message
	 */
	public function lastError() {
		return $this->lastError;
	}

	/**
	 * Check values
	 * @return boolean TRUE if all values are valid, FALSE otherwise
	 */
	public function validate() {

		$Input = In::getInstance();

		$serverHost = 'http://'.$Input->S->RAW->HTTP_HOST;
		if (strlen(trim($this->path))===0) {
			$this->lastError = L::error_app_path_missing;
			return FALSE;
		}

		$url = $serverHost.$this->path;
		if (!$this->urlExists($url)) {
			$this->lastError = L::error_app_not_exists($url);
			return FALSE;
		}

		if (strlen(trim($this->icon)) > 0) {
			$image = $url.$this->icon;
			if (!$this->urlExists($image)) {
				$this->lastError = L::error_app_image_not_exists($image);
				return FALSE;
			}
		}

		return TRUE;
	}
}
