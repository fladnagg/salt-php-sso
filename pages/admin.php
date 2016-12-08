<?php namespace sso;

use salt\DeleteQuery;
use salt\Field;
use salt\FieldType;
use salt\FormHelper;
use salt\InsertQuery;
use salt\Pagination;
use salt\Query;
use salt\Salt;
use salt\UpdateQuery;
use salt\ViewControl;

if (!$sso->isSsoAdmin()) {
	header($Input->S->RAW->SERVER_PROTOCOL.' 403 Forbidden', TRUE, 403);
	echo "<div class=\"errors\">Vous n'avez pas le droit d'accéder à cette page</div>";
	die();
}

$SUBPAGES=array(
		'users'  => new SsoUserAdmin(),
		'applis' => new SsoAppliAdmin(),
		'auths' => new SsoAuthMethodAdmin(),
		'credentials' => new SsoCredentialAdmin(),
		'groups' => new SsoGroupAdmin(),
);

$subpage=$Input->G->RAW->subpage;
if ($subpage === NULL) {
	$subpage = \salt\first(array_keys($SUBPAGES));
}
$editId = (($subpage === 'groups') && $Input->G->ISSET->edit && is_numeric($Input->G->RAW->edit))?$Input->G->RAW->edit:'';

$type = NULL;
if ($editId !== '') {
	$type = $Input->G->RAW->type;
}
if ($type === NULL) {
	$type = \salt\first(array_keys($SUBPAGES));
}


$groupable = ($SUBPAGES[$subpage]->object instanceof SsoGroupable);
$pagination = new Pagination($Input->G->RAW->offset);

$searchFields = array();
$modifiableFields = array();

$search = $Input->G->RAW->search;
if ($search === NULL) {
	$search=array();
}

$msgErrors = array();
$msgOks = array();

$queries = array();

