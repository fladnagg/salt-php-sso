<?php namespace sso; 

use salt\SqlExpr;
use salt\Dual;
use salt\Query;

?>
<h2>Profil du compte SSO <?= $Input->HTML($sso->getLogin())?></h2>
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
			$errors[] = 'Les mots de passe ne correspondent pas';
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

	if ((count($errors) === 0) && ($user->isModified())) {
		$q = new UpdateQuery($user);
		
		if ($changePassword) {
			$q->set('password', SqlExpr::value($user->password)->privateBinds());
		}

		$DB->execUpdate($q, 1);

		$oks[] = 'Les modifications ont été effectuées et seront effectives au prochain login';
	}
}

$user = SsoUser::findFromId($sso->getLogin());

ViewControl::edit();
?>

<?php if (count($errors) > 0) {?>
<div class="errors">
	<?php foreach($errors as $err) {?>
	<?= $Input->HTML($err) ?><br/>
	<?php }?>
</div>
<?php }?>
<?php if (count($oks) > 0) {?>
<div class="ok">
	<?php foreach($oks as $ok) {?>
	<?= $Input->HTML($ok) ?><br/>
	<?php }?>
</div>
<?php }?>
<?= FormHelper::post(NULL, array('page')) ?>
<table class="sso profil results">
	<tr>
		<th colspan="2">Options générales</th>
	</tr>
	<tr>
		<td class="fieldname">ID</td>
		<td><?= $user->VIEW->id ?></td>
	</tr>
	<tr>
		<td class="fieldname">Nom</td>
		<td><?= $user->FORM->name ?></td>
	</tr>
	<tr>
		<td class="fieldname">Administrateur</td>
		<td><?= $user->VIEW->admin ?></td>
	</tr>
	<tr>
		<th colspan="2">Options de sécurité</th>
	</tr>
	<tr>
		<td class="fieldname">Vérifier l'adresse IP</td>
		<td><?= $user->FORM->restrictIP ?><img src="<?= SSO_WEB_RELATIVE ?>images/help.png" alt="aide" title="Cliquez pour afficher l'aide" class="pointer"/></td>
	</tr>
	<tr class="pointer" title="Cliquez pour cacher l'aide">
		<td colspan="2">
		A chaque accès, on vérifiera que l'adresse IP de l'utilisateur correspond à celle utilisée lors du
		dernier login. C'est une protection contre le vol de session, mais il ne faut pas l'activer si votre
		fournisseur d'accès ou votre moyen de vous connecter à internet passe par une ferme de proxy sortants,
		votre adresse IP n'étant alors pas fixe pour une même session.
		</td>
	</tr>
	<tr>
		<td class="fieldname">Vérifier le User Agent</td>
		<td><?= $user->FORM->restrictAgent ?><img src="<?= SSO_WEB_RELATIVE ?>images/help.png" alt="aide" title="Cliquez pour afficher l'aide" class="pointer"/></td>
	</tr>
	<tr class="pointer" title="Cliquez pour cacher l'aide">
		<td colspan="2">
		A chaque accès, on vérifiera que le User Agent (c'est à dire une chaîne caractérisant votre navigateur)
		correspond à celui utilisé lors du dernier login. C'est une protection contre le vol de session assez
		fiable car le User Agent ne change jamais en principe; mais beaucoup d'utilisateurs ont le même et 
		ce n'est pas une donnée privée puisqu'il est communiqué à chaque site visité.
		</td>
	</tr>
	<tr>
		<td class="fieldname">Durée de la session</td>
		<td><?= $user->FORM->timeout ?><img src="<?= SSO_WEB_RELATIVE ?>images/help.png" alt="aide" title="Cliquez pour afficher l'aide" class="pointer"/></td>
	</tr>
	<tr class="pointer" title="Cliquez pour cacher l'aide">
		<td colspan="2">
		Au bout de cette durée sans connexion ou activité, une authentification sera redemandée. 
		Si on choisit une durée de 0, la session expirera lors de la fermeture du navigateur.
		</td>
	</tr>

<?php if ($sso->session->SSO_LOCAL_AUTH) { ?>
	<tr>
		<th colspan="2">Mot de passe</th>
	</tr>
	<tr>
		<td class="fieldname">Mot de passe</td>
		<td><?= $user->FORM->password ?></td>
	</tr>
	<tr>
		<td class="fieldname">Confirmer le mot de passe</td>
		<td><?= FormHelper::input('password2', 'password', '') ?></td>
	</tr>
<?php } ?>

	<tr>
		<th colspan="2">Statistiques</th>
	</tr>
	<tr>
		<td class="fieldname">Nombre de login réussis</td>
		<td><?= $user->VIEW->login_count ?> (Dernier login : <?= $user->VIEW->last_login ?>)</td>
	</tr>
	<tr>
		<td class="fieldname">Nombre de login en échec</td>
		<td><?= $user->VIEW->failed_login_count ?> (Dernier échec : <?= $user->VIEW->last_failed_login ?>)</td>
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
<p><?= FormHelper::input('submit', 'submit', 'Modifier') ?></p>
<?= FormHelper::end() ?>
