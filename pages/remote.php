<?php
/**
 * redirect to application if login OK
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\pages
 */
namespace sso;

$error = NULL;
if ($Input->G->ISSET->return_url) {
	$sso->auth(FALSE, $Input->G->RAW->return_url);
	$sso->resumeApplication();
}

include(SSO_RELATIVE.'pages/index.php');
