<?php
/**
 * display administration language page
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

use salt\FormHelper;
use salt\I18n;

$report = '';
if ($Input->P->ISSET->check) {
	ob_start();
	I18n::getInstance('SSO')->check(TRUE);
	$report = ob_get_clean();
} else if ($Input->P->ISSET->generate) {
	ob_start();
	I18n::getInstance('SSO')->generate(TRUE);
	$report = ob_get_clean();
}
?>
<h3><?= $Input->HTML(L::admin_language) ?></h3>

<?php if ($report !== '') { ?>
<div class="console" style="text-align:left"><?= $report ?></div>
<?php } ?>
<?= FormHelper::post(NULL, array('page', 'subpage')); ?>
<div>
<?= $Input->HTML(L::label_admin_lang_default(\salt\I18N_DEFAULT_LOCALE)) ?><br/>
<?= $Input->HTML(L::label_admin_lang_current(SSO_CURRENT_LOCALE)) ?><br/>
<?= $Input->HTML(L::label_admin_lang_mode_type) ?>
<?php if (I18n::getInstance('SSO')->isGenerationMode(I18n::MODE_REGENERATE_ON_THE_FLY)) { ?>
	<?= $Input->HTML(L::label_admin_lang_mode_auto) ?>
<?php } else if (I18n::getInstance('SSO')->isGenerationMode(I18n::MODE_USE_GENERATED)) { ?>
	<?= $Input->HTML(L::label_admin_lang_mode_generated) ?>
<?php } ?>
<br/><br/>

<?= FormHelper::input('check', 'submit', L::label_admin_lang_check) ?>
&nbsp;<?= FormHelper::input('generate', 'submit', L::label_admin_lang_generate, array(), array('onclick' =>
	"javascript: return confirm('".str_replace("\n", '\n', addslashes(L::label_admin_lang_generate_confirm))."')"
)) ?>
</div>
<?= FormHelper::end() ?>
