<?php 

require('../include_general.php');
require('../include_arrays.php');
ini_set('display_errors', 1);

try {

if (!allow_download($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
	$realm = 'turistautak.hu';
	header('WWW-Authenticate: Basic realm="' . $realm . '"');
    header('HTTP/1.0 401 Unauthorized');
	exit;
}

// bounding box
if (@$_REQUEST['bbox'] == '') throw new Exception('no bbox');
$bbox = explode(',', $_REQUEST['bbox']);
if (count($bbox) != 4) throw new Exception('invalid bbox syntax');
if ($bbox[0]>=$bbox[2] || $bbox[1]>=$bbox[3]) throw new Exception('invalid bbox');

$area = ($bbox[2]-$bbox[0])*($bbox[3]-$bbox[1]);
if ($area>0.25) throw new Exception('bbox too large');

$nd = array();
$nodetags = array();

// lines
$sql = sprintf("SELECT * FROM segments
	WHERE deleted=0
	AND lon_max>=%1.6f
	AND lat_max>=%1.6f
	AND lon_min<=%1.6f
	AND lat_min<=%1.6f",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

echo "<?xml version='1.0' encoding='UTF-8'?>", "\n";
echo "<osm version='0.6' upload='false' generator='turistautak.hu'>", "\n";
echo sprintf("  <bounds minlat='%1.6f' minlon='%1.6f' maxlat='%1.6f' maxlon='%1.6f' origin='turistautak.hu' />", $bbox[1], $bbox[0], $bbox[3], $bbox[2]), "\n";

foreach ($rows as $myrow) {

	$nodes = explode("\n", trim($myrow['points']));
	$ndrefs = array();
	$nodecount = count($nodes);
	foreach ($nodes as $node_id => $node) {
		if (count($coords = explode(';', $node)) >=2) {
			$node = sprintf('%1.6f,%1.6f', $coords[0], $coords[1]);
			if (isset($nd[$node])) {
				$ref = $nd[$node];

				// ha olyan node-ba futottunk, ami már volt,
				// akkor levesszük róla a fixme=continue-t
				// mivel megtaláltuk a felmérendő út belső végét
				unset($nodetags[$ref]['fixme']);
				unset($nodetags[$ref]['noexit']);

			} else {
				$ref = str_replace('.', '', str_replace(',', '', $node));
				$nd[$node] = $ref;

				// ezt csak akkor vizsgáljuk le, ha még nem volt ez a node,
				// hiszen ha volt, akkor már nem külső vég
				if (($node_id == 0) && ($myrow['code'] == 0xd1)) $nodetags[$ref]['fixme'] = 'continue';
				if (($node_id == $nodecount-1) && ($myrow['code'] == 0xd1)) $nodetags[$ref]['fixme'] = 'continue';

				// ezt is, mert csak külső végre van értelme
				if (($node_id == 0) && ($myrow['blind'] & 1)) $nodetags[$ref]['noexit'] = 'yes';
				if (($node_id == $nodecount-1) && ($myrow['blind'] & 2)) $nodetags[$ref]['noexit'] = 'yes';

			}
			$ndrefs[] = $ref;
			
		}
	}
	
	$attr = array(
		'id' => $myrow['id'],
		'version' => '999999999',
		// '' => ,	
	);
	
	$tags = array();
	foreach ($GLOBALS['segment_attributes'] as $id => $array) {

		if (null !== $array[1]) {
			$field = $array[1];
		} else {
			$field = $id;
		}

		if (!is_null(@$myrow[$field])) {
			$tags[$id] = iconv('Windows-1250', 'UTF-8', $myrow[$field]);
		}
		
	}
	
	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], line_type($myrow['code']));
	$tags['traces'] = $myrow['tracks'];
	$tags['name'] = $myrow['Utcanev'];
	$tags['oneway'] = $myrow['dirindicator'] == '1' ? 'yes' : null;
	
	switch ($myrow['code']) {
		case 0x81:
		case 0x82:
		case 0x83:
			$tags['highway'] = 'path';
			break;

		case 0x84:
		case 0x85:
			$tags['highway'] = 'track';
			break;

		case 0x93:
		case 0x94:
			$tags['highway'] = 'residential';
			break;

		case 0xc1:
			$tags['railway'] = 'rail';
			break;

		case 0xc2:
			$tags['railway'] = 'narrow_gauge';
			break;

		case 0xc3:
			$tags['railway'] = 'tram';
			break;

	}
	
	$ways[] = array(
		'attr' => $attr,
		'nd' => $ndrefs,
		'tags' => $tags,
	);
		
}

foreach ($nd as $node => $ref) {
	list($lat, $lon) = explode(',', $node);
	$attributes = sprintf('id="%s" lat="%1.6f" lon="%1.6f" version="999999999"', $ref, $lat, $lon);
	if (!isset($nodetags[$ref])) {
		echo sprintf('<node %s />', $attributes), "\n";
	} else {
		echo sprintf('<node %s>', $attributes), "\n";
		foreach ($nodetags[$ref] as $k => $v) {
			if (@$v == '') continue;
			echo sprintf("<tag k='%s' v='%s' />", htmlspecialchars($k), htmlspecialchars($v)), "\n";
		}
		echo '</node>', "\n";
	}
}

foreach ($ways as $way) {
	$attrs = array();
	foreach ($way['attr'] as $k => $v) {
		$attrs[] = sprintf("%s='%s'", $k, htmlspecialchars($v));
	}
	echo sprintf('<way %s >', implode(' ', $attrs)), "\n";
	foreach ($way['nd'] as $ref) {
		echo sprintf("<nd ref='%s' />", $ref), "\n";
	}
	foreach ($way['tags'] as $k => $v) {
		if (@$v == '') continue;
		echo sprintf("<tag k='%s' v='%s' />", htmlspecialchars($k), htmlspecialchars($v)), "\n";
	}
	echo '</way>', "\n";
	
}

echo '</osm>', "\n";

} catch (Exception $e) {

	header('HTTP/1.1 400 Bad Request');
	echo $e->getMessage();	

}

