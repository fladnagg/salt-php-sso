<?php
/**
 * SsoGroupElement class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib\dao
 */
namespace sso;

use salt\Base;
use salt\DBHelper;
use salt\Field;
use salt\Query;
use salt\SqlExpr;

/**
 * SsoGroupElement
 *
 * @property string $ref_id
 * @property int $group_id
 * @property int $type
 */
class SsoGroupElement extends Base {

	/** User type */
	const TYPE_USER = 1;
	/** Application type */
	const TYPE_APPLI = 2;
	/** Authentication type */
	const TYPE_AUTH = 3;

	/**
	 * {@inheritDoc}
	 * @see \salt\Base::metadata()
	 */
	protected function metadata() {
		self::MODEL()
			->registerTableName('sso_group_element')
			->registerFields(
				Field::newText(		'ref_id', 	L::field_ref_id)->sqlType('VARCHAR(32)'),
				Field::newNumber(	'group_id', L::field_group_id),
				Field::newNumber(	'type',		L::field_type, FALSE, self::TYPE_USER, array(
					self::TYPE_USER => 		L::group_type_user,
					self::TYPE_APPLI => 	L::group_type_appli,
					self::TYPE_AUTH => 		L::group_type_auth,
				))
		);
	}

	/**
	 * Retrieve # elements in each groups
	 * @return int[] as GroupId => # elements
	 */
	public static function counts() {
		$db = DBHelper::getInstance('SSO');
		$q = SsoGroupElement::query();
		$q->selectField('group_id');
		$q->select(SqlExpr::_COUNT(SqlExpr::text('*')), 'nb');
		$q->groupBy('group_id');

		$result = array();
		foreach($db->execQuery($q)->data as $row) {
			$result[$row->group_id] = $row->nb;
		}
		return $result;
	}

	/**
	 * Retrieve # element in group for a type
	 * @param int $groupId Group ID
	 * @param int $type Type self::TYPE_*
	 * @return number Number of element of this type in group
	 */
	public static function countByType($groupId, $type = NULL) {
		$db = DBHelper::getInstance('SSO');
		$q = SsoGroupElement::query();
		$q->whereAnd('group_id', '=', $groupId);
		if ($type !== NULL) {
			$q->whereAnd('type', '=', $type);
		}
		return $db->execCountQuery($q);
	}

	/**
	 * Retrieve tooltip content for each type / group
	 * @return mixed[] array(type => array(group => array('count' => count, 'tooltip'=>array(name))))
	 */
	public static function getTooltipContents() {
		$db = DBHelper::getInstance('SSO');

		$types = array_keys(self::MODEL()->type->values);
		$tooltipContent = array_combine($types, array_fill(0, count($types), array()));

// 		select g.id, el.type, count(el.ref_id) as nb
// 		from sso_group g
// 		left outer join sso_group_element el on el.group_id = g.id
// 		group by g.id, el.type
// 		;
		$q = SsoGroup::query();
		$q->selectField('id');

		$qElem = SsoGroupElement::query();
		$qElem->selectField('type');
		$qElem->select(SqlExpr::_COUNT($qElem->ref_id)->asNumber(), 'nb');

		$q->join($qElem, 'id', '=', $qElem->group_id, 'LEFT OUTER');

		$q->groupBy('id');
		$q->groupBy($qElem->type);

		foreach($db->execQuery($q)->data as $row) {
			if (!isset($tooltipContent[\salt\first($types)][$row->id])) {
				foreach($types as $type) {
					$tooltipContent[$type][$row->id]=array('count' => 0, 'tooltip' => array());
				}
			}
			if ($row->type !== NULL) {
				$tooltipContent[$row->type][$row->id]=array('count' => $row->nb, 'tooltip' => array());
			}
		}

		// Nth first elements of each group
		// We have to :
		//	- isolate data subquery from @nb, @gr variables for avoid duplicate @nb (most inner query, data FROM)
		//	- check nb value after compute (most outer query)
		// 	- initialize @nb and @gr (init FROM)
// 		select name, group_id
// 		from
// 		(select @nb := null, @gr := null) init,
// 		(   select name, group_id
// 				, @nb := IF(data.group_id = @gr, @nb + 1, 1) as `nb`
// 				, @gr := data.group_id
// 			from (
// 				select u.name, el.group_id
// 				from sso_group_element el
// 				inner join sso_user u on el.ref_id = u.id
// 				where type=1
// 				order by el.group_id, u.name
// 				) data
// 		) t
// 		where nb <=6
// 		;

		foreach(array(
					SsoGroupElement::TYPE_APPLI => SsoAppli::query(),
					SsoGroupElement::TYPE_USER => SsoUser::query(),
					SsoGroupElement::TYPE_AUTH => SsoAuthMethod::query(),
				) as $type => $query) {

			$q = SsoGroupElement::query();
			$q->whereAnd('type', '=', $type);

			$qElem = $query;
			$q->join($qElem, 'ref_id', '=', $qElem->id);

			$q->selectField('group_id');
			$q->select($qElem->name, 'name');

			$q->orderAsc('group_id');
			$q->orderAsc($qElem->name);

			$limit = SqlExpr::value(SSO_MAX_TOOLTIP_ELEMENTS+1);
			$sql = <<<SQL
SELECT name, group_id
FROM
	(select @nb := null, @gr := null) init,
	(select name, group_id,
		@nb := IF(data.group_id = @gr, @nb + 1, 1) as `nb`,
		@gr := data.group_id
	from ({$q->toSQL()}) data
	) t
WHERE nb <= {$limit->toSQL()}
SQL;

			$statement = $db->execSQL($sql, array_merge($q->getBinds(), $limit->getBinds()));

			foreach($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$group = $row['group_id'];

				if (count($tooltipContent[$type][$group]['tooltip']) === SSO_MAX_TOOLTIP_ELEMENTS) {
					$row['name'] = '...';
				}
				$tooltipContent[$type][$group]['tooltip'][] = $row['name'];
			}
		}

		return $tooltipContent;
	}
}

