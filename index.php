<?php

/**
 * turistautak.hu osm api
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */
 
$url = parse_url($_SERVER['REQUEST_URI']);

if (preg_match('#^/(api\.dev|api)-?([^/]*)/([0-9]+\.[0-9]+/)?(.*)$#', $url['path'], $regs)) {
	$api = $regs[1];
	$mod = $regs[2];
	$version = $regs[3];
	$request = $regs[4];

} else {
	header('HTTP/1.0 404 Not Found');
	echo '404 Not Found';
	exit;
}

if ($mod == 'osm' && !in_array($request, array('map', ''))) {
	$location = 'http://api.openstreetmap.org/api/';
	if ($version != '') $location .= $version . '/';
	if ($request != '') $location .= $request;
	if ($url['query'] != '') $location .= '?' . $url['query'];

	// header('HTTP/1.1 301 Moved Permanently');
	// header('HTTP/1.1 302 Found');
	// header('HTTP/1.1 303 See Other');
	header('HTTP/1.1 307 Temporary Redirect');
	header('Location: ' . $location);
	exit;

}

switch ($request) {

	case 'capabilities':
		require_once('capabilities.php');
		break;

	case 'changesets':
		require_once('changesets.php');
		break;
		
	case 'map':
		require_once('map.php');
		break;
		
	case 'map-dev':
		require_once('map-dev.php');
		break;
		
	case 'notes':
		require_once('notes.php');
		break;

	case 'trackpoints':
		require_once('trackpoints.php');
		break;
		
	case '':
		require_once('api.php');
		break;
		
	default:
		header('HTTP/1.0 404 Not Found');
		echo '404 Not Found';
		// file_put_contents('log', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
}

