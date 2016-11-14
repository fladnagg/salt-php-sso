<?php namespace sso;
if ($_sso->isLogged()) {
	$_Input = \salt\In::getInstance();
	
	$_req = $_Input->S->RAW->REQUEST_URI;
	$_req = \salt\first(explode('?', $_req, 2));

	if ($_sso->isSubPath($_req, SSO_WEB_PATH)) { // we are on a SSO page
		if ($_Input->S->ISSET->HTTP_REFERER) {
			$_ref = $_Input->S->RAW->HTTP_REFERER;
			$_pathRef = '/'.\salt\last(explode('/', $_ref, 4)); // 4 for bypass protocol://host/...
			if (!$_sso->isSubPath($_pathRef, SSO_WEB_PATH)) { // we came from a page which is not a SSO page
				$_sso->session->SSO_RETURN_URL = $_ref; // register return URL
			}
		}
	} else if (isset($_sso->session->SSO_RETURN_URL)) { // we are NOT on a SSO page
		$_sso->session->SSO_RETURN_URL = NULL; // we clean the return URL
	}
	
	$_pages = $_sso->pagesList();
	
	$links = array();
	if (isset($_sso->session->SSO_RETURN_URL)) {
		$links[]=array($_sso->session->SSO_RETURN_URL, 'Retour', array('title' => 'Retour à la dernière page d\'application consultée'));
		$links[]=NULL;
	}
	$links[]=array(SSO_WEB_RELATIVE.'?page=apps', $_pages['apps']);
	$links[]=array(SSO_WEB_RELATIVE.'?page=settings', $_pages['settings']);
	
	if ($_sso->isSsoAdmin()) {
		$links[]=NULL;
		$links[]=array(SSO_WEB_RELATIVE.'?page=admin', $_pages['admin']);
	}
	
	$links[]=NULL;
	$links[]=array(SSO_WEB_RELATIVE.'?sso_logout=1', 'Se déconnecter');
?>
<div id="sso_menu_container">
	<div id="sso_menu">
		<div id="sso_user"><?php echo $_Input->HTML($_sso->getUserName()) ?></div>
		<ul>
<?php foreach($links as $link) {
		if ($link === NULL) {?>
			<li class="separator"><hr/></li> 
<?php 	} else {
			$url = array_shift($link);
			$text = array_shift($link);
			$options = array_shift($link);
			$attrs=array();
			if (is_array($options)) {
				foreach($options as $k => $v) {
					$attrs[] = $_Input->HTML($k).'="'.$_Input->HTML($v).'"';
				}
			}?>
			<li <?php echo implode(' ', $attrs) ?>><a href="<?php echo $_Input->HTML($url) ?>"><?php echo  $_Input->HTML($text) ?></a></li>
<?php 	} ?>
<?php } ?>
		</ul>
	</div>
</div>
<?php 
} // not logged = no menu ?>