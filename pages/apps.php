<?php
/**
 * display main user page with available applications
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;
?>
<h2><?= $Input->HTML(L::label_app_title($sso->getLogin()))?></h2>
<?php $error = NULL; ?>
<?php if ($Input->G->ISSET->from) {

		if ($Input->G->RAW->from === 'client') {
			// we came from a client application : user does not have credentials for access app.
			// Maybe credentials have changed since last login : refresh them from base
			$sso->refreshUser();
			// and retry access
			$sso->auth(TRUE, FALSE, TRUE);
		}
		if ($Input->G->RAW->from === 'init_error') {
			// an error occured during application init
			// we refresh user for update credentials, maybe user is not allowed anymore
			$sso->refreshUser();
			// but we don't try to redirect
			$error = L::error_app_auth;
		} else {
			$from = $sso->session->SSO_REDIRECT;
			$sso->session->SSO_REDIRECT = NULL;
			$error = L::error_app_forbidden(($from !== NULL)?$from['url']:NULL);
		}
	?>
<?php 	if (isset($error)) {?>
<div class='errors'><?= nl2br($Input->HTML($error)) ?></div>
<?php 	}?>
<?php } ?>

<?php
	$apps = SsoCredential::getAllByUser($sso->getLogin());
	$applis = SsoAppli::getByPath(array_keys($apps));
	$user = SsoUser::getById($DB, $sso->getLogin());
	
	$staticTabs=array(
		'list' => L::app_menu_list,
	);
	if ($user->can_ask) {
		$staticTabs['ask'] = L::app_menu_ask;
	}
	
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

