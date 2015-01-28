<?php

$base = '/api/0.6/';
$url = parse_url($_SERVER['REQUEST_URI']);

switch ($url['path']) {

	case '/api/capabilities':
	case $base . 'capabilities':
		require_once('capabilities.php');
		break;

	case $base . 'changesets':
		require_once('changesets.php');
		break;
		
	case $base . 'map':
		require_once('map.php');
		break;
		
	case $base . 'notes':
		require_once('notes.php');
		break;

	case $base . 'trackpoints':
		require_once('trackpoints.php');
		break;
		
	case '/api/':
		header('Content-type: text/plain; charset=UTF-8');
		echo "JOSM-ben állítsd be ezt a címet.\n";
		echo "\n";
		echo "Szerkesztés/Beállítások (F12)\n";
		echo "második fül (OSM szerverhez kapcsolódás beállításai)\n";
		echo "[ ] Alapértelmezett OSM szerver URL elérés használata (kapcsold ki)\n";
		echo "OSM szerver url: http://turistautak.hu/api\n";
		break;
		
	default:
		header('HTTP/1.0 404 Not Found');
		echo '404 Not Found';
		// file_put_contents('log', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

}

