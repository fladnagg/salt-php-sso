<?php namespace sso; ?>
<h2>Applications SSO pour l'utilisateur <?= $Input->HTML($sso->getLogin())?></h2>
<?php $error = NULL; ?>
<?php if ($Input->G->ISSET->from) {
	
		if ($Input->G->RAW->from === 'client') {
			// we came from a client application : user does not have credentials for access app.
			// Maybe credentials have changed since last login : refresh them from base
			$sso->refreshUser();
			// and retry access
			if ($sso->checkCredentials($sso->session->SSO_REDIRECT)) {
				$sso->resumeApplication();
			}
		} 
		if ($Input->G->RAW->from === 'init_error') {
			// an error occured during application init
			// we refresh user for update credentials, maybe user is not allowed anymore
			$sso->refreshUser();
			// but we don't try to redirect
			$error = "Une erreur est survenue lors de l'authentification à l'application demandée";
		} else {
			$from = $sso->session->SSO_REDIRECT;
			$sso->session->SSO_REDIRECT = NULL;
			
			$error = "Vous avez été redirigé vers cette page car vous n'aviez pas accès à l'application {$from}
Merci de choisir une autre application ci-dessous.";
		}
	?>
<?php 	if (isset($error)) {?>
<div class='errors'>
	<?= nl2br($Input->HTML($error)) ?>
</div>
<?php 	}?>
<?php } ?>

<?php
	$apps = SsoCredential::getAllByUser($sso->getLogin());
	$applis = SsoAppli::getByPath(array_keys($apps));

	$staticTabs=array(
			'list' => 'Applications autorisées', 
			'ask' => 'Demandes d\'accès',
	);
	$tabs = $staticTabs;
// 	$tabs[SSO_WEB_PATH] = 'SSO'; // not necessary : SSO will always use a top right menu
	foreach($applis->data as $appli) {
		$tabs['settings_'.$appli->id] = $appli->name;
	}

	if (!$Input->G->ISSET->id || !isset($tabs[$Input->G->RAW->id])) {
		$id = \salt\first(array_keys($tabs));
	} else {
		$id = $Input->G->RAW->id;
	}
?>

<div class="tabs">
<ul>
<?php foreach($tabs as $k => $v) {?>
	<?php $url = \salt\first(explode('?', $Input->S->RAW->REQUEST_URI,2)).'?page=apps&amp;id='.$Input->URL($k); ?>
	<li class="<?= ($id == $k)?'selected':''?>">
		<a href="<?= $url ?>"><?= $Input->HTML($v) ?></a>
	</li>
<?php } ?>
</ul>
</div>

<?php if (isset($staticTabs[$id])) { ?>
<?php 	include(SSO_RELATIVE.'pages/apps_'.$id.'.php'); ?>
<?php } else { ?>
<?php 	include(SSO_RELATIVE.'pages/apps_settings.php'); ?>
<?php } ?>

