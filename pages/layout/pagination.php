<?php
/**
 * display pagination
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages\layout
 */
namespace sso;

use salt\Pagination;
use salt\FormHelper;

?>
<?php if (isset($pagination) && ($pagination instanceof Pagination) && !$pagination->isEmpty()) {?>

<?php
if (!isset($pagination_type)) {
	$pagination_type='';
} else if ($pagination_type == '') {
	$pagination_type=1;
} else {
	$pagination_type++;
}
?>

<table class="pagination">
<tr>
	<td>
<?php if ($pagination->getPage() > 1) {?>
		<?= FormHelper::input(NULL, 'button', '<<', NULL, array('id' => 'first'.$pagination_type)) ?>
		<?= FormHelper::input(NULL, 'button', '<', NULL, array('id' => 'previous'.$pagination_type)) ?>
<?php } ?>
	</td>
	<td>
		<?= $Input->HTML(L::pagination_results($pagination->getCount())) ?>
	</td>
	<td>
		<?= $Input->HTML(L::pagination_pages($pagination->getPage(), $pagination->getMaxPages())) ?>
	</td>
	<td>
<?php if ($pagination->getMaxPages() > 1) {?>
			<label><?= $Input->HTML(L::pagination_goto) ?> :</label>
			<select
	<?php if ($pagination_type =='') {?>
				name="offset<?= $pagination_type ?>"
	<?php }?>
				id="offset<?= $pagination_type ?>">
	<?php for($i=1; $i <= $pagination->getMaxPages(); $i++) {
		$selected = ($pagination->getPage() == $i)?' selected="selected"':''?>
					<option value="<?= $pagination->getOffsetFromPage($i) ?>"<?= $selected ?>><?= $i ?></option>
	<?php }?>
			</select>
<?php }?>
	</td>
	<td>
<?php if ($pagination->getPage() < $pagination->getMaxPages()) {?>
			<input type="button" id="next<?= $pagination_type ?>" value=">" />
			<input type="button" id="last<?= $pagination_type ?>" value=">>" />
<?php }?>
	</td>
</tr>
</table>

<script type="text/javascript">
$(function() {
	$("#first<?= $pagination_type ?>").click(function() {
		var current = $("#offset option:selected");
		current.prop('selected', null);
		current.parent().children('option').first().prop('selected', true);
		current.closest('select').change();
	});
	$("#last<?= $pagination_type ?>").click(function() {
		var current = $("#offset option:selected");
		current.prop('selected', null);
		current.parent().children('option').last().prop('selected', true);
		current.closest('select').change();
	});

	$("#next<?= $pagination_type ?>").click(function() {
		var current = $("#offset option:selected");
		current.prop('selected', null);
		current.next().prop('selected', true);
		current.closest('select').change();
	});
	$("#previous<?= $pagination_type ?>").click(function() {
		var current = $("#offset option:selected");
		current.prop('selected', null);
		current.prev().prop('selected', true);
		current.closest('select').change();
	});
	$("#offset<?= $pagination_type ?>").change(function() {
		<?php if ($pagination_type =='') {?>
		this.form.submit();
		<?php } else { ?>
		var current = $("#offset option:selected");
		current.prop('selected', null);
		current.parent().children('option[value="'+this.value+'"]').prop('selected', true);
		current.closest('select').change();
		<?php } ?>
	});
});
</script>

<?php }?>