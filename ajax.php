<?php namespace sso;

use salt\Pagination;

include('lib/base.php');

$notAdminCalls = array('user');
$call = \salt\first(explode('&', $Input->S->RAW->QUERY_STRING));

if (!$sso->isLogged()) {
	header($Input->S->RAW->SERVER_PROTOCOL.' 401 Unauthorized', TRUE, 401);
	die();
}

if (!$sso->isSsoAdmin() && !in_array($call, $notAdminCalls)) {
	header($Input->S->RAW->SERVER_PROTOCOL.' 403 Forbidden', TRUE, 403);
	die();
}

$offset = $Input->G->RAW->offset;

$result = array();
switch($call) {
	case 'user' :
		$search = $Input->G->RAW->term;
		$methods = NULL;
		if (is_array($search)) {
			$methods = $sso->authMethods(NULL);
		} else {
			$methods = $sso->authMethods($search);
		}
		foreach($methods as $method) {
			$usersData = $method->searchUser($search);
			if (count($usersData) > 0) {
				$result = $usersData;
				break;
			}
		}
	break;
	case 'users' :
		$pagination = new Pagination($offset, SSO_MAX_AUTOCOMPLETE_ELEMENTS);
		$data = SsoUser::search(array('name' => $Input->G->RAW->term), $pagination);

		if ($offset > 0) {
			$result[]=array('label'=>'<< Précédents ('.$offset.')', 'offset' => ($offset-$pagination->getLimit()));
		}
		foreach($data->data as $row) {
			$result[]=array('label'=>$row->name, 'value' => $row->id);
		}
		$more = $pagination->getCount()-($offset+$pagination->getLimit());
		if ($more > 0) {
			$result[]=array('label'=>'>> Suivants ('.$more.')', 'offset' => ($offset+$pagination->getLimit()));
		}
	break;
	case 'applis' :
		$pagination = new Pagination($offset, SSO_MAX_AUTOCOMPLETE_ELEMENTS);
		$data = SsoAppli::search(array('name' => $Input->G->RAW->term), $pagination);
	
		if ($offset > 0) {
			$result[]=array('label'=>'<< Précédents ('.$offset.')', 'offset' => ($offset-$pagination->getLimit()));
		}
		foreach($data->data as $row) {
			$result[]=array('label'=>$row->name, 'value' => $row->id);
		}
		$more = $pagination->getCount()-($offset+$pagination->getLimit());
		if ($more > 0) {
			$result[]=array('label'=>'>> Suivants ('.$more.')', 'offset' => ($offset+$pagination->getLimit()));
		}
	break;
	default :
		header($Input->S->RAW->SERVER_PROTOCOL.' Bad Request', true, 400);
		die();
}

header('Content-Type: application/json; charset='.SSO_CHARSET);

echo json_encode($result);
