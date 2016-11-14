<?php namespace sso;

use salt\FormHelper;
use salt\InsertQuery;
use salt\Query;
use salt\UpdateQuery;
use salt\ViewControl;
use salt\SqlExpr;

$errorsProfils = array();
$oksProfils = array();

// retrieve accessible apps
$appliName = $tabs[$id];
$appli = \salt\last(explode('_', $id, 2));

$currentTheme = NULL;

if ($Input->P->ISSET->save || $Input->P->ISSET->save_recommended) {
	
	$themeId = $Input->P->RAW->theme;

	$currentTheme = $themeId;
	
	if ($Input->P->ISSET->save_recommended && !SsoProfil::isThemeValid($themeId, NULL)) {
		$errorsProfils[]='Le thème sélectionné ne peut pas être un thème recommandé';

	} else if (!SsoProfil::isThemeValid($themeId, $appli)) {
		$errorsProfils[]='Thème sélectionné invalide';

	} else {
		$profil = NULL;
		
		if (!SsoProfil::isInternalTheme($themeId)) {
			// retrieve or create current profile
			$q = new Query(SsoProfil::meta(), TRUE);
			if ($Input->P->ISSET->save_recommended) {
				$q->whereAnd('userId', 'IS', NULL);
			} else {
				$q->whereAnd('userId', '=', $sso->getLogin());
			}
			$q->whereAnd('appliId', '=', $appli);
			$q->whereAnd('theme', '=', $themeId);
			
			$profil = \salt\first($DB->execQuery($q)->data);
			
			if ($profil === NULL) {
				$user = NULL;
				if (!$Input->P->ISSET->save_recommended) {
					$user = $sso->getLogin();
				}
				$profil = SsoProfil::createNew($appli, $appliName, $user);
				$profil->theme = $themeId;
			}
			
			if (is_array($Input->P->RAW->options) && ($Input->P->RAW->options['theme'] === $themeId)) {
				$theme = $profil->getThemeObject();
				foreach($Input->P->RAW->options as $field => $value) {
					if ($field !== 'theme') {
						$theme->$field = $value;
					}
				}
				$profil->setThemeObject($theme);
			}
			$profil->enabled = TRUE;
		}
		
		$disabledQuery = NULL;

		// disable all
		if (!$Input->P->ISSET->save_recommended) {
			$disabledQuery = new UpdateQuery(SsoProfil::meta());
			$disabledQuery->allowMultipleChange();
			$disabledQuery->whereAnd('userId', '=', $sso->getLogin());
			$disabledQuery->whereAnd('appliId', '=', $appli);
	
			if (!SsoProfil::isInternalTheme($themeId)) {
				$disabledQuery->whereAnd('theme', '<>', $themeId);
			}
			
			$disabledQuery->set('enabled', FALSE);
		}

		$DB->beginTransaction();
		
		try {
			if ($disabledQuery !== NULL) {
				$DB->execUpdate($disabledQuery);
			}

			if ($profil !== NULL) {
				if ($profil->isNew()) {
					$DB->execInsert(new InsertQuery($profil));
				} else if ($profil->isModified()) {
					$DB->execUpdate(new UpdateQuery($profil));
				}
			}
			
			$DB->commit();
			
			SsoProfil::initProfiles($sso);

			$message = '';
			if ($Input->P->ISSET->save_recommended) {
				$message.='Le thème ['.$themeId.'] a été sauvegardé comme thème recommandé';
			} else {
				if ($themeId === SsoProfil::RECOMMENDED_PROFILE) {
					$message.='Le thème recommandé a été activé';
				} else {
					$message.='Le thème ['.$themeId.'] a été activé';
				}
			}
			$message.=' pour l\'application ['.$appliName.']';

			$oksProfils[] = $message;
		} catch (\Exception $ex) {
			$oksProfils = array();
			$DB->rollback();
			throw $ex;
		}
	} // theme valid
	
} // save profil

if ($Input->P->ISSET->load) {
	$currentTheme = $Input->P->RAW->theme;
	if (!SsoProfil::isThemeValid($currentTheme, $appli)) {
		$currentTheme = NULL;
	}
} else if ($Input->P->ISSET->load_recommended) {
	$currentTheme = NULL;
}