function allow_download ($user, $password) {

	if ($user == '') return false;
	if ($password == '') return false;

	$cryptpass = substr(crypt(strtolower($password), PASSWORD_SALT), 2);
	$sql_user = "SELECT id, userpasswd, uids, user_ids, allow_turistautak_region_download FROM geocaching.users WHERE member='" . addslashes($user) . "'";

	if (!$myrow_user = mysql_fetch_array(mysql_query($sql_user))) return false;
	if ($myrow_user['userpasswd'] != $cryptpass) return false;
	if ($myrow_user['allow_turistautak_region_download']) return true;
	
	$sql_rights = sprintf("SELECT COUNT(*) FROM regions_explicit WHERE user_id=%d AND allow_region_download=1", $myrow_user['id']);
	if (!simple_query($sql_rights)) return false;
	
	return true;

}

function line_type ($code) {

	$codes = array(
		0x0000 => 'nullás kód, általában elfelejtett típus',
		0x0081 => 'csapás',
		0x0082 => 'ösvény',
		0x0083 => 'gyalogút',
		0x0084 => 'szekérút',
		0x0085 => 'földút',
		0x0086 => 'burkolatlan utca',
		0x0087 => 'makadámút',
		0x0091 => 'burkolt gyalogút',
		0x0092 => 'kerékpárút',
		0x0093 => 'utca',
		0x0094 => 'kiemelt utca',
		0x0095 => 'országút',
		0x0096 => 'másodrendű főút',
		0x0097 => 'elsőrendű főút',
		0x0098 => 'autóút',
		0x0099 => 'autópálya',
		0x009a => 'erdei aszfalt',
		0x009b => 'egyéb közút',
		0x00a1 => 'lehajtó',
		0x00a2 => 'körforgalom',
		0x00a3 => 'lépcső',
		0x00a4 => 'kifutópálya',
		0x00b1 => 'folyó',
		0x00b2 => 'patak',
		0x00b3 => 'időszakos patak',
		0x00b4 => 'komp',
		0x00c1 => 'vasút',
		0x00c2 => 'kisvasút',
		0x00c3 => 'villamos',
		0x00c4 => 'kerítés',
		0x00c5 => 'elektromos vezeték',
		0x00c6 => 'csővezeték',
		0x00c7 => 'kötélpálya',
		0x00d1 => 'felmérendő utak',
		0x00d2 => 'kanyarodás tiltás',
		0x00d3 => 'vízpart',
		0x00d4 => 'völgyvonal',
		0x00d5 => 'megyehatár',
		0x00d6 => 'országhatár',
		0x00d7 => 'alapszintvonal',
		0x00d8 => 'főszintvonal',
		0x00d9 => 'vastag főszintvonal',
		0x00da => 'felező szintvonal',
	);
	
	return @$codes[$code];
}
