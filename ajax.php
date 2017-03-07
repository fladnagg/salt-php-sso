<?php namespace sso;

use salt\Pagination;

include('lib/base.php');

if (!$sso->isLogged()) {
	header($Input->S->RAW->SERVER_PROTOCOL.' 401 Unauthorized', TRUE, 401);
	die();
}

if (!$sso->isSsoAdmin()) {
	header($Input->S->RAW->SERVER_PROTOCOL.' 403 Forbidden', TRUE, 403);
	die();
}

$call = \salt\first(explode('&', $Input->S->RAW->QUERY_STRING));
$offset = $Input->G->RAW->offset;

$result = array();
switch($call) {
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

echo json_encode($result);