if ($Input->P->ISSET->add) {
	$datas = $Input->P->RAW->new;

	$groups = array();
	
	$obj = $SUBPAGES[$subpage]->createFrom($Input->P->RAW->new);
		
	$msgOks=array_merge($msgOks, $SUBPAGES[$subpage]->messages);
	$msgErrors=array_merge($msgErrors, $SUBPAGES[$subpage]->errors);
		
	if ((count($msgErrors) === 0) && ($obj !== NULL)) {
		$DB->beginTransaction();
		try {
			$q = new InsertQuery($obj);

			$id = $DB->execInsert($q);

			if ($obj->getId() === NULL) {
				$obj->id = $id;
			}

			if ($SUBPAGES[$subpage]->isFemaleName()) {
				$msgOks[] = $SUBPAGES[$subpage]->displayName($obj).' a été ajoutée';
			} else {
				$msgOks[] = $SUBPAGES[$subpage]->displayName($obj).' a été ajouté';
			}

			if ($obj instanceof SsoGroupable) {
				$defaultGroups = SsoGroup::getDefaultsGroupElements($obj);
				if (count($defaultGroups) > 0) {
					$q = new InsertQuery($defaultGroups);
					$DB->execInsert($q);
					
					$msgOks[]='Le nouvel élément a été ajouté aux groupes par défaut';
				}
			}

			$DB->commit();
			
			$Input->P->SET->new = NULL; // if all is ok, clear input fields
			
		} catch (\Exception $ex) {
			$DB->rollback();
			$msgOks = array();
			$msgErrors[]='Impossible d\'ajouter le nouvel objet : '.$ex->getMessage();
		}
	} // no errors

} else if ($Input->P->ISSET->delete) {
	$id = \salt\first(array_keys($Input->P->RAW->delete));
	
	$obj = $SUBPAGES[$subpage]->object->getById($DB, $id);
	
	if ($obj === NULL) {
		$msgErrors[]='Impossible de retrouver l\'objet à supprimer';
	} else {
		$otherDeletes = $SUBPAGES[$subpage]->relatedObjectsDeleteQueries($obj);
		
		$msgOks=array_merge($msgOks, $SUBPAGES[$subpage]->messages);
		$msgErrors=array_merge($msgErrors, $SUBPAGES[$subpage]->errors);
	}
	if (count($msgErrors) === 0) {
		$DB->beginTransaction();
		try {
			if ($SUBPAGES[$subpage]->isFemaleName()) {
				$msgOks[] = $SUBPAGES[$subpage]->displayName($obj).' a été supprimée';
			} else {
				$msgOks[] = $SUBPAGES[$subpage]->displayName($obj).' a été supprimé';
			}
	
			$obj->delete();
			$DB->execDelete(new DeleteQuery($obj));
				
			foreach($otherDeletes as $delete) {
				$DB->execDelete($delete);
			}
				
			$DB->commit();
				
			$sso->refreshUser();
			
		} catch (\Exception $ex) {
			$DB->rollback();
			$msgOks = array();
			$msgErrors[]='Impossible de supprimer l\'objet : '.$ex->getMessage();
		}
	} // no errors

} else if (($Input->P->ISSET->save) && ($Input->P->ISSET->data)) {
	$DB->beginTransaction();

	try {
		$datas = $Input->P->RAW->data;
		$ids = array_keys($datas);

		if ($Input->G->ISSET->edit) {
			$objects = $datas;
		} else {
			$objects = $SUBPAGES[$subpage]->object->getByIds($DB, $ids);
		}

		$groups=array();
		
		
		$modifiedObjects = array();
		
		$changedGroups = array();
		
		foreach($datas as $id => $values) {
			if (!isset($objects[$id])) {
				continue; // removed by another user
			}
			$obj = $objects[$id];

			if (isset($values[SsoGroupable::GROUPS]) && $groupable) {
				$changedGroups[$id] = array_map('intval', $values[SsoGroupable::GROUPS]);
			}

			if ($editId !== '') {
				if (count($groups) === 0) {
					$groups = array(
							'ADD' => array(),
							'DELETE' => array());
				}
			
				if (isset($values[SsoGroupable::WITH_GROUP])) {
					// add to group
					$groups['ADD'][] = $id;
				} else {
					// remove of group
					$groups['DELETE'][] = $id;
				}

			} else {
				$modifiedObject = $SUBPAGES[$subpage]->updateFrom($obj, $values);
				
				$msgOks=array_merge($msgOks, $SUBPAGES[$subpage]->messages);
				$msgErrors=array_merge($msgErrors, $SUBPAGES[$subpage]->errors);
				
				$SUBPAGES[$subpage]->messages = array();
				$SUBPAGES[$subpage]->errors = array();
				
				if (($modifiedObject !== NULL) && $modifiedObject->isModified() && (count($msgErrors) === 0)) {
					$q = new UpdateQuery($modifiedObject);
					$DB->execUpdate($q, 1);
					if ($SUBPAGES[$subpage]->isFemaleName()) {
						$msgOks[]=$SUBPAGES[$subpage]->displayName($modifiedObject).' a été modifiée';
					} else {
						$msgOks[]=$SUBPAGES[$subpage]->displayName($modifiedObject).' a été modifié';
					}
				}
			}
		} // each POST data
		
		if (count($changedGroups) > 0) {
			$realType = $SUBPAGES[$subpage]->object->getGroupType();

			$q = new Query(SsoGroupElement::meta(), TRUE);
			$q->whereAnd('ref_id', 'IN', array_keys($changedGroups));
			$q->whereAnd('type', '=', $realType);
			$existings = array();
			foreach($DB->execQuery($q)->data as $row) {
				if (!isset($existings[$row->ref_id])) {
					$existings[$row->ref_id] = array();
				}
				$existings[$row->ref_id][] = $row->group_id;
			}
			
			$insertGroups = array();
			foreach($changedGroups as $id => $newGroups) {
				$newGroups = array_diff($newGroups, array(-1));

				if (!isset($existings[$id])) {
					$existings[$id] = array();
				}
				
				$toCreate = array_diff($newGroups, $existings[$id]);
				$toDelete = array_diff($existings[$id], $newGroups);
				
				if (count($toCreate) > 0) {
					foreach($toCreate as $group) {
						$groupElement = new SsoGroupElement();
						$groupElement->type = $realType;
						$groupElement->ref_id = $id;
						$groupElement->group_id = $group;
						$insertGroups[] = $groupElement;
					}

					if ($SUBPAGES[$subpage]->isFemaleName()) {
						if (count($toCreate) === 1) {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été ajoutée à '.count($toCreate).' groupe';
						} else {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été ajoutée à '.count($toCreate).' groupes';
						}
					} else {
						if (count($toCreate) === 1) {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été ajouté à '.count($toCreate).' groupe';
						} else {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été ajouté à '.count($toCreate).' groupes';
						}
					}
				}
				
				if (count($toDelete) > 0) {
					$q = new DeleteQuery(SsoGroupElement::meta());
					$q->allowMultipleChange();
					$q->whereAnd('type', '=', $realType);
					$q->whereAnd('ref_id', '=', $id);
					$q->whereAnd('group_id', 'IN', $toDelete);
					$DB->execDelete($q);
					
					if ($SUBPAGES[$subpage]->isFemaleName()) {
						if (count($toDelete) === 1) {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été retirée de '.count($toDelete).' groupe';
						} else {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été retirée de '.count($toDelete).' groupes';
						}
					} else {
						if (count($toDelete) === 1) {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été retiré de '.count($toDelete).' groupe';
						} else {
							$msgOks[].=$SUBPAGES[$subpage]->displayName($objects[$id]).' a été retiré de '.count($toDelete).' groupes';
						}
					}
				}
			}
			
			if (count($insertGroups) > 0) {
				$DB->execInsert(new InsertQuery($insertGroups));
			}
		}
		
		if (count($groups) > 0) {

			$realType = $SUBPAGES[$type]->object->getGroupType();
			
			$q = new Query(SsoGroupElement::meta(), TRUE);
			$q->whereAnd('group_id', '=', $editId);
			$q->whereAnd('type', '=', $realType);
			$r = $DB->execQuery($q);
			$existings = array();
			foreach($r->data as $row) {
				$existings[] = $row->ref_id;
			}

			$template = new SsoGroupElement();
			$template->type = $realType;
			$template->group_id = $editId;
			
			$deleteQueries = $SUBPAGES[$subpage]->relatedObjectsDeleteQueriesAfterUpdate($template, $existings, $groups['DELETE']);
			$insertQueries = $SUBPAGES[$subpage]->relatedObjectsInsertQueriesAfterUpdate($template, $existings, $groups['ADD']);
			
			$msgOks=array_merge($msgOks, $SUBPAGES[$subpage]->messages);
			$msgErrors=array_merge($msgErrors, $SUBPAGES[$subpage]->errors);
			
			foreach($deleteQueries as $deleteQuery) {
				$nb = $DB->execDelete($deleteQuery);
				if ($nb === 1) {
					$msgOks[$editId.'del']=$nb.' élément a été enlevé du groupe';
				} else {
					$msgOks[$editId.'del']=$nb.' éléments ont été enlevés du groupe';
				}
			}
			
			foreach($insertQueries as $insertQuery) {
				$nb = $insertQuery->getInsertObjectCount();
				$DB->execInsert($insertQuery);
				if ($nb === 1) {
					$msgOks[$editId.'add']=$nb.' élément a été ajouté au groupe';
				} else {
					$msgOks[$editId.'add']=$nb.' éléments ont été ajoutés au groupe';
				}
			}

		} // $groups > 0
		
		if (count($msgErrors) == 0) {
			$DB->commit();
// 			$DB->rollback();

			$sso->refreshUser();
		} else {
			$msgOks=array();
			$DB->rollback();
		}
	} catch (\Exception $ex) {
		$DB->rollback();
		$msgOks = array();
		$msgErrors[]='Impossible de sauvegarder : '.$ex->getMessage();
	}
} // ISSET POST submit

