<?php
/**
 * display initialization page if SSO tables does not exists
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

header('Content-Type: text/html; charset='.SSO_CHARSET);

use salt\DatabaseHelper;
use salt\DBHelper;
use salt\FormHelper;
use salt\InsertQuery;

$errorMessage=NULL;

$missingObjects = DatabaseHelper::missingTables($DB, array(
		__NAMESPACE__.'\SsoAppli',
		__NAMESPACE__.'\SsoCredential',
		__NAMESPACE__.'\SsoUser',
		__NAMESPACE__.'\SsoProfil',
		__NAMESPACE__.'\SsoGroup',
		__NAMESPACE__.'\SsoGroupElement',
		__NAMESPACE__.'\SsoAuthMethod',
));

if (count($missingObjects) > 0) {
	$sso->session->checked_db_password = FALSE;

	if ($Input->P->ISSET->db_pass) {
		if (DBHelper::checkPassword('SSO', $Input->P->RAW->db_pass)) {
			$sso->session->checked_db_password = TRUE;

			if (DatabaseHelper::createTablesFromObjects($DB, $missingObjects)) {
				$adminUser = SsoUser::getInitUser();

				$q = new InsertQuery($adminUser);
				$DB->execInsert($q);
			}

		} else {
			$errorMessage = L::error_bad_password;
		}
	}
}

?>
	<div class="sso">
		<?= FormHelper::post(NULL, array('page' => 'init')) ?>
		<h2 id="init"><?= $Input->HTML(L::label_init_title) ?></h2>
<?php if (isset($errorMessage)) { ?>
		<div class="errors"><?= $Input->HTML($errorMessage) ?></div>
<?php }?>
<?php if (!$sso->session->checked_db_password) { ?>
		<p><?= nl2br($Input->HTML(L::label_init_message)) ?></p>
		<div>
			<?= FormHelper::input('db_pass', 'password') ?>
			<?= FormHelper::input(NULL, 'submit', L::button_check) ?>
		</div>
<?php } else { ?>
		<p><?= nl2br($Input->HTML(L::label_init_success)) ?></p>
		<a href="<?=$Input->S->RAW->SCRIPT_NAME ?>?page=admin"><?= $Input->HTML(L::label_init_goto_configuration) ?></a>
<?php } ?>
		<?= FormHelper::end() ?>
	</div>
