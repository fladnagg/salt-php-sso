<?php
/**
 * display application access request
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

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
		$result = SsoCredential::search(array('appli_id' => $id, 'user_id' => $sso->getLogin()));
		if (count($result->data) > 0) {
			$errors[] = L::error_ask_exists;
		} else {
			$cred = new SsoCredential();
			$cred->appli = $id;
			$cred->user = $sso->getLogin();
			$cred->status = SsoCredential::STATUS_ASKED;
			$cred->description = substr($desc, 0, 1024);
			$q = new InsertQuery($cred);
			$DB->execInsert($q);
			$oks[] = L::label_ask_added;
		}
	} else if ($action !== NULL) {
		$cred = SsoCredential::getById($DB, $id);
		if ($cred === NULL) {
			$errors[] = L::error_ask_not_exists;
		} else if ($cred->user !== $sso->getLogin()) {
			$errors[] = L::error_ask_other_user;
		} else {
			switch($action) {
				case 'cancel' :
					$dq = new DeleteQuery($cred);
					$DB->execDelete($dq);
					$oks[] = L::label_ask_deleted;
				break;
				case 'renew' :
					$cred->status = SsoCredential::STATUS_ASKED;
					$cred->description = substr($desc, 0, 1024);
					$uq = new UpdateQuery($cred);
					$DB->execUpdate($uq, 1);
					$oks[] = L::label_ask_revived;
				break;
			}
		} // no errors
	} // action is not 'add'
} // isset action

$requests = SsoCredential::getPendingRequests($sso->getLogin());

$existing = array();
foreach($requests->data as $req) { // application is in approval for this user
	$existing[$req->appli] = 1;
}

$allowed = array();
foreach($applis->data as $row) { // application is already accessible
	$allowed[$row->id] = 1;
}

$offset = max(0, $Input->G->RAW->offset-count($requests->data));
$limit=10;

$nbRequests = count($requests->data);
$requests->data = array_slice($requests->data, max(0, $Input->G->RAW->offset), $limit);

$pagination = new Pagination($offset, max($limit-count($requests->data), $limit));
$newApplis = SsoAppli::search(array('except' => array_keys($existing+$allowed)), $pagination);

$pagination->setCount($pagination->getCount()+$nbRequests);
$pagination->setOffset($Input->G->RAW->offset);

foreach($newApplis->data as $row) {
	if (count($requests->data) < $pagination->getLimit()) {
		$cred = new SsoCredential();
		$cred->appli = $row->id;
		$existing[$row->id] = 1;
		$requests->data[] = $cred;
	}
}

$allApplis = array();
$appliNames = array();
if (count($existing) > 0) {
	foreach(SsoAppli::search(array('id' => array_keys($existing)))->data as $row) {
		$appliNames[$row->id] = $row->name;
		$allApplis[$row->id] = $row;
	}
}

if (count($requests->data) === 0) {
	$oks[] = L::label_ask_all_access;
}

ViewControl::edit();
?>
<h4><?= $Input->HTML(L::label_ask_title) ?></h4>

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

<?php if (count($requests->data) > 0) {?>

<?= FormHelper::get(NULL, array('page', 'id')) ?>
<?php include(SSO_RELATIVE.'pages/layout/pagination.php') ?>
<?= FormHelper::end(); ?>

<?= FormHelper::post(NULL, array('page', 'id')) ?>

<table class="appli results">
	<tr>
<?php foreach($requests->columns as $col) {?>
		<th><?= SsoCredential::COLUMN()->$col ?></th>
<?php }?>
	</tr>
<?php foreach($requests->data as $row) {?>
<?php 	if ($row->isNew()) {?>
<?php 		FormHelper::withNameContainer('action', $row->appli) ?>
<?php 	} else {?>
<?php 		FormHelper::withNameContainer('action', $row->id) ?>
<?php 	}?>
	<tr>
<?php 	foreach($requests->columns as $col) {?>
			<td>
<?php 		if ($col === 'appli') {?>
				<div title="<?= $allApplis[$row->$col]->VIEW->description ?>" class="aide"><?= $allApplis[$row->$col]->VIEW->name ?></div>
<?php  		} else if (($col === 'description') && (($row->status === SsoCredential::STATUS_REFUSED) || $row->isNew())) {?>
				<?= $row->FORM->$col ?>
<?php  		} else if (($col !== 'status') || !$row->isNew()) {?>
				<?= $row->VIEW->$col ?>
<?php 		}?>
<?php
			if ($col === 'status') {
				if ($row->isNew()) {
					echo FormHelper::input('add', 'submit', L::button_new_request);
				} else {
					if ($row->$col === SsoCredential::STATUS_REFUSED) {
						echo '&nbsp;';
						echo FormHelper::input('renew', 'submit', L::button_ask_again);
					}
					if ($row->$col !== SsoCredential::STATUS_VALIDATED) {
						echo '&nbsp;';
						echo FormHelper::input('cancel', 'submit', L::button_cancel);
					}
				}
	 		} // status
?>
			</td>
<?php 	} // each columns ?>
	</tr>
<?php } // each row ?>

</table>
<div><br/><?= $Input->HTML(L::help_ask) ?></div>

<?= FormHelper::end() ?>
<?php } // has missing apps
