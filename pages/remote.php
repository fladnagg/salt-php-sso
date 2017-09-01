<?php
/**
 * redirect to application if login OK
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

if ($Input->G->ISSET->return_url) {
	$sso->auth(FALSE, $Input->G->RAW->return_url);
	$sso->resumeApplication();
}

if (!$sso->isLogged()) {
	include(SSO_RELATIVE.'pages/accueil.php');
} else {
	include(SSO_RELATIVE.'pages/apps.php');
}
