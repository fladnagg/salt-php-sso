<?php namespace sso;
use salt\ViewControl;
use salt\InsertQuery;
use salt\DeleteQuery;
use salt\UpdateQuery;
use salt\FormHelper;
use salt\Pagination;

$errors = array();
$oks = array();

if ($Input->P->ISSET->action) {
	$action = $id = $desc = NULL;
	foreach($Input->P->RAW->action as $k => $data) {
		$action = \salt\first(array_diff(array_keys($data), array('description')));
		if (isset($action)) {
			$id = $k;
			if (isset($Input->P->RAW->action[$id]['description'])) {
				$desc = $Input->P->RAW->action[$id]['description'];
			}
			break;
		}
	}

	if ($action === 'add') {
		$result = SsoCredential::search(array('appli_id' => $id));
		if (count($result->data) > 0) {
			$errors[] = 'Il existe déjà un accès ou une demande d\'accès pour cette application';
		} else {
			$cred = new SsoCredential();
			$cred->appli = $id;
			$cred->user = $sso->getLogin();
			$cred->status = SsoCredential::STATUS_ASKED;
			$cred->description = substr($desc, 0, 1024);
			$q = new InsertQuery($cred);
			$DB->execInsert($q);
			$oks[] = 'Demande ajoutée';
		}
	} else if ($action !== NULL) {
		$cred = SsoCredential::meta()->getById($DB, $id);
		if ($cred === NULL) {
			$errors[] = 'Impossible de retrouver la demande à modifier';
		} else if ($cred->user !== $sso->getLogin()) {
			$errors[] = 'Seules les demandes de l\'utilisateur connecté peuvent être modifiées';
		} else {
			switch($action) {
				case 'cancel' :
					$dq = new DeleteQuery($cred);
					$DB->execDelete($dq);
					$oks[] = 'La demande a bien été supprimée';
				break;
				case 'renew' :
					$cred->status = SsoCredential::STATUS_ASKED;
					$cred->description = substr($desc, 0, 1024);
					$uq = new UpdateQuery($cred);
					$DB->execUpdate($uq, 1);
					$oks[] = 'La demande a bien été relancée';
				break;
			}
		} // no errors
	} // action is not 'add'
} // isset action

$demandes = SsoCredential::getDemandes($sso->getLogin());

$existing = array();
foreach($demandes->data as $dem) { // application is in approval for this user 
	$existing[$dem->appli] = 1;
}

$allowed = array();
foreach($applis->data as $row) { // application is already accessible
	$allowed[$row->id] = 1;
}

$offset = max(0, $Input->G->RAW->offset-count($demandes->data));
$limit=10;

$nbDemandes = count($demandes->data);
$demandes->data = array_slice($demandes->data, max(0, $Input->G->RAW->offset), $limit);

$pagination = new Pagination($offset, max($limit-count($demandes->data), $limit));
$newApplis = SsoAppli::search(array('except' => array_keys($existing+$allowed)), $pagination);

$pagination->setCount($pagination->getCount()+$nbDemandes);
$pagination->setOffset($Input->G->RAW->offset);

foreach($newApplis->data as $row) {
	if (count($demandes->data) < $pagination->getLimit()) {
		$cred = new SsoCredential();
		$cred->appli = $row->id;
		$existing[$row->id] = 1;
		$demandes->data[] = $cred;
	}
}

$appliNames = array();
if (count($existing) > 0) {
	foreach(SsoAppli::search(array('id' => array_keys($existing)))->data as $row) {
		$appliNames[$row->id] = $row->name;
	}
}

if (count($demandes->data) === 0) {
	$oks[] = "Vous avez accès à toutes les applications disponibles";
}

ViewControl::edit();
?>
<h4>Demandez l'accès à de nouvelles applications</h4>

<?php if (count($errors) > 0) {?>
<div class="errors">
<?php 	foreach($errors as $err) {?>
	<?= $Input->HTML($err)?><br/>
<?php 	}?>
</div>
<?php }?>
<?php if (count($oks) > 0) {?>
<div class="ok">
<?php 	foreach($oks as $ok) {?>
	<?= $Input->HTML($ok)?><br/>
<?php 	}?>
</div>
<?php }?>

<?php if (count($demandes->data) > 0) {?>

<?= FormHelper::get(NULL, array('page', 'id')) ?>
<?php include(SSO_RELATIVE.'pages/layout/pagination.php') ?>
<?= FormHelper::end(); ?>

<?= FormHelper::post(NULL, array('page', 'id')) ?>

<table class="appli results">
	<tr>
<?php foreach($demandes->columns as $col) {?>
		<th><?= SsoCredential::COLUMN($col) ?></th>
<?php }?>
	</tr>
<?php foreach($demandes->data as $row) {?>
<?php 	if ($row->isNew()) {?>
<?php 		FormHelper::withNameContainer('action', $row->appli) ?>
<?php 	} else {?>
<?php 		FormHelper::withNameContainer('action', $row->id) ?>
<?php 	}?>
	<tr>
<?php 	foreach($demandes->columns as $col) {?>
			<td>
<?php 		if ($col === 'appli') {?>
				<?= $Input->HTML($appliNames[$row->$col]) ?>
<?php  		} else if (($col === 'description') && (($row->status === SsoCredential::STATUS_REFUSED) || $row->isNew())) {?>
				<?= $row->FORM->$col ?>
<?php  		} else if (($col !== 'status') || !$row->isNew()) {?>
				<?= $row->VIEW->$col ?>
<?php 		}?>
<?php
			if ($col === 'status') {
				if ($row->isNew()) {
					echo FormHelper::input('add', 'submit', 'Nouvelle demande');
				} else {
					if ($row->$col === SsoCredential::STATUS_REFUSED) {
						echo '&nbsp;';
						echo FormHelper::input('renew', 'submit', 'Redemander');
					}
					if ($row->$col !== SsoCredential::STATUS_VALIDATED) {
						echo '&nbsp;';
						echo FormHelper::input('cancel', 'submit', 'Annuler');
					}
				}
	 		} // status
?>
			</td>
<?php 	} // each columns ?>
	</tr>
<?php } // each row ?>

</table>
<div><br/>Vous pouvez indiquer pourquoi vous souhaitez un accès dans le champ "Description"</div>

<?= FormHelper::end() ?>
<?php } // has missing apps