if ($Input->P->ISSET->preview) {
	$profil = SsoProfil::createNew(SsoProfil::PREVIEW_KEY, NULL, $sso->getLogin());
	$currentTheme = $Input->P->RAW->theme;
	if (SsoProfil::isThemeValid($currentTheme, $appli)) {
		$theme = NULL;
		if (SsoProfil::isInternalTheme($currentTheme)) {
			$internalProfile = SsoProfil::getInternalProfile($sso, $appli, $currentTheme);
			if ($internalProfile === NULL) {
				$internalProfile = SsoProfil::createNew(NULL, NULL, $sso->getLogin());
			}
			$profil->theme = $internalProfile->theme;
			$profil->setThemeObject($internalProfile->getThemeObject());
		} else {
			$profil->theme = $currentTheme;
			if (is_array($Input->P->RAW->options) && ($Input->P->RAW->options['theme'] === $currentTheme)) {
				$theme = $profil->getThemeObject();
				foreach($Input->P->RAW->options as $field => $value) {
					if ($field !== 'theme') {
						$theme->$field = $value;
					}
				}
				$profil->setThemeObject($theme);
			}
		}
		SsoProfil::setPreview($profil);
	}
}

// load real themes
$profile = NULL;
if (!SsoProfil::isInternalTheme($currentTheme) || ($currentTheme === NULL)) {
	$q = new Query(SsoProfil::meta(), TRUE);
	if ($Input->P->ISSET->load_recommended) {
		$q->whereAnd('userId', 'IS', SqlExpr::value(NULL));
	} else {
		$q->whereAnd('userId', '=', $sso->getLogin());
	}
	if ($currentTheme === NULL) { // without selected theme, we use the selected one
		$q->whereAnd('enabled', '=', TRUE);
	} else {
		$q->whereAnd('theme', '=', $currentTheme);
	}
		
	$q->whereAnd('appliId', '=', $appli);

	$profile = \salt\first($DB->execQuery($q)->data);
}

if ($profile === NULL) {
	$profile = SsoProfil::createNew($appli, $appliName, $sso->getLogin());
	if ($currentTheme !== NULL) {
		$profile->theme = $currentTheme;
	}
}

if ($Input->P->ISSET->load_recommended) {
	$Input->P->SET->theme = $profile->theme;
}

$theme = $profile->getThemeObject();

ViewControl::edit();
?>
<?= FormHelper::post(NULL, array('page', 'id')) ?>

<h4>Modifier le profil pour l'application <?= $Input->HTML($appliName) ?></h4>

<?php if (count($errorsProfils) > 0) {?>
<div class="errors">
<?php 	foreach($errorsProfils as $err) {?>
	<?= $Input->HTML($err)?><br/>
<?php 	}?>
</div>
<?php }?>
<?php if (count($oksProfils) > 0) {?>
<div class="ok">
<?php 	foreach($oksProfils as $ok) {?>
	<?= $Input->HTML($ok)?><br/>
<?php 	}?>
</div>
<?php }?>
<table class="theme results options">
	<tr>
		<th class="compact"><?= SsoProfil::COLUMN('theme') ?></th>
		<td><?= $profile->FORM->theme ?>
			&nbsp;<?= FormHelper::input('load', 'submit', 'Charger les options du thème') ?>
		</td>
	</tr>
	<tr>
		<th class="compact">Description</th>
		<td><?= $Input->HTML($theme->description()) ?></td>
	</tr>
	<tr><th colspan="2">Options</th></tr>
<?php if (count($theme->getOptions()) === 0) { ?>
	<tr><td colspan="2">Le thème n'a pas d'option configurable</td></tr>
<?php } else { ?>
<?php 	FormHelper::withNameContainer('options')?>
	<tr class="hidden"><td colspan="2"><?= FormHelper::input('theme', 'hidden', $profile->theme) ?></td></tr>
<?php 	foreach($theme->getOptions() as $fieldName => $value) {?>	
	<tr class="options">
		<td class="field compact" ><?= $theme->COLUMN($fieldName) ?></td>
		<td class="input"><?= $theme->FORM->$fieldName?></td>
	</tr>
<?php 	} ?>
<?php 	FormHelper::withoutNameContainer()?>
<?php } ?>
</table>

<table class="actions">
	<tr>
		<td>
			<?= FormHelper::input('save', 'submit', 'Sauvegarder') ?>
			&nbsp;<?= FormHelper::input('preview', 'submit', 'Aperçu') ?>
		</td>
	</tr>
<?php if ($sso->isSsoAdmin()) {?>
	<tr>
		<td>Thème recommandé (administrateurs seulement):&nbsp;
		<?= FormHelper::input('load_recommended', 'submit', 'Charger') ?>
		&nbsp;
		<?= FormHelper::input('save_recommended', 'submit', 'Sauvegarder') ?>
		</td>
	</tr>
<?php } ?>
</table>

<?php if (($Input->P->ISSET->preview) && !SsoProfil::isPreview()) {?>
<?php 
/**
 * The preview action set the preview profile in session, but menu is already loaded, so
 * we have to reload page again for display the preview. Calling the load keep all input values
 */
?>
<script>
	$(function() {
		$('input[name=load]')[0].click();
	});
</script>
<?php } ?>

<?= FormHelper::end() ?>