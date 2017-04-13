<?php namespace sso;
error_reporting(\E_ALL);

require_once(__DIR__.\DIRECTORY_SEPARATOR.'SsoClient.class.php');

use salt\Benchmark;
use salt\DBHelper;
use salt\In;

Benchmark::start('page');

DBHelper::registerDefault('SSO', SSO_DB_HOST, SSO_DB_PORT, SSO_DB_DATABASE, SSO_DB_USER, SSO_DB_PASS, SSO_DB_CHARSET);
$DB = DBHelper::getInstance();

// ===================================================
// Handle inputs/outputs
$Input = In::getInstance();
In::setThrowException(FALSE);
In::setCharset(SSO_CHARSET);

// ===================================================
// SSO main instance
$sso = Sso::getInstance();

// ===================================================
// Handle errors
ErrorHandler::init();

// ================================
// Handle pages
$pageId = '';
$pages = $sso->pagesList();
if ($Input->G->ISSET->page) {
	$page = $Input->G->RAW->page;
	$ids = explode('_', $page);
	while(count($ids) > 0) {
		$pageId = array_shift($ids);

		if (!is_array($pages)) {
			throw new BusinessException('La page demandée ['.$Input->G->HTML->page.'] ne fait pas partie des pages autorisées.');
			$page=NULL;
			break;
		}

		if (array_key_exists('_'.$pageId, $pages)) {
			if ($Input->G->ISSET->id === TRUE) {
				$pageId = '_'.$pageId;
			} else {
				// Access to internal page without ID ? forbidden
				throw new BusinessException('Le paramètre id est obligatoire pour accéder à la page '.$Input->G->HTML->page);
			}
		}

		if (!array_key_exists($pageId, $pages)) {
			throw new BusinessException('La page demandée ['.$Input->G->HTML->page.'] ne fait pas partie des pages autorisées.');
			$page=NULL;
			break;
		} else {
			$pages = $pages[$pageId];
		}
	}
} else {
	$page = NULL;
}
if (is_array($pages)) {
	$titre = $pages[''];
} else {
	$titre = $pages;
}

if (($page !== NULL) && ($page !== 'init')) { // we need to be auth before access to another page of the SSO
	$sso->auth(TRUE);
}

define(__NAMESPACE__.'\SSO_TITLE', $titre);