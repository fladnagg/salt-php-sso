<?php namespace sso; ?>
<?php if (count(ErrorHandler::$ERRORS)>0) { ?>
<div id="errors">
	<ul>
		<?php foreach(ErrorHandler::$ERRORS as $err) { ?>
			<li<?= (strpos($err, ErrorHandler::SUBERROR_PREFIX)===0)?' class="suberror"':'' ?>><?= nl2br($Input->HTML($err)) ?></li>
		<?php } ?>
	</ul>
</div>
<?php } ?>
