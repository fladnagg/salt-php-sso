<?php
/**
 * display user settings page
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

use salt\SqlExpr;
use salt\Dual;
use salt\Query;

?>
<h2><?= $Input->HTML(L::label_setting_title($sso->getLogin()))?></h2>
<?php

use salt\FormHelper;
use salt\UpdateQuery;
use salt\ViewControl;

$errors = array();
$oks = array();
if ($Input->P->ISSET->submit) {
	$user = SsoUser::findFromId($sso->getLogin());
	if (strlen(trim($Input->P->RAW->name))===0) {
		$user->name = '';
	} else {
		$user->name = $Input->P->RAW->name;
	}

	$changePassword = FALSE;

	if ($Input->P->ISSET->password && trim($Input->P->RAW->password) !== '') {
		if ($Input->P->RAW->password !== $Input->P->RAW->password2) {
			$errors[] = L::error_user_password_mismatch;
		} else {
			$changePassword = TRUE;
			$q = Dual::query();
			$q->select(SqlExpr::_PASSWORD($Input->P->RAW->password)->privateBinds(), 'pass');
			$user->password = \salt\first($DB->execQuery($q)->data)->pass;
		}
	}

	$user->restrictIP = $Input->P->ISSET->restrictIP;
	$user->restrictAgent = $Input->P->ISSET->restrictAgent;
	$user->timeout = SsoUser::arrayToIntTimeout($Input->P->RAW->timeout);

	$user->lang = $Input->P->RAW->lang;

	if (count($errors) === 0) {
		if (Locale::set($user->lang)) {
			$oks[] = L::label_setting_language_change_next_page;
		}

		if ($user->isModified()) {
			$q = new UpdateQuery($user);

			if ($changePassword) {
				$q->set('password', SqlExpr::value($user->password)->privateBinds());
			}

			$DB->execUpdate($q, 1);

			$oks[] = L::label_setting_change_after_next_login;
		}
	}
}

$user = SsoUser::findFromId($sso->getLogin());

ViewControl::edit();
?>

<?php if (count($errors) > 0) {?>
<div class="errors"><?= nl2br($Input->HTML(implode("\n", $errors))) ?></div>
<?php }?>
<?php if (count($oks) > 0) {?>
<div class="ok"><?= nl2br($Input->HTML(implode("\n", $oks))) ?></div>
<?php }?>
<?= FormHelper::post(NULL, array('page')) ?>
<table class="sso profil results">
	<tr>
		<th colspan="2"><?= $Input->HTML(L::label_setting_main_options) ?></th>
	</tr>
<?php foreach(array('id' => 'VIEW',
					'name' => 'FORM',
					'admin' => 'VIEW') as $field => $type) { ?>
	<tr>
		<td class="fieldname"><?= $user::COLUMN($field) ?></td>
		<td><?= $user->{$type}->{$field} ?></td>
	</tr>
<?php } ?>
	<tr>
		<td class="fieldname"><?= $user::COLUMN('lang') ?></td>
		<td><?= $user->FORM->lang ?></td>
	</tr>
	<tr>
		<th colspan="2"><?= $Input->HTML(L::label_setting_security_options) ?></th>
	</tr>
	<tr>
		<td class="fieldname"><?= $user::COLUMN('restrictIP') ?></td>
		<td><?= $user->FORM->restrictIP ?><img src="<?= SSO_WEB_RELATIVE ?>images/help.png" alt="aide" title="<?= $Input->HTML(L::help_click_for_display) ?>" class="pointer"/></td>
	</tr>
	<tr class="pointer" title="<?= $Input->HTML(L::help_click_for_hide) ?>">
		<td colspan="2"><?= $Input->HTML(L::help_user_check_ip) ?></td>
	</tr>
	<tr>
		<td class="fieldname"><?= $user::COLUMN('restrictAgent') ?></td>
		<td><?= $user->FORM->restrictAgent ?><img src="<?= SSO_WEB_RELATIVE ?>images/help.png" alt="aide" title="<?= $Input->HTML(L::help_click_for_display) ?>" class="pointer"/></td>
	</tr>
	<tr class="pointer" title="<?= $Input->HTML(L::help_click_for_hide) ?>">
		<td colspan="2"><?= $Input->HTML(L::help_user_check_agent) ?></td>
	</tr>
	<tr>
		<td class="fieldname"><?= $user::COLUMN('timeout') ?></td>
		<td><?= $user->FORM->timeout ?><img src="<?= SSO_WEB_RELATIVE ?>images/help.png" alt="aide" title="<?= $Input->HTML(L::help_click_for_display) ?>" class="pointer"/></td>
	</tr>
	<tr class="pointer" title="<?= $Input->HTML(L::help_click_for_hide) ?>">
		<td colspan="2"><?= $Input->HTML(L::help_user_session_duration) ?></td>
	</tr>

<?php if ($sso->session->SSO_LOCAL_AUTH) { ?>
	<tr>
		<th colspan="2"><?= $Input->HTML(L::field_password) ?></th>
	</tr>
	<tr>
		<td class="fieldname"><?= $Input->HTML(L::field_password) ?></td>
		<td><?= $user->FORM->password ?></td>
	</tr>
	<tr>
		<td class="fieldname"><?= $Input->HTML(L::field_confirm_password) ?></td>
		<td><?= FormHelper::input('password2', 'password', '') ?></td>
	</tr>
<?php } ?>

	<tr>
		<th colspan="2"><?= $Input->HTML(L::label_setting_statistics) ?></th>
	</tr>
	<tr>
		<td class="fieldname"><?= $user::COLUMN('login_count') ?></td>
		<td><?= $user->VIEW->login_count ?> (<?= $user::COLUMN('last_login') ?> : <?= $user->VIEW->last_login ?>)</td>
	</tr>
	<tr>
		<td class="fieldname"><?= $user::COLUMN('failed_login_count') ?></td>
		<td><?= $user->VIEW->failed_login_count ?> (<?= $user::COLUMN('last_failed_login') ?> : <?= $user->VIEW->last_failed_login ?>)</td>
	</tr>
</table>
<script type="text/javascript">
$(function() {
	$('tr.pointer').hide();
	$('tr.pointer').click(function() {
		$(this).hide();
	});
	$('img.pointer').click(function() {
		$(this).closest('tr').next('tr.pointer').toggle();
	})
})
</script>
<p><?= FormHelper::input('submit', 'submit', L::button_modify) ?></p>
<?= FormHelper::end() ?>