?>
<h2>Administration SSO</h2>

<div class="tabs">
<ul>
<?php foreach($SUBPAGES as $k => $v) {?>
	<?php $url = \salt\first(explode('?', $Input->S->RAW->REQUEST_URI,2)).'?page=admin&amp;subpage='.$Input->URL($k); ?>
	<li class="<?= ($subpage == $k)?'selected':''?>">
		<a href="<?= $url ?>"><?= $Input->HTML($v->title) ?></a>
	</li>
<?php } ?>
</ul>
</div>

<?php 

// protection agains not allowed columns search
foreach($search as $k => $v) {
	if (!in_array($k, $SUBPAGES[$subpage]->searchFields)) { // allow only defined search fields
		if (($groupable && ($k === 'group'))		// but allow group on Groupable pages
		|| ($editId !== '')) { 						// ... and allow edit ids
			// do nothing : allowed
		} else {
			unset($search[$k]);
		}
	}
}

$search[SsoAdministrable::WITH_DETAILS]='';

$data = $SUBPAGES[$subpage]->object->search(($editId !== '')?array('edit' => $editId):$search, $pagination);

if (count($data->data) === 0) {
	$editId = '';
}

if ($editId !== '') {
	$currentEdit = $data->data[0];
	
	$visibleTypes = array();
	foreach($SUBPAGES as $groupType => $view) {
		if (!$view->object instanceof SsoGroupable) continue;
		if (($currentEdit->types & pow(2, $view->object->getGroupType()-1)) === 0) continue;
		$visibleTypes[$groupType] = $view;
	}
	if (!isset($visibleTypes[$type])) {
		$type = \salt\first(array_keys($visibleTypes));
	}

	$subpage = $type;
	if (!isset($SUBPAGES[$subpage])) {
		$subpage = \salt\first(array_keys($SUBPAGES));
	}
	unset($search[SsoAdministrable::WITH_DETAILS]);
	$search[SsoGroupable::WITH_GROUP]=$editId;
	$data = $SUBPAGES[$subpage]->object->search($search, $pagination);
	
	$searchFields = array('id', 'name');
	$modifiableFields = array(SsoGroupable::WITH_GROUP);
	$newFields = NULL;
	$newObject = NULL;
	$displayFields = array('id', 'name', SsoGroupable::WITH_GROUP);
	$SUBPAGES[$subpage]->hideFields = array(); // disable tooltip on table row

} else {
	$searchFields = $SUBPAGES[$subpage]->searchFields;
	$modifiableFields = $SUBPAGES[$subpage]->modifiableFields;
	$newFields = $SUBPAGES[$subpage]->newFields;
	$newObject = $SUBPAGES[$subpage]->object->getNew($SUBPAGES[$subpage]->extraFields);
	$displayFields = $data->columnsExcept($SUBPAGES[$subpage]->hideFields);
}

