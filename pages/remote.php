<?php
/**
 * redirect to application if login OK
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

if ($Input->G->ISSET->return_url) {
	$sso->setRedirectUrl($Input->G->RAW->return_url, TRUE);
	$sso->auth(TRUE, TRUE, TRUE);
}

if (!$sso->isLogged()) {
	include(SSO_RELATIVE.'pages/accueil.php');
} else {
	include(SSO_RELATIVE.'pages/apps.php');
}
