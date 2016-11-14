<?php namespace sso;

/***
 * We have to load all classes... because we wanted to check theme css last modified time, so we have to load Theme class
 * for retrieve theme css file. Theme classes is retrieve from session and extends Base : we have to load all.
 */
ob_start();
include(implode(DIRECTORY_SEPARATOR, array(__DIR__, '../lib/base.php')));
ErrorHandler::disable();
ob_end_clean();  // avoid send headers
// remove all useless headers
if (!headers_sent()) {
	foreach(headers_list() as $h) {
		header_remove(\salt\first(explode(':', $h)));
	}
	$app = \salt\first(explode('=', $Input->S->RAW->QUERY_STRING));
} else {
	$app = \salt\first(explode('=', $id));
}

if ($app === 'sso') {
	$app = NULL; // load default theme
} else {
	$app = urldecode($app);
}

$profil = SsoProfil::getCurrent($sso, $app);
$theme = $profil->getThemeObject();

if (!headers_sent()) {
	header('Content-type: text/css; charset: '.SSO_CHARSET);
	
	if (($profil->path === SsoProfil::PREVIEW_KEY)) {
		SsoProfil::clearPreview(); // never send a 304 on preview
	} else {
		$lastModifiedTime = max(filemtime(__FILE__), filemtime($theme->getCssFile()));
		
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && ($_SERVER['HTTP_IF_NONE_MATCH'] === $_SERVER['QUERY_STRING'])
		&& isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModifiedTime)) {
			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
			die();
		}
		
		// headers for cache
		header('Etag: '.$_SERVER['QUERY_STRING']); // php 5.1.2+ prevent header injection, no need of escape here 
		header('Last-Modified: '.gmdate(DATE_RFC1123, $lastModifiedTime).' GMT');
	}
}

echo $theme->displayCss();

$recommended = ($profil->userId === NULL)?'TRUE':'FALSE';
// Add some information in css builded file 
echo <<<INFOS

/*
Theme : {$theme->id}
Profil : {$profil->id}
Application : {$profil->appliId}: {$profil->path} 
Recommended : {$recommended}
*/
INFOS;
