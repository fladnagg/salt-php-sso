<?php namespace sso;

include('lib/base.php');

use salt\DBHelper;

if ($page === NULL) {
	$page = \salt\first(explode('&', $Input->S->RAW->QUERY_STRING, 2));
	if (strpos($page, '=') !== FALSE) {
		$page = NULL;
	}
}

if (empty($page)) {
	$page='accueil';
}

include('pages/layout/header.php'); // FIXME : page can be redirected AFTER this out...

$file='pages/'.$page.'.php';
if (file_exists($file)) {
	ob_start(); // in case of error on page, we don't want to partially display it : we will call ob_end_clean()
	include_once($file);
	ob_end_flush();
} else {
	ErrorHandler::addError('La page demandée n\'existe pas !');
}

DBHelper::checkAllTransactionsEnded();

include('pages/layout/footer.php');
