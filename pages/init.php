<?php namespace sso; 

header('Content-Type: text/html; charset='.SSO_CHARSET);

use salt\DatabaseHelper;
use salt\DBHelper;
use salt\InsertQuery;
use salt\FormHelper;
use salt\Dual;
use salt\SqlExpr;
use salt\Query;

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
				$adminUser = new SsoUser();

				$adminUser->id = SSO_DB_USER;
				$adminUser->name = SSO_DB_USER;
				$adminUser->state = SsoUser::STATE_ENABLED;
				
				$q = new Query(Dual::meta());
				$q->select(SqlExpr::func('PASSWORD', $Input->P->RAW->db_pass)->privateBinds(), 'pass');

				$adminUser->password = \salt\first($DB->execQuery($q)->data)->pass;
				$adminUser->admin = TRUE;
				$adminUser->last_login = time();

				$q = new InsertQuery($adminUser);
				$DB->execInsert($q);
			}

		} else {
			$errorMessage = 'Bad DB Password';
		}
	}
}

?>
	<div class="sso">
		<?= FormHelper::post(NULL, array('page' => 'init')) ?>
		<h2 id="init">Initialisation du SSO</h2>
<?php if (isset($errorMessage)) { ?>
		<div class="errors"><?= $Input->HTML($errorMessage) ?></div>
<?php }?>
<?php if (!$sso->session->checked_db_password) { ?>
		<p>Le SSO n'est pas initialisé.<br/>Avant d'accéder à l'initialisation du SOO, merci d'indiquer le mot de passe de la base de donnée.</p>
		<div>
			<?= FormHelper::input('db_pass', 'password') ?>
			<?= FormHelper::input(NULL, 'submit', 'Vérifier') ?>
		</div>
<?php } else { ?>
		<p>Initialisation réussie.
		Un utilisateur a été créé avec le login et le mot de passe de la base de donnée.
		</p>
		<a href="<?=$Input->S->RAW->SCRIPT_NAME ?>?page=admin">Accéder à la configuration du SSO</a>
<?php } ?>
		<?= FormHelper::end() ?>
	</div>
