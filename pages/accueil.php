<?php namespace sso;

use salt\FormHelper;

$error = NULL;
if ($Input->G->ISSET->reason) {
	if ($Input->G->RAW->reason != SsoClient::AUTH_KO_NO_SESSION) {
		$error = 'Vous avez été déconnecté car '.SsoClient::getLogoutReason($Input->G->RAW->reason);
	}
}

if ($Input->P->ISSET->sso_user) {

	if ($Input->P->RAW->sso_user === '') {
		$error = 'Utilisateur obligatoire';
	} else if ($Input->P->RAW->sso_password === '') {
		$error = 'Mot de passe obligatoire';
	}

	if ($error === NULL) {
		$error = $sso->authUser($Input->P->RAW->sso_user, $Input->P->RAW->sso_password, $Input->P->ISSET->sso_public);
	}
} // isset POST/sso_user

// if we are at login page with a logged session, we redirect on apps list page or previous app
if ($sso->isLogged()) {
	// resume only if we came from a page, not if we access to sso directly
	if ($Input->S->ISSET->HTTP_REFERER && $sso->checkCredentials($sso->session->SSO_REDIRECT)) {
		$sso->resumeApplication();
	} else {
		$sso->redirectApplications();
	}
}

if ($sso->getUser() !== NULL) {
	$stateMessage = NULL;
	switch($sso->getUser()->getState()) {
		case SsoUser::STATE_DISABLED :
			$stateMessage = "Votre compte [".$Input->HTML($sso->getUser()->id)."] est actuellement désactivé.
					Vous devez contacter un administrateur pour l'activer";
		break;
		
		case SsoUser::STATE_TO_VALIDATE :
			$stateMessage = "Votre compte [".$Input->HTML($sso->getUser()->id)."] est en attente de validation par un administrateur.
					Merci de réessayer plus tard.";
		break;
	}
	if ($stateMessage !== NULL) {
		$sso->session->logout();
	}
?>
<div id="login_disabled">
	<?= nl2br($Input->HTML($stateMessage))?>
	<br/><br/>
</div>
		
<?php } else { ?>
<div class="login">
	<?= FormHelper::post() ?>
	<h2>Merci de vous identifier</h2>
<?php if ($error !== NULL) { ?>
		<div class="errors"><?= $Input->HTML($error) ?></div>
<?php } ?>
	<fieldset>
		<table>
			<tr>
				<th class="field user">Utilisateur</th>
				<td class="input user"><?= FormHelper::input('sso_user') ?></td>
			</tr>
			<tr>
				<th class="field password">Mot de passe</th>
				<td class="input password"><?= FormHelper::input('sso_password', 'password') ?></td>
			</tr>
			<tr>
				<th class="field public">Restreindre la connexion à cette session</th>
				<td class="input public"><?= FormHelper::input('sso_public', 'checkbox') ?> (Ordinateur public)</td>
			</tr>
		</table>
	</fieldset>
	<div class="submit"><input type="submit" value="Envoyer" /></div>
	<?= FormHelper::end() ?>
</div>
<?php } 