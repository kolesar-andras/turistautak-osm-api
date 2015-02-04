<?php

/**
 * turistautak.hu osm api
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */
 
$url = parse_url($_SERVER['REQUEST_URI']);

if (preg_match('#^/(api[^/]*)/?#', $url['path'], $regs)) {
	$api = $regs[1];
} else {
	$api = null;
}

$base = sprintf('/%s/0.6/', $api);

switch ($url['path']) {

	case sprintf('/%s/capabilities', $api):
	case $base . 'capabilities':
		require_once('capabilities.php');
		break;

	case $base . 'changesets':
		require_once('changesets.php');
		break;
		
	case $base . 'map':
		require_once('map.php');
		break;
		
	case $base . 'map-dev':
		require_once('map-dev.php');
		break;
		
	case $base . 'notes':
		require_once('notes.php');
		break;

	case $base . 'trackpoints':
		require_once('trackpoints.php');
		break;
		
	case '/api/':
		require_once('api.php');
		break;
		
	default:
		header('HTTP/1.0 404 Not Found');
		echo '404 Not Found';
		// file_put_contents('log', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

}

