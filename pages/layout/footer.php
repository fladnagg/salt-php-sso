<?php namespace sso;

use salt\Benchmark;

include(SSO_RELATIVE.'pages/layout/errors.php'); ?>
<div class="footer">
<?php if (ini_get('display_errors') || $sso->isSsoAdmin()) { ?>
	<table class="footer benchmark hidden">
		<tr><th>Compteurs</th><th>Temps</th></tr>
		<tr>
			<td>
<?php Benchmark::stop('page'); Benchmark::start('page'); // for update time and display it below ?>
<?php foreach(Benchmark::getAllCounters() as $k => $v) { ?>
				<?= $Input->HTML($k)?>: <?= $Input->HTML($v) ?><br/>
<?php } ?>
			</td>
			<td>
<?php foreach(Benchmark::getAllTimes() as $k => $v) { ?>
				<?= $Input->HTML($k)?>: <?= $Input->HTML(round($v, \salt\BENCH_PRECISION))?>ms<br/>
<?php }?>
			</td>
		</tr>
	</table>
<?php if (Benchmark::hasData('salt.queries')) {?>
	<table class="benchmark sql hidden">
		<tr>
<?php 	foreach(\salt\first(Benchmark::getData('salt.queries')) as $k => $v) {?>
			<th><?= $Input->HTML($k) ?></th>
<?php 	}?>
		</tr>
<?php 	foreach(Benchmark::getData('salt.queries') as $k=>$data) { ?>
		<tr>
<?php 		foreach($data as $v) { ?>
			<td onclick="javascript:$(this).next('td').first().toggle();$(this).toggle()">
				<?= str_replace("\0", '\0', nl2br($Input->HTML(preg_replace('( (?:FROM|[A-Z ]+ JOIN|WHERE|GROUP|ORDER|LIMIT|SET) )', "\n".' $0 ', $v)))) ?>
			</td>
			<td class="hidden" onclick="javascript:$(this).prev('td').first().toggle();$(this).toggle()">
				<?php $values = Benchmark::getData('salt.queriesValues') ?>
				<?= str_replace("\0", '\0', nl2br($Input->HTML(preg_replace('( (?:FROM|[A-Z ]+ JOIN|WHERE|GROUP|ORDER|LIMIT|SET) )', "\n".' $0 ', $values[$k])))) ?>
			</td>
<?php 		}?>
		</tr>
<?php 	}?>
	</table>
<?php }?>
	<div onclick="javascript:$('table.benchmark').toggle();">
			Page générée en <?= round(Benchmark::end('page'), \salt\BENCH_PRECISION) ?> ms
	</div>
<?php } else { ?>
	Page générée en <?= round(Benchmark::end('page'), \salt\BENCH_PRECISION) ?> ms
<?php } ?>
</div>
</body>
</html>