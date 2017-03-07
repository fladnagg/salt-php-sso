<?php namespace sso; 

use salt\DBHelper;
use salt\SqlExpr;

DBHelper::register('SSO', SSO_DB_HOST, SSO_DB_PORT, SSO_DB_DATABASE, SSO_DB_USER, SSO_DB_PASS, SSO_DB_CHARSET);

class SsoAdminApi {

	private static $instance = NULL;
	
	private function __construct() {
		
	}
	
	public static function getInstance() {
		global $sso;
		
		if (self::$instance === NULL) {
			self::$instance = new SsoAdminApi();
		}
		return self::$instance;
	}
	
	
	public function getAllUsers() {
		global $sso;

		$db = DBHelper::getInstance('SSO');
		
		if (!$sso->isSsoAdmin()) {
			return NULL;
		}
		
		$q = SsoUser::query();
		$q->selectField('id');
		$result = array();
		foreach($db->execQuery($q)->data as $row) {
			$result[] = $row->id;
		}
		return $result;
	}
	
	public function getDisplayNames($users) {
		$db = DBHelper::getInstance('SSO');
		
		$q = SsoUser::query();
		$q->selectFields(array('id', 'name'));
		$q->whereAnd('id', 'IN', $users);
		$result = array_combine($users, $users);
		foreach($db->execQuery($q)->data as $row) {
			$result[$row->id] = $row->name;
		}
		return $result;
	}
	
	/**
	 * Retrieve groups of users
	 * @param string[] $users users login
	 * @return string[][] groups of users : array(login => array(group names)) 
	 */
	public function getGroups($users) {
		$db = DBHelper::getInstance('SSO');
		
// 		select u.id, u.name, group_concat(g.name order by 1 separator '/') as groups
// 		from sso_user u
// 		left outer join sso_group_element ge on ge.ref_id = u.id and type = 1
// 		left outer join sso_group g on g.id = ge.group_id
// 		group by u.id, u.name
// 		;

		$q = SsoUser::query();
		$q->selectField('id');
		$q->whereAnd('id', 'IN', $users);
		
		$qGroupElement = SsoGroupElement::query();
		$qGroupElement->whereAnd('type', '=', SsoGroupElement::TYPE_USER);
		$q->join($qGroupElement, 'id', '=', $qGroupElement->ref_id, 'LEFT OUTER');
		
		$qGroup = SsoGroup::query();
		$qGroup->select(SqlExpr::_GROUP_CONCAT($qGroup->name->after(SqlExpr::text(" ORDER BY 1 SEPARATOR ','"))), 'groups');
		$q->join($qGroup, $qGroup->id, '=', $qGroupElement->group_id);
		
		$q->groupBy('id');
		
		$result = array_fill_keys($users, array());
		foreach($db->execQuery($q)->data as $row) {
			$result[$row->id] = explode(',', $row->groups);
		}
		return $result;
	}
}