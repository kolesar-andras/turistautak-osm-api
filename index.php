<?php

$base = '/api/0.6/';
$url = parse_url($_SERVER['REQUEST_URI']);

switch ($url['path']) {

	case '/api/capabilities':
		// echo "#"; exit;
		require_once('capabilities.php');
		break;

	case $base . 'changesets':
		require_once('changesets.php');
		break;
		
	case $base . 'map':
		require_once('map.php');
		break;
		
	default:
		header('HTTP/1.0 404 Not Found');
		echo '404 Not Found';

}

