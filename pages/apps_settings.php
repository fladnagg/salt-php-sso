<?php
/**
 * display application settings
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

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
		$errorsProfils[] = L::error_theme_not_recommended;

	} else if (!SsoProfil::isThemeValid($themeId, $appli)) {
		$errorsProfils[] = L::error_theme_invalid;

	} else {
		$profil = NULL;

		if (!SsoProfil::isInternalTheme($themeId)) {
			// retrieve or create current profile
			$q = SsoProfil::query(TRUE);
			if ($Input->P->ISSET->save_recommended) {
				$q->whereAnd('userId', 'IS', SqlExpr::value(NULL));
			} else {
				$q->whereAnd('userId', '=', $sso->getLogin());
				$q->whereAnd('theme', '=', $themeId);
			}
			$q->whereAnd('appliId', '=', $appli);

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
				if ($themeId !== $profil->theme) {
					// with recommended profil, theme can be changed : so we set theme and reset options
					$profil->theme = $themeId;
					$profil->options = '';
				}
				$theme = $profil->getThemeObject();
				foreach($Input->P->RAW->options as $field => $value) {
					if ($field !== 'theme') {
						$theme->FORM->$field = $value;
					}
				}
				$profil->setThemeObject($theme);
			}
			$profil->enabled = TRUE;
		}

		$disabledQuery = NULL;

		// disable all
		if (!$Input->P->ISSET->save_recommended) {
			$disabledQuery = SsoProfil::updateQuery();
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

			if ($Input->P->ISSET->save_recommended) {
				$oksProfils[] = L::label_theme_save_recommended($themeId, $appliName);
			} elseif ($themeId === SsoProfil::RECOMMENDED_PROFILE) {
				$oksProfils[] = L::label_theme_active_recommended($appliName);
			} else {
				$oksProfils[] = L::label_theme_active($themeId, $appliName);
			}

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
						$theme->FORM->$field = $value;
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
	$q = SsoProfil::query(TRUE);
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

<h4><?= $Input->HTML(L::label_theme_title($appliName)) ?></h4>

<?php if (count($errorsProfils) > 0) {?>
<div class="errors"><?= nl2br($Input->HTML(implode("\n", $errorsProfils)))?></div>
<?php }?>
<?php if (count($oksProfils) > 0) {?>
<div class="ok"><?= nl2br($Input->HTML(implode("\n", $oksProfils)))?></div>
<?php }?>
<table class="theme results options">
	<tr>
		<th class="compact"><?= SsoProfil::COLUMN()->theme ?></th>
		<td><?= $profile->FORM->theme ?>
			&nbsp;<?= FormHelper::input('load', 'submit', L::button_load_theme_options) ?>
		</td>
	</tr>
	<tr>
		<th class="compact"><?= $Input->HTML(L::field_description) ?></th>
		<td><?= $Input->HTML($theme->description()) ?></td>
	</tr>
	<tr><th colspan="2"><?= $Input->HTML(L::field_options) ?></th></tr>
<?php if (count($theme->getOptions()) === 0) { ?>
	<tr><td colspan="2"><?= $Input->HTML(L::label_theme_no_options) ?></td></tr>
<?php } else { ?>
<?php 	FormHelper::withNameContainer('options')?>
	<tr class="hidden"><td colspan="2"><?= FormHelper::input('theme', 'hidden', $profile->theme) ?></td></tr>
<?php 	foreach($theme->getOptions() as $fieldName => $value) {?>
	<tr class="options">
		<td class="field compact" ><?= $theme->COLUMN()->$fieldName ?></td>
		<td class="input"><?= $theme->FORM->$fieldName?></td>
	</tr>
<?php 	} ?>
<?php 	FormHelper::withoutNameContainer()?>
<?php } ?>
</table>

<table class="actions">
	<tr>
		<td>
			<?= FormHelper::input('save', 'submit', L::button_save) ?>
			&nbsp;<?= FormHelper::input('preview', 'submit', L::button_preview) ?>
		</td>
	</tr>
<?php if ($sso->isSsoAdmin()) {?>
	<tr>
		<td><?= $Input->HTML(L::label_theme_recommended) ?>:&nbsp;
		<?= FormHelper::input('load_recommended', 'submit', L::button_load) ?>
		&nbsp;
		<?= FormHelper::input('save_recommended', 'submit', L::button_save) ?>
		</td>
	</tr>
<?php } ?>
</table>

<?php if ($Input->P->ISSET->preview) {?>
<?php
/**
 * The preview action set the preview profile in session, but if menu is already loaded,
 * we have to reload page again for display the preview. Calling the load keep all input values
 */
?>
<script type="text/javascript">
	$(function() {
		// we don't load the preview menu yet ? reload page !
		if ($('head link[href*="sso_menu.css.php?<?= SsoProfil::PREVIEW_KEY ?>="]').length === 0) {
			$('input[name=load]')[0].click();
		}
	});
</script>
<?php } ?>

<?= FormHelper::end() ?>