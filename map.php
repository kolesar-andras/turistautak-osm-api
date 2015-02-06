<?php 

/**
 * térképi objektumok letöltése osm fájlként
 *
 * 2015.02.01 előtt csak hitelesítéssel és jogosultsággal működött
 * az ODbL nyitással ezt kikapcsoltam
 *
 * közvetlenül a MySQL adatbázist olvassa
 * felhasznál php összetevőket is a turistautak.hu-ból
 * például a beállításokat, típusdefiníciós tömböket
 *
 * @todo ötletek
 * geometriai index használata (sajnos a táblák nem MyISAM-ok)
 * objektum-orientált megvalósítás, szétválasztás részekre
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

require_once('turistautak.hu/poi.php');
require_once('turistautak.hu/line.php');
require_once('turistautak.hu/polygon.php');

require_once('turistautak.hu/osm.php');

require_once('turistautak.hu/filter.php');
require_once('turistautak.hu/types.php');
require_once('turistautak.hu/trailmarks.php');
require_once('turistautak.hu/concat-ways.php');
require_once('turistautak.hu/concat-trailmarks.php');

include_once('include/postgresql.conf.php');

ini_set('display_errors', 0);
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '512M');
mb_internal_encoding('UTF-8');

try {

	// ezzel adtam megjegyzéseket a típuskódokhoz a forráskódban
	if (isset($_REQUEST['comment-types']) && $_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR']) comment_types();

	// megnézzük, lesz-e szűrés
	$filter = isset($params['poi']) || isset($params['line']) || isset($params['polygon']);
	$bbox = bbox(@$_REQUEST['bbox'], $filter);

	$nd = array(); // '47.1234567,19.8765432' => '-471234567198765432'
	$ways = array();
	$rels = array();
	$nodetags = array();

	// beolvassuk a pontkat
	poi($nd, $nodetags, $bbox, $filter, $params);

	// beolvassuk a vonalakat
	line($nd, $nodetags, $ways, $bbox, $filter, $params);

	// összefűzzük a fűzhető vonalakat
	if (!isset($params['noconcat'])) concat_ways($ways);

	// beolvassuk a felületeket
	polygon($nd, $nodetags, $ways, $rels, $bbox, $filter, $params);

	// feltesszük a jelzett turistautakat
	if (!isset($params['notrailmarks'])) trailmarks($ways, $rels);

	// összefűzzük a jelzett turistautakat
	concat_trailmarks($rels);

	// kiírjuk osm fájlba
	osm($nd, $nodetags, $ways, $rels, $bbox, $filter, $params);

} catch (Exception $e) {

	header('HTTP/1.1 400 Bad Request');
	echo $e->getMessage();	

}

