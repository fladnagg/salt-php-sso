<?php
/**
 * display application list
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;
?>
<h4><?= $Input->HTML(L::label_access_list) ?></h4>
<div class="appli list">
<?php foreach($applis->data as $appli) {?>
	<?php $icon = (strlen($appli->icon) > 0)?$appli->path.$appli->icon:SSO_WEB_RELATIVE.'images/default-icon.png'; ?>
	<div class="appli item" title="<?= $Input->HTML($appli->description) ?>">
		<a href="<?= $Input->HTML($appli->path) ?>">
		<img src="<?= $Input->HTML($icon)?>" width="64" height="64" alt="image" />
		<?= $appli->VIEW->name ?></a><br/>
	</div>
<?php }?>
</div>
