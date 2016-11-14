<?php namespace sso; header('Content-Type: text/html; charset='.SSO_CHARSET);
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?= strtolower(SSO_CHARSET) ?>" />
	<meta http-equiv="Content-Language" content="fr" />
	<link href="<?= SSO_WEB_RELATIVE ?>css/sso.css" rel="stylesheet" type="text/css" media="all"/>
	<link href="<?= SSO_WEB_RELATIVE ?>scripts/jquery-ui-1.11.4/jquery-ui.min.css" rel="stylesheet" type="text/css" media="all"/>
	<?= $sso->displayMenuCssHeader($page === 'init') ?>
	<script src="<?= SSO_WEB_RELATIVE ?>scripts/jquery-1.12.4.min.js" type="text/javascript"></script>
	<script src="<?= SSO_WEB_RELATIVE ?>scripts/jquery-ui-1.11.4/jquery-ui.min.js" type="text/javascript"></script>
	<script src="<?= SSO_WEB_RELATIVE ?>scripts/sso.js" type="text/javascript"></script>	
	<title>SSO - <?= SSO_TITLE ?></title>
</head>
<body>
<?php $sso->displayMenu($page === 'init') ?>