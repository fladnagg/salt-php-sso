<?php namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\FieldType;
use salt\In;
use salt\Pagination;
use salt\Query;
use salt\SqlExpr;

/**
 * @property int id
 * @property string path
 * @property string name
 * @property string handler
 * @property string icon
 */
class SsoAppli extends Base implements SsoAdministrable, SsoGroupable {

	private $lastError = NULL;
	
	protected function metadata() {
		parent::registerId('id');
		parent::registerTableName('sso_appli');
		parent::registerHelper(__NAMESPACE__.'\SsoAppliViewHelper');

		return array(
				Field::newNumber(	'id', 		'ID')->sqlType('INT PRIMARY KEY AUTO_INCREMENT'),
				Field::newText(		'path', 	'Chemin')->sqlType('VARCHAR(64) UNIQUE'),
				Field::newText(		'name', 	'Nom')->sqlType('VARCHAR(64)'),
				Field::newText(		'handler', 	'Gestionnaire')->sqlType('VARCHAR(128)')
															->displayOptions(array('size'=>32)),
				Field::newText(		'icon', 	'Image (64x64)')->sqlType('VARCHAR(128)')
															->displayOptions(array('size'=>32)),
		);
	}

	public static function getByPath(array $paths) {
		$DB = DBHelper::getInstance('SSO');
		$q = new Query(SsoAppli::meta(), TRUE);
		$q->whereAnd('path', 'IN', $paths);
		$q->orderAsc('name');
		$q->disableIfEmpty($paths);
		return $DB->execQuery($q);
	}

	public static function getGroupType() {
		return SsoGroupElement::TYPE_APPLI;
	}
	
	public function getNameField() {
		return 'name';
	}

	public static function search(array $criteres, Pagination $pagination = NULL) {
		$DB = DBHelper::getInstance('SSO');
		$appli = SsoAppli::meta();
		$q = new Query($appli, TRUE);

		if (isset($criteres[self::WITH_GROUP])) {
			$qGroup = new Query(SsoGroupElement::meta());
			$qGroup->whereAnd('type', '=', self::meta()->getGroupType());
			$qGroup->whereAnd('group_id', '=', $criteres[self::WITH_GROUP]);
			$q->join($qGroup, 'id', '=', $qGroup->getField('ref_id'), 'LEFT OUTER');
			$q->select(SqlExpr::func('!ISNULL', $qGroup->getField('group_id'))->asBoolean(), self::WITH_GROUP);
			unset($criteres[self::WITH_GROUP]);
			
			if (isset($criteres[self::EXISTS_NAME])) {
				if ($criteres[self::EXISTS_NAME] !== '') {
					$value = SqlExpr::value(NULL);
					if ($criteres[self::EXISTS_NAME] == 1) {
						$value->not();
					}
					$q->whereAnd($qGroup->getField('group_id'), 'IS', $value);
				}
				unset($criteres[self::EXISTS_NAME]);
			}
		}
		
		foreach($criteres as $k => $v) {
			if ($v !== '') {
				if ($k === 'group') {
					$qGroup = new Query(SsoGroupElement::meta());
					$qGroup->whereAnd('type', '=', SsoGroupElement::TYPE_APPLI);
					$qGroup->whereAnd('group_id', '=', $v);
					$q->join($qGroup, 'id', '=', $qGroup->getField('ref_id'));
				} else if ($k === 'except') {
					if (count($v) > 0) {
						$q->whereAnd('id', 'NOT IN', $v);
					}
				} else {
					$field = $appli->getField($k);
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
				}
			}
		}
		$q->orderAsc('name');

		return $DB->execQuery($q , $pagination);
	}

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
	
	public function lastError() {
		return $this->lastError;
	}
	
	public function validate() {
		
		$Input = In::getInstance();
		
		$serverHost = 'http://'.$Input->S->RAW->HTTP_HOST;
		if (strlen(trim($this->path))===0) {
			$this->lastError = 'Le chemin de l\'application est obligatoire.';
			return FALSE;
		}
			
		$url = $serverHost.$this->path;
		if (!$this->urlExists($url)) {
			$this->lastError = 'L\'application n\'existe pas : '.$url;
			return FALSE;
		}

		if (strlen(trim($this->icon)) > 0) {
			$image = $url.$this->icon;
			if (!$this->urlExists($image)) {
				$this->lastError = 'L\'image indiquée est introuvable : '.$image;
				return FALSE;
			}
		}
		
		return TRUE;
	}
}
