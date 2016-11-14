<?php namespace sso;

use salt\DBHelper;
use salt\Base;
use salt\DBResult;
use salt\DeleteQuery;
use salt\InsertQuery;
use salt\Query;
use salt\SqlExpr;

class SsoGroupAdmin extends SsoAdmin {

	private static $types = array(
		'users' => SsoGroupElement::TYPE_USER,
		'applis' => SsoGroupElement::TYPE_APPLI,
		'auths' => SsoGroupElement::TYPE_AUTH,
	);

	public function __construct() {
		$this->title = 'Groupes';
		$this->object = SsoGroup::meta();
		$this->searchFields = array('name', 'types');
		$this->modifiableFields = array('name');
		$this->newFields = array('name');
		$this->extraFields = array('users', 'applis', 'auths');
		$this->hideFields = array('defaults', 'types');

		$groupables = array('users', 'applis', 'auths');
		foreach(array_keys(self::$types) as $groupable) {
			$this->modifiableFields[]='type_'.$groupable;
			$this->modifiableFields[]='default_'.$groupable;
			$this->modifiableFields[]= $groupable;

			$this->extraFields[]='type_'.$groupable;
			$this->extraFields[]='default_'.$groupable;

			$this->newFields[]='type_'.$groupable;
			$this->newFields[]='default_'.$groupable;
		}
	}
	
	public function displayName(Base $obj) {
		return 'Le groupe '.$obj->name;
	}
	
	public function createFrom(array $datas) {
	
		$obj = $this->object->getNew();

		$defaults = 0;
		$types = 0;
		foreach(self::$types as $name => $value) {
			if (isset($datas['type_'.$name])) {
				$types |= pow(2, $value -1);
			}
			if (isset($datas['default_'.$name])) {
				$defaults |= pow(2, $value -1);
			}
		}

		$obj->name = $datas['name'];
		$obj->defaults = $defaults;
		$obj->types = $types;

		return $obj;
	}

	public function relatedObjectsDeleteQueries(Base $obj) {
		
		$DB = DBHelper::getInstance('SSO');
		
		$result = array();
	
		$q = new DeleteQuery(SsoGroupElement::meta());
		$q->allowMultipleChange();
		$q->whereAnd('group_id', '=', $obj->id);
		$result[] = $q;
		
		$q = new Query(SsoCredential::meta());
		$q->whereOr('appli_group', '=', $obj->id);
		$q->whereOr('user_group', '=', $obj->id);

		$nb = $DB->execCountQuery($q);
		if ($nb > 0) {
			$this->addError('Impossible de supprimer le groupe '.$obj->name.' car il est référencé par '.$nb.' autorisation(s)');
			return array();
		}
	
		return $result;
	}
	
	public function updateFrom(Base $obj, array $data) {

		$obj->name = $data['name'];

		$defaults = 0;
		$types = 0;
		foreach(self::$types as $name => $value) {
			if (isset($data['type_'.$name])) {
				$types |= pow(2, $value -1);
			}
			if (isset($data['default_'.$name])) {
				$defaults |= pow(2, $value -1);
			}
		}
		
		$removedTypes = $obj->types & ~$types; // old AND NOT new : all 1 bits in old but missing in new
		
		if ($removedTypes > 0) {
			$q = new Query(SsoGroupElement::meta());
			$q->whereAnd('group_id', '=', $obj->id);
			
			//(pow(2, t1.type-1) & 5)
			
			$expr = SqlExpr::func('POW', 2, $q->getField('type')->after(SqlExpr::text(' - 1')));
			
			$q->whereAnd($expr, '&', $removedTypes);
			
			$DB = DBHelper::getInstance('SSO');
			$nb = $DB->execCountQuery($q);
			if ($nb > 0) {
				$bin = strrev(decbin($removedTypes));
				$types = array();
				$values = SsoGroupElement::meta()->getField('type')->values;
				for($i = 0; $i < strlen($bin); $i++) {
					if ($bin[$i] === '1') {
						$types[] = $values[$i+1];
					}
				}
				if (count($types) > 1) {
					$this->addError('Impossible de désactiver les types ('.implode(', ', $types).') pour le groupe '.$obj->name.' car le groupe contient '.$nb.' élément(s) de ces types');
				} else {
					$this->addError('Impossible de désactiver le type '.implode('', $types).' pour le groupe '.$obj->name.' car le groupe contient '.$nb.' élément(s) de ce type');
				}
				return NULL;
			}
		}
		
		$obj->defaults = $defaults & $types; // default cannot be activated for disabled types
		$obj->types = $types;
		
		return $obj;
	}
	
	public function relatedObjectsDeleteQueriesAfterUpdate(Base $template, array $existingIds, array $deleteIds) {
	
		$deletedGroups = array_intersect($deleteIds, $existingIds);
		if (count($deletedGroups) > 0) {
			$q = new DeleteQuery(SsoGroupElement::meta());
			$q->allowMultipleChange();
			$q->whereAnd('group_id', '=', $template->group_id);
			$q->whereAnd('type', '=', $template->type);
			$q->whereAnd('ref_id', 'IN', $deletedGroups);
			
			return array($q);
		}
		return array();

	}
	public function relatedObjectsInsertQueriesAfterUpdate(Base $template, array $existingIds, array $newIds) {

		$addedGroups = array_diff($newIds, $existingIds);
		if (count($addedGroups) > 0) {
			foreach($addedGroups as $k => $elemId) {
				$elem  = new SsoGroupElement();
				$elem->group_id = $template->group_id;
				$elem->type = $template->type;
				$elem->ref_id = $elemId;
				$addedGroups[$k] = $elem;
			}
			return array(new InsertQuery($addedGroups));
		}
		return array();
	}
	

	public function buildViewContext(DBResult $data) {
		$groupsElements = SsoGroupElement::getTooltipContents();
		return array('tooltip' => $groupsElements);
	}
}