$formContext = $SUBPAGES[$subpage]->buildViewContext($data);

if ($modifiableFields === NULL) {
	$modifiableFields = array();
} else if (count($modifiableFields) === 0) {
	$modifiableFields = $data->columns;
}
if ($searchFields === NULL) {
	$searchFields = array();
} else if (count($searchFields) === 0) {
	$searchFields = $data->columns;
}
if ($newFields === NULL) {
	$newFields = array();
} else if (count($newFields) === 0) {
	$newFields = $data->columns;
}

ViewControl::edit();
?>

<?php if ($editId === '') { ?>
<h3>Liste des <?= $Input->HTML($SUBPAGES[$subpage]->title) ?></h3>
<?php } ?>

<?php if (($subpage === 'groups') || ($editId !== '')) { ?>
<div class="tabs">
<ul>
<?php $counts = SsoGroupElement::counts() ?>
<?php foreach($SUBPAGES['groups']->object->search(array())->data as $row) {?>
	<?php $url = \salt\first(explode('?', $Input->S->RAW->REQUEST_URI,2)).'?page=admin&amp;subpage=groups&amp;edit='.$Input->URL($row->id); ?>
	<?php if ($Input->G->ISSET->type) $url.='&amp;type='.$Input->URL($type); ?>
	<li class="<?= ($editId == $row->id)?'selected':''?>">
		<a href="<?= $url ?>"><?= $Input->HTML($row->name) ?> (<?= isset($counts[$row->id])?$counts[$row->id]:0 ?>)</a>
	</li>
<?php } ?>
</ul>
</div>	
<?php } ?>

<?php if ($editId !== '') { ?>
<h3>Modification des éléments du groupe "<?= $Input->HTML($currentEdit->name) ?>"</h3>
<?php }?>

<?php if ($editId !== '') { ?>
<?php 	if (count($visibleTypes) === 0) {?>
<div class="errors">Aucun type n'est activé pour ce groupe</div>
<?php 	} else {?>
<div class="tabs"> 
	<ul>
<?php 		foreach($visibleTypes as $groupType => $view) {?>
<?php 			$url = \salt\first(explode('?', $Input->S->RAW->REQUEST_URI,2)).'?page=admin&amp;subpage=groups&amp;edit='.$Input->URL($editId).'&amp;type='.$groupType; ?>
<?php 			$realType = $view->object->getGroupType(); ?>
		<li class="<?= ($type == $groupType)?'selected':''?>">
			<a href="<?= $url ?>"><?= $Input->HTML($SUBPAGES[$groupType]->title) ?> (<?= SsoGroupElement::countByType($editId, $realType) ?>)</a>
		</li>

<?php 		} ?>
	</ul>
</div>
<?php 	} ?>
<?php }?>

