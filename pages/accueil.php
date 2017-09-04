<?php
/**
 * display main login page
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

use salt\FormHelper;

$error = NULL;
if ($Input->G->ISSET->reason) {
	if ($Input->G->RAW->reason != SsoClient::AUTH_KO_NO_SESSION) {
		$error = L::error_logout_with_reason(SsoClient::getLogoutReason($Input->G->RAW->reason));
	}
}

if ($Input->P->ISSET->sso_user) {

	if ($Input->P->RAW->sso_user === '') {
		$error = L::error_user_missing;
// 	} else if ($Input->P->RAW->sso_password === '') { // in a local install, password can be... empty
// 		$error = L::error_password_missing
	}

	if ($error === NULL) {
		$error = $sso->authUser($Input->P->RAW->sso_user, $Input->P->RAW->sso_password, $Input->P->ISSET->sso_public);
	}
} // isset POST/sso_user

// if we are at login page with a logged session, we redirect on apps list page or previous app
if ($sso->isLogged()) {
	// resume only if we came from a page, not if we access to sso directly
	if ($Input->S->ISSET->HTTP_REFERER) {
		$sso->resumeApplication();
	}
	// if we don't have been redirected, display app list
	$sso->redirectApplications();
}

if ($sso->getUser() !== NULL) {
	$stateMessage = NULL;
	switch($sso->getUser()->getState()) {
		case SsoUser::STATE_DISABLED :
			$stateMessage = L::error_user_state_disabled($sso->getUser()->id);
		break;
		case SsoUser::STATE_TO_VALIDATE :
			$stateMessage = L::error_user_state_pending($sso->getUser()->id);
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
	<h2><?= $Input->HTML(L::label_welcome_login_text) ?></h2>
<?php if ($error !== NULL) { ?>
<?php 	error_log('SSO - Warning - '.$error); ?>
		<div class="errors"><?= $Input->HTML(L::error_login) ?></div>
<?php } ?>

	<fieldset>
		<table>
			<tr>
				<th class="field user"><?= $Input->HTML(L::field_user) ?></th>
				<td class="input user"><?= FormHelper::input('sso_user') ?></td>
			</tr>
			<tr>
				<th class="field password"><?= $Input->HTML(L::field_password) ?></th>
				<td class="input password"><?= FormHelper::input('sso_password', 'password') ?></td>
			</tr>
			<tr>
				<th class="field public"><?= $Input->HTML(L::label_restrict_login_to_session) ?></th>
				<td class="input public"><?= FormHelper::input('sso_public', 'checkbox') ?> (<?= $Input->HTML(L::label_public_computer) ?>)</td>
			</tr>
		</table>
	</fieldset>
	<div class="submit"><input type="submit" value="<?= $Input->HTML(L::button_send) ?>" /></div>
	<?= FormHelper::end() ?>
</div>
<?php }