<?php if (($editId === '') || (count($visibleTypes) > 0)) { ?>
<?= FormHelper::get(NULL, array('page', 'subpage', 'edit', 'type')) ?>
<fieldset class="search admin">
	<legend>Critères de recherche</legend>

	<table>
		<tr>
<?php FormHelper::withNameContainer('search') ?>
	<?php foreach($searchFields as $f) {?>
			<td class="legend"><?= $SUBPAGES[$subpage]->object->COLUMN($f) ?> :</td>
			<td>
			<?= $SUBPAGES[$subpage]->object->FORM('search')->$f; ?>
			</td>
	<?php }?>
<?php 			if ($groupable) {?>
			<td class="legend">Groupe :</td>
			<td>
				<?= SsoGroup::meta()->FORM('list-'.$SUBPAGES[$subpage]->object->getGroupType())->name ?>
			</td>
<?php 			} else if ($editId !== '') {?>
			<td class="legend">Appartient au groupe :</td>
			<td>
<?php 				$field = Field::newBoolean(SsoGroupable::EXISTS_NAME, NULL, TRUE); ?>
				<?= FormHelper::field($field, SsoGroupable::EXISTS_NAME, NULL) ?>
			</td>
<?php 			}?>
<?php FormHelper::withoutNameContainer() ?>
			<td><?= FormHelper::input('search_button', 'submit', 'Filtrer') ?></td>
		</tr>
	</table>
</fieldset>
<?= FormHelper::end() ?>

<?php include(SSO_RELATIVE.'pages/layout/pagination.php'); ?>

<?php $params = array('*'); ?>
<?php if ($editId !== '') $params+=array('type' => $type); ?>
<?= FormHelper::post(NULL, $params) ?>
<table class="actions">
	<tr>
		<td><?= FormHelper::input('save', 'submit', 'Sauvegarder')?></td>
	</tr>
</table>
<?php if (count($msgErrors) > 0) {?>
<div class="errors">
	<?php foreach($msgErrors as $err) {?>
	<?= $Input->HTML($err) ?><br/>
	<?php } ?>
</div>
<?php }?>
<?php if (count($msgOks) > 0) {?>
<div class="ok">
	<?php foreach($msgOks as $ok) {?>
	<?= $Input->HTML($ok) ?><br/>
	<?php } ?>
</div>
<?php }?>

<table class="admin results">
	<tr>
<?php $extraCols = array(); ?>
<?php $emptyCols = array(); ?>
<?php foreach($displayFields as $i => $col) { ?>
<?php 	$viewCol = $SUBPAGES[$subpage]->object->COLUMN($col, 'columns'); ?>
<?php 	if (is_array($viewCol)) { ?>
<?php 		$extraCols[$i-count($emptyCols)] = \salt\first(array_values($viewCol)); ?>
		<th colspan="<?= count($extraCols[$i-count($emptyCols)])?>"><?= \salt\first(array_keys($viewCol)); ?></th>
<?php 	} else if ($viewCol !== NULL) {?>
		<th><?= $viewCol ?></th>
<?php 	} else { ?>
<?php 		$emptyCols[]=$col; ?>
<?php 	} ?>
<?php } ?>
<?php if ($editId === '') {?>

		<th>Actions</th>
<?php } ?>
	</tr>
<?php if (count($extraCols) > 0) {?>
	<tr>
<?php 	foreach(array_keys($displayFields) as $i) {?>
<?php 		if (count($displayFields) - count($emptyCols) === $i) {?>
<?php 			break;?>
<?php 		}?>
<?php 		if (!isset($extraCols[$i])) {?>
		<th>&nbsp;</th>
<?php 		} else { ?>
<?php 			foreach($extraCols[$i] as $col) {?>
		<th><?= $SUBPAGES[$subpage]->object->COLUMN($col, 'subcolumns');?></th>
<?php 			}?>
<?php 		}?>
<?php 	}?>
<?php 	if ($editId === '') {?>
		<th>&nbsp;</th>
<?php 	} ?>
	</tr>
<?php } ?>
<?php if ($newObject !== NULL) {?>
	<tr>
<?php 	FormHelper::withNameContainer('new'); ?>
<?php 	foreach($displayFields as $col) { ?>
		<td><?= in_array($col, $newFields)?$newObject->FORM($formContext)->$col:$newObject->VIEW->$col ?></td>
<?php 	} ?>

		<td>
			<button name="add">Ajouter <img src="<?= SSO_WEB_RELATIVE ?>images/add.png" alt="Ajouter" title="Ajouter"/></button>
		</td>
<?php 	FormHelper::withoutNameContainer(); ?>
	</tr>
<?php } ?>
<?php foreach($data->data as $row) {?>
<?php 	FormHelper::withNameContainer('data', $row->getId()); ?>
	<tr>
<?php 	$first = TRUE; ?>
<?php 	foreach($displayFields as $col) {?>
		<td>
			<?= in_array($col, $modifiableFields)?$row->FORM($formContext)->$col:$row->VIEW->$col ?>
<?php 		if (($first) && (count($SUBPAGES[$subpage]->tooltipFields) > 0)) {?>
<?php 
				$jsCode=<<<'JS'
$(function() {
	var tds=$('.setParentHover').closest('td').addClass('hover');

	$('.hover').hover(function(e) {
		var $div = $(this).find('div.hidden');
		$div.toggle(e.type=='mouseenter');
		$div[0].style.top=(this.offsetTop+$(this).closest('table')[0].offsetTop-$div[0].clientHeight/2+15)+'px';
		$div[0].style.left=(this.offsetLeft+this.clientWidth-2)+'px';
	});
});
JS;
				FormHelper::registerJavascript('hoverDescription', $jsCode);

				$tooltip = array();
				$details = array();
				foreach($SUBPAGES[$subpage]->tooltipFields as $tooltipField) {
					if (strlen($row->$tooltipField) > 0) {
						$tooltip[] = $row->COLUMN($tooltipField).' : '.$row->VIEW->{$tooltipField};
					}
					$detail = $row->COLUMN($tooltipField).': ';
					$detail.= $row->VIEW->$tooltipField;
					$details[] = $detail;
				}

				$changeDetails = array();
				foreach($SUBPAGES[$subpage]->hideFields as $tooltipField) {
					if (in_array($tooltipField, $modifiableFields)) {
						$changeDetails[]=$tooltipField;
					}
				}
				
				$img = ((count($tooltip) > 0)?'comments':'comment');
				$click = 'javascript:return false;';
				if (count($changeDetails) > 0) {
					$img = 'edit';
					$click = "handleOverlay($(this).siblings('.overlay'), 'show');";
				}
				?>
			<img src="<?= SSO_WEB_RELATIVE ?>images/<?= $img ?>.png" alt="other fields (<?= $row->VIEW->id ?>)" onclick="<?= $click ?>"/>
			<div class="hidden setParentHover" style="position:absolute;top:0px;left:0px; background-color: #ddddff; border:1px solid black; padding:5px; text-align:left;">
				<em>Autres champs pour l'objet <?= $row->VIEW->id ?> :</em><br/>
				<?= implode('<br/>', $details) ?>
			</div>
<?php 			if (count($changeDetails) > 0) { ?>
			<div class="overlay">
				<div>
					<b>Modifier les autres champs de l'objet <?= $row->VIEW->id ?></b>
					<table>
<?php 				foreach($changeDetails as $f) {?>
						<tr>
							<td class="field"><?= $row->COLUMN($f) ?></td>
							<td class="input"><?= $row->FORM($formContext)->$f ?></td>
						</tr>
<?php 				}?>
					</table>
					<input type="button" value="Valider" onclick="handleOverlay($(this).closest('.overlay'), 'save')"/>
					<input type="button" value="Annuler" onclick="handleOverlay($(this).closest('.overlay'), 'cancel');"/>
				</div>
			</div>
<?php 			} // has Modifiable fields ?>
<?php 		} // first column & has tooltip ?>
<?php		$first = FALSE;?>
		</td>
<?php 	} // each cols ?>
<?php 	if ($editId === '') {?>
		<td>
			<button name="delete[<?= $Input->HTML($row->getId()) ?>]">Supprimer <img src="<?= SSO_WEB_RELATIVE ?>images/delete.png" alt="Supprimer" title="Supprimer"/></button>
		</td>
<?php 	}?>
	</tr>
<?php FormHelper::withoutNameContainer(); ?>
<?php } // each rows ?>
</table>

<?= FormHelper::end() ?>
<?php } // data to display ?>