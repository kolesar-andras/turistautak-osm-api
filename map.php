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

require_once('turistautak.hu/rights.php');
require_once('turistautak.hu/types.php');
require_once('turistautak.hu/concat.php');
require_once('turistautak.hu/surface.php');

include_once('include/postgresql.conf.php');

ini_set('display_errors', 0);
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '512M');
mb_internal_encoding('UTF-8');

$távolság = 15; // házszámok a vonaltól
$végétől = 25; // az utca végétől

try {

// ezzel adtam megjegyzéseket a típuskódokhoz a forráskódban
if (isset($_REQUEST['comment-types']) && $_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR']) comment_types();

// megnézzük, lesz-e szűrés
$filter = isset($params['poi']) || isset($params['line']) || isset($params['polygon']);

// bounding box
if ($_REQUEST['bbox'] == '') {
	if (!$filter) throw new Exception('no bbox');
	$bbox = null;
	
} else {
	$bbox = explode(',', $_REQUEST['bbox']);
	if (count($bbox) != 4) throw new Exception('invalid bbox syntax');
	if ($bbox[0]>=$bbox[2] || $bbox[1]>=$bbox[3]) throw new Exception('invalid bbox');
	foreach ($bbox as $coord) if (!is_numeric($coord)) throw new Exception('invalid bbox');

	$area = ($bbox[2]-$bbox[0])*($bbox[3]-$bbox[1]);
	if (!$filter && $area>0.25) throw new Exception('bbox too large');
}

$nd = array(); // '47.1234567,19.8765432' => '-471234567198765432'
$ways = array();
$rels = array();
$nodetags = array();

header('Content-type: text/xml; charset=utf-8');
header('Content-disposition: attachment; filename=map.osm');
echo "<?xml version='1.0' encoding='UTF-8'?>", "\n";
echo "<osm version='0.6' upload='false' generator='turistautak.hu'>", "\n";
if ($bbox) echo sprintf("  <bounds minlat='%1.7f' minlon='%1.7f' maxlat='%1.7f' maxlon='%1.7f' origin='turistautak.hu' />", $bbox[1], $bbox[0], $bbox[3], $bbox[2]), "\n";

// letöltjük az osm adatokat is
if (isset($params['osm'])) {
	$url = 'http://api.openstreetmap.org/api/0.6/map?bbox=' . implode(',', $bbox);
	$osm = file($url);
	$linecount = count($osm);
	for ($i=2; $i<$linecount-1; $i++) echo $osm[$i];
}

// poi
$where = array();
$where[] = "poi.code NOT IN (0xad02, 0xad03, 0xad04, 0xad05, 0xad06, 0xad07, 0xad08, 0xad09, 0xad0a, 0xad00)";
$where[] = "poi.deleted = 0";
if ($bbox) $where[] = sprintf("poi.lon>=%1.7f
		AND poi.lat>=%1.7f
		AND poi.lon<=%1.7f
		AND poi.lat<=%1.7f",
			$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

if ($filter && !isset($params['poi'])) {
	$where = false;

} else if (@$params['poi'] == '') {
	// mindet kérjük

} else {

	// előkészítjük a poi típustömböt
	$poi_type = array();
	foreach ($poi_types_array as $code => $def) {
		if (isset($def['nev'])) $poi_type[$code] = tr($def['nev']);
	}

	$codes = typeFilter($params['poi'], $poi_type);

	if (count($codes)) {
		$where[] = sprintf('poi.code IN (%s)', implode(', ', $codes));
	} else { // volt valami megadva, de nem találtuk meg
		$where = false;
	}
}

if ($where !== false) {
	$sql = sprintf("SELECT
		poi.*,
		poi_types.wp,
		poi_types.name AS typename,
		owner.member AS ownername,
		useruploaded.member AS useruploadedname
		FROM geocaching.poi
		LEFT JOIN geocaching.poi_types ON poi.code = poi_types.code
		LEFT JOIN geocaching.users AS owner ON poi.owner = owner.id
		LEFT JOIN geocaching.users AS useruploaded ON poi.useruploaded = useruploaded.id
		WHERE %s", implode(' AND ', $where));

	$rows = array_query($sql);
}

if ($where !== false && is_array($rows)) foreach ($rows as $myrow) {

	$node = sprintf('%1.7f,%1.7f', $myrow['lat'], $myrow['lon']);
	
	if (isset($nd[$node])) {
		$ref = $nd[$node];
	} else {
		$ref = refFromNode($node);
		$nd[$node] = $ref;
	}
	$ndrefs[] = $ref;
	
	$tags = array(
		'Type' => sprintf('0x%02x %s', $myrow['code'], tr($myrow['typename'])),
		'Label' => tr($myrow['nickname']),
		'ID' => $myrow['id'],
		'Magassag' => $myrow['altitude'],
		'Letrehozta' => sprintf('%d %s', $myrow['owner'], tr($myrow['ownername'])),
		'Letrehozva' => $myrow['dateinserted'],
		'Modositotta' => sprintf('%d %s', $myrow['useruploaded'], tr($myrow['useruploadedname'])),
		'Modositva' => $myrow['dateuploaded'],
		'ID' => $myrow['id'],
		'Leiras' => tr($myrow['fulldesc']),
		'Megjegyzes' => tr($myrow['notes']),
	);

	$attributes = array();
	foreach (explode("\n", $myrow['attributes']) as $attribute) {
		if (preg_match('/^([^=]+)=(.+)$/', $attribute, $regs)) {
			$key = tr($regs[1]);
			$value = tr($regs[2]);
			
			if (isset($poi_attributes_def[$regs[1]])) {
				$def = $poi_attributes_def[$regs[1]];
				if ($def['datatype'] == 'attributes' && $value[0] == 'A') {
					$values = explode(';', tr(trim($def['options'])));
					$attributes[$key] = array();
					for ($i=0; $i<strlen($value); $i++) {
						if ($value[$i+1] == '+') {
							$attributes[$key][] = trim($values[$i]);
						}
					}
					$value = implode('; ', $attributes[$key]);
				}
			}
			
			$tags['POI:' . $key] = $value;
		}
	}

	$tags['[----------]'] = '[----------]';
	$tags['name'] = preg_replace('/ \.\.$/', '', $tags['Label']);
	$name = null;
	
	switch (@$myrow['code']) {

		case 0xa006: // településrész
			$tags['place'] = 'suburb';
			break;

		case 0xa101: // élelmiszerbolt
			$tags['shop'] = 'convenience';
			break;

		case 0xa102: // bevásárlóközpont
			$tags['shop'] = 'mall';
			break;

		case 0xa103: // étterem
			$tags['amenity'] = 'restaurant';
			break;

		case 0xa104: // büfé
			$tags['amenity'] = 'fast_food';
			break;

		case 0xa105: // kocsma
			$tags['amenity'] = 'pub';
			break;
		
		case 0xa106: // kávézó
			$tags['amenity'] = 'cafe';
			break;

		case 0xa107: // cukrászda
			$tags['shop'] = 'confectionery';
			break;

		case 0xa108: // pincészet
			$tags['craft'] = 'winery';
			break;

		case 0xa109: // gyorsétterem
			$tags['amenity'] = 'fast_food';
			break;

		case 0xa10a: // pékség
			$tags['shop'] = 'bakery';
			break;

		case 0xa10b: // zöldség-gyümölcs
			$tags['shop'] = 'greengrocer';
			break;

		case 0xa10c: // hentes
			$tags['shop'] = 'butcher';
			break;

		case 0xa201: // tó
			$tags['natural'] = 'water';
			break;

		case 0xa202: // forrás
			$tags['natural'] = 'spring';
			break;

		case 0xa203: // időszakos forrás
			$tags['natural'] = 'spring';
			$tags['intermittent'] = 'yes';
			break;

		case 0xa205: // közkút
			$tags['amenity'] = 'drinking_water';
			$name = false;
			break;

		case 0xa206: // elzárt közkút
			$tags['disused:amenity'] = 'drinking_water';
			$name = false;
			break;

		case 0xa207: // tűzcsap
			$tags['emergency'] = 'fire_hydrant';
			$name = false;
			break;

		case 0xa208: // szökőkút
			$tags['amenity'] = 'fountain';
			$name = false;
			break;

		case 0xa300: // épület
		case 0xa301: // ház
			$tags['building'] = 'yes';
			break;

		case 0xa302: // múzeum
			$tags['tourism'] = 'museum';
			break;

		case 0xa303: // templom
			$tags['amenity'] = 'place_of_worship';
			$tags['building'] = 'church';
			if (preg_match('/\\b(g\\.? ?k|görög kat.*)\\b/iu', $tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'greek_catholic';
			} else if (preg_match('/\\b(r\\.? ?k|kat\.|római kat.*)\\b/iu', $tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'roman_catholic';
			} else if (preg_match('/\\b(református|ref\.)\\b/iu', $tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'reformed';
			} else if (preg_match('/\\b(evangélikus|ev\.)\\b/iu', $tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'lutheran';
			}
			
			break;

		case 0xa304: // kápolna
			$tags['building'] = 'chapel';
			break;

		case 0xa305: // zsinagóga
			$tags['amenity'] = 'place_of_worship';
			$tags['religion'] = 'jewish';
			break;

		case 0xa306: // iskola
			$tags['amenity'] = 'school';
			break;

		case 0xa307: // vár
		case 0xa308: // kastély
			$tags['historic'] = 'castle';
			break;

		case 0xa401: // szálloda
			$tags['tourism'] = 'hotel';
			break;

		case 0xa400: // szállás
		case 0xa402: // panzió
		case 0xa403: // magánszállás
		case 0xa405: // turistaszállás
			$tags['tourism'] = 'guest_house';
			break;

		case 0xa404: // kemping
			$tags['tourism'] = 'camp_site';
			break;

		case 0xa406: // kulcsosház
			$tags['tourism'] = 'chalet';
			break;

		case 0xa501: // emléktábla
			$tags['historic'] = 'memorial';
			$tags['memorial'] = 'plaque';
			break;

		case 0xa502: // kereszt
			$tags['historic'] = 'wayside_cross';
			if (in_array(@$tags['Label'], array('Kereszt', 'Feszület'))) $name = false;
			break;

		case 0xa503: // emlékmű
			$tags['historic'] = 'memorial';
			break;

		case 0xa504: // szobor
			$tags['tourism'] = 'artwork'; // ???
			break;

		case 0xa506: // sír
			$tags['historic'] = 'wayside_shrine';
			break;

		case 0xa602: // buszmegálló
			$tags['highway'] = 'bus_stop';
			break;

		case 0xa603: // villamosmegálló
			$tags['railway'] = 'tram_stop';
			break;

		case 0xa604: // pályaudvar
		case 0xa605: // vasútállomás
			$tags['railway'] = 'station';
			break;

		case 0xa606: // vasúti megálló
			$tags['railway'] = 'halt';
			break;

		case 0xa607: // határátkelőhely
			$tags['barrier'] = 'border_control';
			break;

		case 0xa608: // komp
		case 0xa60a: // hajóállomás
			$tags['amenity'] = 'ferry_terminal';
			break;

		case 0xa609: // kikötő
			$tags['leisure'] = 'marina';
			break;

		case 0xa60b: // repülőtér
			$tags['aeroway'] = 'areodrome';
			break;

		case 0xa60e: // traffipax
			$tags['highway'] = 'speed_camera';
			$name = false;
			break;

		case 0xa60f: // buszpályaudvar
			$tags['amenity'] = 'bus_station';
			break;

		case 0xa610: // vasúti átjáró
			$tags['railway'] = 'level_crossing';
			$name = false;
			break;

		case 0xa611: // autópálya-csomópont
			$tags['highway'] = 'motorway_junction';
			break;

		case 0xa612: // taxiállomás
			$tags['amenity'] = 'taxi';
			$name = false;
			break;

		case 0xa701: // üzlet
			$tags['shop'] = 'yes';
			break;

		case 0xa702: // bankautomata
			$tags['amenity'] = 'atm';
			break;

		case 0xa703: // bankfiók
			$tags['amenity'] = 'bank';
			break;

		case 0xa704: // benzinkút
			$tags['amenity'] = 'fuel';
			break;

		case 0xa705: // kórház
			$tags['amenity'] = 'hospital';
			break;

		case 0xa706: // orvosi rendelő
			$tags['amenity'] = 'doctors';
			if (@$tags['Label'] == 'Orvosi rendelő') $name = false;
			break;

		case 0xa707: // gyógyszertár
			$tags['amenity'] = 'pharmacy';
			if (@$tags['Label'] == 'Gyógyszertár') $name = false;
			break;

		case 0xa708: // hivatal
			$tags['office'] = 'government';
			break;

		case 0xa709: // hotspot
			$tags['internet_access'] = 'wlan';
			break;

		case 0xa70a: // nyilvános telefon
			$tags['amenity'] = 'telephone';
			if (preg_match('/^[0-9]+-/', $tags['name'])) {
				$tags['payment:telephone_cards'] = 'yes';
			} else if (preg_match('/^[0-9]+\\+/', $tags['name'])) {
				$tags['payment:coins'] = 'yes';
			}
			$tags['phone'] = preg_replace('/^([0-9]+)(\\+|-)/', '\\1 ', $tags['name']);
			if (!preg_match('/^\\+36 ?/', $tags['phone'])) {
				$tags['phone'] = '+36 ' . $tags['phone'];
			}
			$name = false;
			break;

		case 0xa70b: // parkoló
			$tags['amenity'] = 'parking';
			if (@$tags['Label'] == 'Parkoló') $name = false;
			break;

		case 0xa70c: // posta
			$tags['amenity'] = 'post_office';
			$name = false;
			break;

		case 0xa70d: // postaláda
			$tags['amenity'] = 'post_box';
			$name = false;
			break;

		case 0xa70f: // rendőrség
			if (@$tags['Label'] == 'Rendőrség') $name = false;
			$tags['amenity'] = 'police';
			break;

		case 0xa710: // tűzoltóság
			$tags['amenity'] = 'fire_station';
			break;

		case 0xa711: // mentőállomás
			$tags['emergency'] = 'ambulance_station';
			break;

		case 0xa712: // autószerviz
			$tags['shop'] = 'car_repair';
			break;

		case 0xa713: // kerékpárbolt
			$tags['shop'] = 'bicycle';
			break;

		case 0xa714: // wc
			$tags['amenity'] = 'toilets';
			$name = false;
			break;

		case 0xa717: // piac
			$tags['amenity'] = 'marketplace';
			break;

		case 0xa718: // turistainformáció
			$tags['tourism'] = 'information';
			break;

		case 0xa71c: // pénzváltó
			$tags['amenity'] = 'bureau_de_change';
			break;

		case 0xa806: // teniszpálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'tennis';
			break;

		case 0xa809: // uszoda
			$tags['sport'] = 'swimming';
			break;

		case 0xa810: // sportpálya
			$tags['leisure'] = 'pitch';
			break;

		case 0xa901: // színház
			$tags['amenity'] = 'theatre';
			break;

		case 0xa902: // mozi
			$tags['amenity'] = 'cinema';
			break;

		case 0xa903: // könyvtár
			$tags['amenity'] = 'library';
			break;

		case 0xa905: // állatkert
			$tags['tourism'] = 'zoo';
			break;

		case 0xa908: // látnivaló
			$tags['tourism'] = 'attraction';
			break;

		case 0xaa00: // épített tereptárgy
			// erdőhatár-jelek, leginkább fából
			if (preg_match('#^[0-9/]+$#', $tags['name'])) {
				$tags['ref'] = $tags['name'];
				$tags['boundary'] = 'marker';
				$tags['marker'] = 'wood';
				$name = false;
			} else if ($tags['name'] == 'Harangláb') {
				$tags['man_made'] = 'campanile';
				$name = false;
			}
			break;

		case 0xaa03: // gyár
			$tags['man_made'] = 'works';
			$name = false;
			break;

		case 0xaa06: // rádiótorony
			$tags['man_made'] = 'tower';
			$tags['tower_type'] = 'communication';
			$name = false;
			break;

		case 0xaa07: // kémény
			$tags['man_made'] = 'chimney';
			$name = false;
			break;

		case 0xaa08: // víztorony
			$tags['man_made'] = 'water_tower';
			$name = false;
			break;

		case 0xaa0a: // esőház
			$tags['amenity'] = 'shelter';
			$name = false;
			break;

		case 0xaa0c: // információs tábla
			$tags['information'] = 'board';
			$name = false;
			break;

		case 0xaa0e: // kapu
			$tags['barrier'] = 'gate';
			$name = false;
			break;

		case 0xaa0f: // kilátó
			$tags['man_made'] = 'tower';
			$tags['tower_type'] = 'observation';
			$tags['tourism'] = 'viewpoint';
			break;

		case 0xaa10: // magasles
			$tags['amenity'] = 'hunting_stand';
			if (preg_match('/fedett/i', @$tags['Label'])) $tags['shelter'] = 'yes';
			$name = false;
			break;

		case 0xaa11: // pihenőhely
			$tags['tourism'] = 'picnic_site';
			if (in_array(@$tags['Label'], array('Pihenőhely', 'Pihenő'))) $name = false;
			break;

		case 0xaa12: // pad
			$tags['amenity'] = 'bench';
			if (@$tags['Label'] == 'Pad') $name = false;
			break;

		case 0xaa13: // tűzrakóhely
			$tags['fireplace'] = 'yes';
			if (@$tags['Label'] == 'Tűzrakóhely') $name = false;
			break;

		case 0xaa14: // sorompó
			$tags['barrier'] = 'lift_gate';
			$name = false;
			break;

		case 0xaa16: // háromszögelési pont
			$tags['man_made'] = 'survey_point';
			$name = false;
			break;

		case 0xaa17: // határkő
			$tags['historic'] = 'boundary_stone';
			if (@$tags['Label'] == 'Határkő') $name = false;
			break;

		case 0xaa2a: // km-/útjelzőkő
			$tags['highway'] = 'milestone';
			if (preg_match('/([0-9]+)/iu', $tags['Label'], $regs)) {
				$tags['distance'] = $regs[1];
			}
			$name = false;
			break;

		case 0xaa2b: // rom
			$tags['ruins'] = 'yes';
			break;

		case 0xaa2d: // torony
			$tags['man_made'] = 'tower';
			break;

		case 0xaa34: // vízmű
			$tags['man_made'] = 'water_works';
			$name = false;
			break;

		case 0xaa36: // transzformátor
			$tags['power'] = 'transformer';
			if (preg_match('/([0-9]+)/', $tags['Label'], $regs))
				$tags['ref'] = $regs[1];
			if (preg_match('/otr/iu', $tags['Label'], $regs)) {
				$tags['power'] = 'pole';
				$tags['transformer'] = 'distribution';
			}
			$name = false;
			break;

		case 0xaa37: // játszótér
			$tags['leisure'] = 'playground';
			if (@$tags['Label'] == 'Játszótér') $name = false;
			break;

		case 0xab02: // fa
			$tags['natural'] = 'tree';
			if (@$tags['Label'] == 'Fa') $name = false;
			break;

		case 0xab03: // gázló
			$tags['ford'] = 'yes';
			if (@$tags['Label'] == 'Gázló') $name = false;
			break;

		case 0xab04: // dagonya
			$tags['natural'] = 'mud';
			if (@$tags['Label'] == 'Dagonya') $name = false;
			break;

		case 0xab05: // geofa
			$tags['natural'] = 'tree';
			break;

		case 0xab06: // akadály
			$tags['barrier'] = 'yes';
			break;

		case 0xab07: // barlang
			$tags['natural'] = 'cave_entrance';
			break;

		case 0xab0a: // magaslat
			$tags['natural'] = 'peak';
			$tags['ele'] = $tags['magassag']; // ezt más ponttípusok is megkaphatnák, melyek?
			break;

		case 0xab0b: // kilátás
			$tags['tourism'] = 'viewpoint';
			$name = false;
			break;

		case 0xab0c: // szikla
			$tags['natural'] = 'cliff';
			if (@$tags['Label'] == 'Szikla') $name = false;
			break;

		case 0xab0d: // vízesés
			$tags['waterway'] = 'waterfall';
			if (@$tags['Label'] == 'Vízesés') $name = false;
			break;

		case 0xac02: // szelektív hulladékgyűjtő
			$tags['amenity'] = 'recycling';
			$name = false;
			break;

		case 0xac03: // hulladéklerakó
			$tags['amenity'] = 'waste_transfer_station';
			$name = false;
			break;
			
		case 0xac04: // hulladékgyűjtő
			$tags['amenity'] = 'waste_basket';
			$name = false;
			break;

		case 0xac05: // konténer
			$tags['amenity'] = 'waste_disposal';
			$name = false;
			break;

		case 0xad01: // pecsételőhely
			$tags['checkpoint'] = 'hiking';
			$tags['checkpoint:type'] = 'stamp';
			break;
			
		case 0xae01: // névrajz
			$tags['place'] = 'locality';
			break;

		case 0xae06: // turistaút csomópont, szakértője: modras
			$tags['noi'] = 'yes';
			$tags['hiking'] = 'yes';
			$tags['tourism'] = 'information';
			$tags['information'] = 'route_marker';
			$tags['ele'] = $tags['Magassag'];
			break;
			
	}
	
	if ($name === false) unset($tags['name']);
	$tags['url'] = 'http://turistautak.hu/poi.php?id=' . $myrow['id'];
	
	$tags['email'] = @$tags['POI:email'];
	
	if (@$tags['POI:telefon'] != '' && $tags['POI:mobil'] != '' && $tags['POI:telefon'] != $tags['POI:mobil']) {
		$tags['phone'] = $tags['POI:telefon'] . '; ' . $tags['POI:mobil'];
	} else if ($tags['POI:telefon'] != '') {
		$tags['phone'] = $tags['POI:telefon'];	
	} else if ($tags['POI:mobil'] != '') {
		$tags['phone'] = $tags['POI:mobil'];
	}

	$tags['fax'] = @$tags['POI:fax'];
	$tags['website'] = @$tags['POI:web'];
	$tags['addr:postcode'] = @$tags['POI:irányítószám'];
	$tags['addr:street'] = @$tags['POI:cím'];
	$tags['opening_hours'] = @$tags['POI:nyitvatartás'];
	$tags['operator'] = @$tags['POI:hálózat'];
	$tags['gsm:LAC'] = @$tags['POI:lac'];
	$tags['gsm:cellid'] = @$tags['POI:cid'];
	$tags['gsm:cellid'] = @$tags['POI:cid'];
	$tags['internet_access:ssid'] = @$tags['POI:essid'];
	$tags['cave:ref'] = @$tags['POI:kataszteri szám'];
	
	// kivesszük a nyitva tartást a leírásból
	if (!isset($tags['opening_hours']) && preg_match('/^Nyitva ?tartás ?:?(.+)$/imu', $tags['Leiras'], $regs)) {
		$tags['opening_hours'] = trim($regs[1]);
	}
	
	// átalakítjuk a nyitva tartást osm szintaktikára
	$tags['opening_hours'] = preg_replace('/\b(H|Hét|Hétfő)\b/i', 'Mo', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(K|Ked|Kedd)\b/i', 'Tu', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(S|Sze|Szerda)\b/i', 'We', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(Cs|Csü|Csürtörtök)\b/i', 'Th', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(P|Pén|Péntek)\b/i', 'Fr', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(Sz|Szo|Szombat)\b/i', 'Sa', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(V|Vas|Vasárnap)\b/i', 'Su', $tags['opening_hours']);

	$tags['opening_hours'] = preg_replace("/(?<![0-9:])([0-9]+)(?![0-9:])/i", '\\1:00', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace("/(?<![0-9:])([0-9]:)/i", '0\\1', $tags['opening_hours']);
	
/*
étterem tulajdonságai: vegetáriánus konyha; nemdohányzó helyiség; légkondicionálás; fizetés kártyával
pihenőhely tulajdonságai: nyilvános WC; ivóvíz; szemeteskuka; kávé, tea; szendvics; meleg étel
barlang tulajdonságai: bivakhelynek megfelel; nyitott (nincs lezárva); kötél szükséges hozzá
hulladékfajták: papír; színes üveg; fehér üveg; fémpalack; PET palack; akkumulátor; fémhulladék; egyéb veszélyes hulladék
váróhelység nincs; megálló beállóval; állomás váróteremmel; pályaudvar
*/					
	
	foreach ($attributes as $key => $attribute) {
		foreach ($attribute as $value) {
			switch ($value) {
				case 'vegetáriánus konyha':
					$tags['diet:vegetarian'] = 'yes';
					break;
					
				case 'nemdohányzó helyiség':
					// ma már sehol sem lehet dohányozni
					break;

				case 'fizetés kártyával':
					$tags['payment:debit_cards'] = 'yes';
					$tags['payment:credit_cards'] = 'yes';
					break;

				case 'nyilvános WC':
					$tags['amenity'] = 'toilets';
					break;
					
				case 'ivóvíz':
					$tags['amenity'] = 'drinking_water';
					break;
					
				case 'szemeteskuka':
					$tags['amenity'] = 'waste_basket';
					break;
					
				case 'papír':
					$tags['recycling:paper'] = 'yes';
					break;
					
				case 'színes üveg':
				case 'fehér üveg':
					$tags['recycling:glass'] = 'yes';
					break;

				case 'fémpalack':
					$tags['recycling:cans'] = 'yes';
					break;
					
				case 'PET palack':
					$tags['recycling:plastic_bottles'] = 'yes';
					break;
					
				case 'akkumulátor':
					$tags['recycling:batteries'] = 'yes';
					break;

				case 'fémhulladék':
					$tags['recycling:scrap_metal'] = 'yes';
					break;

			}
		}
	}
	
	/* váróhelység: nincs; megálló beállóval; állomás váróteremmel; pályaudvar */
	switch ($tags['POI:váróhelység']) {
		case 'nincs':
			$tags['shelter'] = 'no';
			break;

		case 'megálló beállóval':
			$tags['shelter'] = 'yes';
			break;

		case 'állomás váróteremmel':
			$tags['shelter'] = 'yes';
			$tags['building'] = 'yes';
			break;

		case 'pályaudvar':
			$tags['amenity'] = 'bus_station';
			break;

	}
	
	/* étterem típusa: étterem; pizzéria; cukrászda; büfé; söröző; kocsma; teaház; presszó */
	if ($myrow['code'] == 0xa103 || $myrow['code'] == 0xa100) switch ($tags['POI:étterem típusa']) {
		case 'pizzéria':
			$tags['cuisine'] = 'pizza';
			break;

		case 'cukrászda':
			$tags['shop'] = 'confectionery';
			break;

		case 'büfé':
			$tags['amenity'] = 'fast_food';
			break;

		case 'söröző':
			$tags['amenity'] = 'pub';
			break;

		case 'kocsma':
			$tags['amenity'] = 'pub';
			break;

		case 'teaház':
			$tags['shop'] = 'tea'; // ??
			break;

		case 'presszó':
			$tags['amenity'] = 'cafe';
			break;

	}	
	
	/* igazolás típusa: bélyegző; kód; matrica; egyéb */
	/* 16395 Dezsővár 47.924417, 19.909033 */
	if ($myrow['code'] == 0xad01) switch ($tags['POI:igazolás típusa']) {
		case 'bélyegző':
			$tags['checkpoint:type'] = 'stamp';
			break;

		case 'kód':
			$tags['checkpoint:type'] = 'code';
			break;

		case 'matrica':
			$tags['checkpoint:type'] = 'sticker';
			break;
	}	

	/* szállás típusa: szálloda; panzió; vendégház; turistaház; kulcsosház; kemping */
	/* 1705 Slano 42.582633 18.209050 kemping */
	if ($myrow['code'] == 0xa400) switch ($tags['POI:szállás típusa']) {

		case 'szálloda':
			$tags['tourism'] = 'hotel';
			break;

		case 'panzió':
		case 'vendégház':
		case 'turistaház':
			$tags['tourism'] = 'guest_house';
			break;

		case 'kulcsosház':
			$tags['tourism'] = 'chalet';
			break;

		case 'kemping':
			$tags['tourism'] = 'camp_site';
			break;
	}
	
	if ($tags['POI:díjszabás'] == 'ingyenes') $tags['fee'] = 'no';
	if (preg_match('/ingyen/i', $tags['Label'])) $tags['fee'] = 'no'; // poi 2838
	
	// forrás
	$tags['source'] = 'turistautak.hu';

	$nodetags[$ref] = $tags;
}

// line
$where = array();
$where[] = 'deleted=0';
if ($bbox) $where[] = sprintf("lon_max>=%1.7f
		AND lat_max>=%1.7f
		AND lon_min<=%1.7f
		AND lat_min<=%1.7f",
			$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

if ($filter && !isset($params['line'])) {
	$where = false;

} else if (@$params['line'] == '') {
	// mindet kérjük

} else {

	$codes = typeFilter($params['line'], Line::getTypeArray());

	if (count($codes)) {
		$where[] = sprintf('segments.code IN (%s)', implode(', ', $codes));
	} else { // volt valami megadva, de nem találtuk meg
		$where = false;
	}
}

if ($where !== false) {
	$sql = sprintf("SELECT
		segments.*,
		userinserted.member AS userinsertedname,
		usermodified.member AS usermodifiedname
		FROM segments
		LEFT JOIN geocaching.users AS userinserted ON segments.userinserted = userinserted.id
		LEFT JOIN geocaching.users AS usermodified ON segments.usermodified = usermodified.id
		WHERE %s", implode(' AND ', $where));

	$rows = array_query($sql);
}

if ($where !== false && is_array($rows)) foreach ($rows as $myrow) {

	$nodes = explode("\n", trim($myrow['points']));
	$ndrefs = array();
	$wkt = array();
	$nodecount = count($nodes);
	foreach ($nodes as $node_id => $node) {
		if (count($coords = explode(';', $node)) >=2) {
			$node = sprintf('%1.7f,%1.7f', $coords[0], $coords[1]);
			if (isset($nd[$node])) {
				$ref = $nd[$node];

				// ha olyan node-ba futottunk, ami már volt,
				// akkor levesszük róla a fixme=continue-t
				// mivel megtaláltuk a felmérendő út belső végét
				unset($nodetags[$ref]['fixme']);
				unset($nodetags[$ref]['noexit']);

			} else {
				$ref = refFromNode($node);
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
			$wkt[] = sprintf('%1.7f %1.7f', $coords[1], $coords[0]);
			
		}
	}
	
	$attr = array(
		'id' => -$myrow['id'],
		// 'version' => '999999999',
	);
	
	$tags = array();

	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], Line::getNameFromCode($myrow['code']));

	foreach ($GLOBALS['segment_attributes'] as $id => $array) {

		if (null !== $array[1]) {
			$field = $array[1];
		} else {
			$field = $id;
		}

		if (!is_null(@$myrow[$field])) {
			$tags[$id] = tr($myrow[$field]);
		}
		
	}

	// felülírunk címkéket	
	$tags['Letrehozta'] = sprintf('%d %s', $myrow['userinserted'], tr($myrow['userinsertedname']));
	if (isset($tags['Modositotta']))
		$tags['Modositotta'] = sprintf('%d %s', $myrow['usermodified'], tr($myrow['usermodifiedname']));
		
	// törlünk címkéket
	unset($tags['Del']);
	unset($tags['Csatlakozik']);
	unset($tags['EmelkedesOda']);
	unset($tags['EmelkedesVissza']);
	unset($tags['Hossz']);
	unset($tags['HosszFerde']);
	unset($tags['From']);
	unset($tags['To']);

	// ezt csak akkor, ha nincs
	if (!$tags['Ivelve']) unset($tags['Ivelve']);
	if (!$tags['MindenElag']) unset($tags['MindenElag']);
	if (!$tags['DirIndicator']) unset($tags['DirIndicator']);
	if (!$tags['Zsakutca']) unset($tags['Zsakutca']);
	
	// forrás
	$tags['source'] = 'turistautak.hu';

	$tags['[----------]'] = '[----------]';

	switch ($myrow['code']) {
		case 0x0081: // csapás
		case 0x0082: // ösvény
		case 0x0083: // gyalogút
			$tags['highway'] = 'path';
			break;

		case 0x0084: // szekérút
		case 0x0085: // földút
			$tags['highway'] = 'track';
			break;

		case 0x0086: // burkolatlan utca
			$tags['highway'] = 'residential';
			$tags['surface'] = 'unpaved';
			break;

		case 0x0087: // makadámút
			$tags['highway'] = 'track';
			$tags['tracktype'] = 'grade1';
			break;

		case 0x0091: // burkolt gyalogút
			$tags['highway'] = 'footway';
			break;

		case 0x0092: // kerékpárút
			$tags['highway'] = 'cycleway';
			break;

		case 0x0093: // utca
		case 0x0094: // kiemelt utca
			$tags['highway'] = 'residential';
			break;

		case 0x0095: // országút
			$tags['highway'] = 'tertiary';
			break;

		case 0x0096: // másodrendű főút
			$tags['highway'] = 'secondary';
			break;

		case 0x0097: // elsőrendű főút
			$tags['highway'] = 'primary';
			break;

		case 0x0098: // autóút
			$tags['highway'] = 'trunk';
			break;

		case 0x0099: // autópálya
			$tags['highway'] = 'motorway';
			break;

		case 0x009a: // erdei aszfalt
		case 0x009b: // egyéb közút
			$tags['highway'] = 'unclassified';
			break;

		case 0x00a2: // körforgalom
			$tags['junction'] = 'roundabout';
			break;

		case 0x00a3: // lépcső
			$tags['highway'] = 'steps';
			break;

		case 0x00a4: // kifutópálya
			$tags['aeroway'] = 'runway';
			break;

		case 0x00b1: // folyó
			$tags['waterway'] = 'river';
			break;

		case 0x00b2: // patak
			$tags['waterway'] = 'stream';
			break;

		case 0x00b3: // időszakos patak
			$tags['waterway'] = 'stream';
			$tags['intermittent'] = 'yes';
			break;

		case 0x00b4: // komp
			$tags['route'] = 'ferry';
			break;

		case 0x00b5: // csatorna
			$tags['waterway'] = 'ditch';
			break;

		case 0x00c1: // vasút
			$tags['railway'] = 'rail';
			break;

		case 0x00c2: // kisvasút
			$tags['railway'] = 'narrow_gauge';
			break;

		case 0x00c3: // villamos
			$tags['railway'] = 'tram';
			break;

		case 0x00c4: // kerítés
			$tags['barrier'] = 'fence';
			break;

		case 0x00c5: // elektromos vezeték
			$tags['power'] = 'line';
			break;

		case 0x00c6: // csővezeték
			$tags['man_made'] = 'pipeline';
			break;

		case 0x00c7: // kötélpálya
		case 0x00c8: // 
		case 0x00c9: // 
			$tags['aerialway'] = 'chair_lift';
			break;

		case 0x00d3: // vízpart
			$tags['natural'] = 'coastline';
			break;

		case 0x00d4: // völgyvonal
			$tags['natural'] = 'valley';
			break;

		case 0x00d5: // megyehatár
			$tags['boundary'] = 'administrative';
			$tags['admin_level'] = '2';
			break;

		case 0x00d6: // országhatár
			$tags['boundary'] = 'administrative';
			$tags['admin_level'] = '6';
			break;

	}

	$tags['traces'] = @$myrow['tracks'];
	$tags['name'] = tr(trim(@$myrow['Utcanev']) != '' ? $myrow['Utcanev'] : @$myrow['Nev']);
	$tags['ref'] = tr(@$myrow['Utnev']);
	if (@$tags['junction'] != 'roundabout' && !isset($tags['waterway'])) $tags['oneway'] = @$myrow['dirindicator'] == '1' ? 'yes' : null;
	$tags['surface'] = burkolat(tr(trim(@$myrow['Burkolat'])));
	$tags['maxspeed'] = tr(trim(@$myrow['KorlatozasSebesseg']));
	
	if (preg_match('/rossz|tönkrement/', tr(trim(@$myrow['Burkolat'])))) {
		$tags['smoothness'] = 'bad';
	}

	// vannak autós-bicicklis járhatósági paraméterek vasúton, ezt nem kérjük
	if (!isset($tags['railway']) && $tags['highway'] != 'steps') {

		if ($tags['highway'] != 'footway' && $tags['highway'] != 'path' && $tags['highway'] != 'cycleway') {
			$smoothness = JarhatosagAutoval(tr(trim(@$myrow['JarhatosagAutoval'])));
			if ($smoothness != '') $tags['smoothness'] = $smoothness;
		}

		if (@$myrow['JarhatosagBiciklivel'] == 'A' &&
			($tags['highway'] == 'cycleway' || @$myrow['JarhatosagAutoval'] == '')) $tags['smoothness'] = 'good';
		if (@$myrow['JarhatosagBiciklivel'] == 'B') $tags['smoothness'] = 'bad';
		if (@$myrow['JarhatosagBiciklivel'] == 'C') $tags['smoothness'] = 'horrible';
		if (@$myrow['JarhatosagBiciklivel'] == 'D') $tags['smoothness'] = 'impassable';

		if ($tags['highway'] != 'footway' && $tags['highway'] != 'path' && $tags['highway'] != 'cycleway') {
			if (@$myrow['BehajtasAutoval'] == 'B') $tags['toll'] = 'yes';
			if (@$myrow['BehajtasAutoval'] == 'C') $tags['motor_vehicle'] = 'private';
			if (@$myrow['BehajtasAutoval'] == 'D') $tags['motor_vehicle'] = 'no';
		}

		if (@$myrow['BehajtasBiciklivel'] == 'B') $tags['toll:bicycle'] = 'yes';
		if (@$myrow['BehajtasBiciklivel'] == 'C') $tags['bicycle'] = 'private';
		if (@$myrow['BehajtasBiciklivel'] == 'D') $tags['bicycle'] = 'no';
	}
	
	$tags['maxweight'] = tr(trim(@$myrow['KorlatozasSuly']));
	$tags['maxweight'] = preg_replace("/([0-9])([a-z]+)$/i", '\1 \2', trim($tags['maxweight']));

	if ($myrow['Ivelve']) $tags['complete:curves'] = 'yes';
	if ($myrow['MindenElag']) $tags['complete:intersections'] = 'yes';
			
	$way = array(
		'attr' => $attr,
		'nd' => $ndrefs,
		'tags' => $tags,
		'endnodes' => array($ndrefs[0], $ndrefs[count($ndrefs)-1]),
	);
	$ways[] = $way;
	
	// házszámok
	if (@$tags['Numbers'] != '') {
		// N/A|0,O,1,17,E,2,24,8956,8956,Páka,Zala megye,Magyarország,Páka,Zala megye,Magyarország
		$parts = explode('|', $tags['Numbers']);

		$részek = array();
		foreach ($parts as $part) {
			$arr = explode(',', trim($part));
			$nodeindex = $arr[0];
			if (!is_numeric($nodeindex)) continue;
			if ($id) $részek[$id-1]['endnode'] = $nodeindex;
			$részek[$id]['startnode'] = $nodeindex;
			$részek[$id]['arr'] = $arr;
			$id++;
		}
		$részek[$id-1]['endnode'] = null;
		
		foreach ($részek as $id => $rész) {
		
			$arr = $rész['arr'];
			
			// értelmezzük a sort
			$házszám = array();
			
			$házszám['bal']['számozás'] = trim($arr[1]);
			$házszám['bal']['első'] = trim($arr[2]);
			$házszám['bal']['utolsó'] = trim($arr[3]);
			$házszám['jobb']['számozás'] = trim($arr[4]);
			$házszám['jobb']['első'] = trim($arr[5]);
			$házszám['jobb']['utolsó'] = trim($arr[6]);
			$házszám['bal']['irányítószám'] = trim($arr[7]);
			$házszám['jobb']['irányítószám'] = trim($arr[8]);
			$házszám['bal']['település'] = trim($arr[9]);
			$házszám['bal']['megye'] = trim($arr[10]);
			$házszám['bal']['ország'] = trim($arr[11]);
			$házszám['jobb']['település'] = trim($arr[12]);
			$házszám['jobb']['megye'] = trim($arr[13]);
			$házszám['jobb']['ország'] = trim($arr[14]);

			// felépítjük a geometriát WKT-ben
			$wktstring = sprintf('LINESTRING(%s)', implode(', ',
				array_slice($wkt,
					$rész['startnode'],
					$rész['endnode']
				)));
			
			$részek[$id] = array(
				'wkt' => $wktstring,
				'házszám' => $házszám,
			);
		}
		
		foreach ($részek as $rész) {
		
		$pg = pg_connect(PG_CONNECTION_STRING);
		$interpolation = array(
			'O' => 'odd',
			'E' => 'even',
			'B' => 'all',
		);
				
		foreach ($rész['házszám'] as $oldal => $szám) {
		
			if (!isset($interpolation[$szám['számozás']])) continue;
			if ($szám['első'] == '' && $szám['utolsó'] == '') {
				// nincs házszám
				
			} else if ($szám['első'] == $szám['utolsó'] ||
				$szám['első'] == '' ||
				$szám['utolsó'] == ''
				) {

				// egyetlen node
				$sql = sprintf("SELECT
					ST_AsText(
					ST_Transform(
					ST_Line_Interpolate_Point(
					ST_OffsetCurve(
					ST_Transform(
					ST_GeomFromText('%s',
					4326), -- GeomFromText
					3857), -- Transform
					%f), -- ST_OffsetCurve
					0.5), -- Line_Interpolate_Point
					4326) -- Transform
					) -- AsText
					AS geom
					",
						$rész['wkt'],
						($oldal == 'bal' ? 1 : -1) * $távolság
				);
				
				$result = pg_query($sql);
				$row = pg_fetch_assoc($result);
				$newgeom = $row['geom'];

				if (preg_match('/^POINT\(([^ ]+) ([^ ]+)\)$/', $newgeom, $regs)) {
				
					$node = sprintf('%1.7f,%1.7f', $regs[2], $regs[1]);
					$ref = refFromNode($node);
					$nd[$node] = $ref;
					$ndrefs[] = $ref;

					$addrtags = array(
						'addr:city' => $szám['település'],
						'addr:housenumber' => $szám['első'],
						'addr:postcode' => $szám['irányítószám'],
						'addr:street' => $tags['name'],
					);
					$nodetags[$ref] = $addrtags;
				}				

			} else {
				// interpoláció
				$sql = sprintf("SELECT
					ST_AsText(
					ST_Transform(
					ST_Line_Substring(
					ST_OffsetCurve(
					ST_Transform(
					ST_GeomFromText('%1\$s',
					4326), -- GeomFromText
					3857), -- Transform
					%2\$f), -- OffsetCurve
						%3\$f/ST_Length(
							ST_OffsetCurve(
							ST_Transform(
							ST_GeomFromText('%1\$s',
							4326), -- GeomFromText
							3857), -- Transform
							%2\$f) -- OffsetCurve
						), 
						1.0-%3\$f/ST_Length(
							ST_OffsetCurve(
							ST_Transform(
							ST_GeomFromText('%1\$s',
							4326), -- GeomFromText
							3857), -- Transform
							%2\$f) -- OffsetCurve
						)
					), -- Line_Substring
					4326) -- Transform
					) -- AsText
					AS geom
					",
						$rész['wkt'],
						($oldal == 'bal' ? 1 : -1) * $távolság,
						$végétől
				);
				
				$result = pg_query($sql);
				$row = pg_fetch_assoc($result);
				$newgeom = $row['geom'];
				
				if (preg_match('/^LINESTRING\((.+)\)$/', $newgeom, $regs)) {
					$nodes = explode(',', $regs[1]);
					if ($oldal != 'bal') $nodes = array_reverse($nodes);
					$ndrefs = array();
					$firstnode = $lastnode = null;
					foreach ($nodes as $node) {
						$coords = explode(' ', $node);
						$node = sprintf('%1.7f,%1.7f', $coords[1], $coords[0]);
						$ref = refFromNode($node);
						$nd[$node] = $ref;
						$ndrefs[] = $ref;				
						if ($firstnode === null) $firstnode = $ref;
						$lastnode = $ref;

						$ndrefs[] = $ref;
					}
					
					if ($firstnode !== null) {
						$addrtags = array(
							'addr:city' => $szám['település'],
							'addr:housenumber' => $szám['első'],
							'addr:postcode' => $szám['irányítószám'],
							'addr:street' => $tags['name'],
						);
						$nodetags[$firstnode] = $addrtags;
					}
					
					if ($lastnode !== null) {
						$addrtags['addr:housenumber'] = $szám['utolsó'];
						$nodetags[$lastnode] = $addrtags;
					}

					if (count($ndrefs)) {
						$attr = array(
							'id' => sprintf('-3%09d%02d',
								$myrow['id'], ($oldal == 'bal' ? 1 : 2)),
							// 'version' => '999999999',
						);
						$inttags = array(
							'addr:interpolation' => @$interpolation[$szám['számozás']],
						);
					
						$ways[] = array(
							'attr' => $attr,
							'nd' => $ndrefs,
							'tags' => $inttags,
						);
					}	
				}
			}
		}
		} // parts	
	}		
}

// összefűzzük a fűzhető vonalakat

if (!isset($params['noconcat'])) {

	// megnézzük, hogy a végpontokon mennyi azonos tulajdonságú vonal van
	$common = array();
	foreach ($ways as $id => $way) {
		$concatHash = md5(serialize(getConcatTags($way['tags'])));
		$common[$concatHash][$way['endnodes'][0]][] = $id;
		$common[$concatHash][$way['endnodes'][1]][] = $id;
	}

	$counter = 0;
	foreach ($common as $hash => $group) {

		// menet közben írjuk a $group tömböt, ezért élőben kell kiolvasnunk
		foreach (array_keys($group) as $node) try {
			$ids = $group[$node];
			$count = count($ids);
			if ($count < 2) {
				continue; // nincs mit fűznünk
			} else if ($count == 2) {
				$todo = array($ids); // ezt a kettőt fűzzük
			} else {
				$angle = array();
				foreach ($ids as $id) {
					$way = $ways[$id];
					$angle[$id] = getWayAngle($way, $node);
				}
				$turns = array();
				$turnids = array();
				for ($i = 0; $i < $count-1; $i++) {
					for ($j = $i+1; $j < $count; $j++) {
						$turn = 180 + $angle[$ids[$j]] - $angle[$ids[$i]];
						while ($turn < -180) $turn += 360;
						while ($turn > 180) $turn -= 360;
						$turn = abs($turn);
						$turns[] = $turn;
						$turnids[] = array($ids[$i], $ids[$j]);
					}
				}
				asort($turns);
				$todo = array();
				$volt = array();
				foreach (array_keys($turns) as $key) {
					if (isset($volt[$turnids[$key][0]])) continue;
					if (isset($volt[$turnids[$key][1]])) continue;
					$todo[] = $turnids[$key];
					$volt[$turnids[$key][0]] = true;
					$volt[$turnids[$key][1]] = true;
				}
			}
			
			foreach ($todo as $ids) {	
		
			// ellenőrizzük, nem csináltunk-e előzőleg butaságot
			if (isset($ways[$ids[0]]['deleted'])) throw new Exception('nincs 0: ' . $ways[$ids[0]]['tags']['ID']);
			if (isset($ways[$ids[1]]['deleted'])) throw new Exception('nincs 1: ' . $ways[$ids[1]]['tags']['ID']);

			$bal = $ways[$ids[0]]['nd'];
			$jobb = $ways[$ids[1]]['nd'];
	
			// megnézzük, mely végén illeszkedik az új kapcsolat
			if ($ways[$ids[1]]['endnodes'][0] == $node) {
				$nodes = $ways[$ids[1]]['nd'];
				$endnode = $ways[$ids[1]]['endnodes'][1];
				$reverse = false;
			} else if ($ways[$ids[1]]['endnodes'][1] == $node) {
				$nodes = array_reverse($ways[$ids[1]]['nd']);
				$endnode = $ways[$ids[1]]['endnodes'][0];
				$reverse = true;
			} else {
				throw new Exception('nem az van a másik kapcsolat végén, amit vártunk');
			}
	
			// önmagába záródik, nem szeretnénk szívni miatta
			if ($endnode == $node) continue;
	
			if (!is_array($nodes)) {
				throw new Exception('a nodes nem tömb');
			}

			if (!is_array($ways[$ids[0]]['nd'])) {
				throw new Exception('a tagok nem tömb');
			}

			// megnézzük, hogy a megmaradó melyik végéhez illeszkedik
			if ($ways[$ids[0]]['endnodes'][0] == $node) {
				$ways[$ids[0]]['nd'] = array_merge(array_reverse($nodes), array_slice($ways[$ids[0]]['nd'], 1));
				$ways[$ids[0]]['endnodes'][0] = $endnode;
				$ways[$ids[0]]['tags'] = mergeConcatTags($ways[$ids[1]]['tags'], $ways[$ids[0]]['tags'], !$reverse, false);

			} else if ($ways[$ids[0]]['endnodes'][1] == $node) {
				$ways[$ids[0]]['nd'] = array_merge($ways[$ids[0]]['nd'], array_slice($nodes, 1));
				$ways[$ids[0]]['endnodes'][1] = $endnode;
				$ways[$ids[0]]['tags'] = mergeConcatTags($ways[$ids[0]]['tags'], $ways[$ids[1]]['tags'], false, $reverse);

			} else {
				throw new Exception('nem az van az aktuális kapcsolat végén, amit vártunk');
			}

			// kicseréljük a megszűnő vonal hivatkozását a túlsó végen a megmaradóra
			$index = array_search($ids[1], $group[$endnode]);
			if ($index === false) throw new Exception('nincs meg a megszűnő vonal hivatkozása a másik végén levő csomópontban');
			$group[$endnode][$index] = $ids[0];

			// megjelöljük töröltként
			$ways[$ids[1]]['deleted'] = true;
	
			if (false) {		
				$nodetags[$node][sprintf('illesztés:%d.', $counter++)] = sprintf('%s [%s = %s + %s] %s',
					$hash,
					implode(', ', $ways[$ids[0]]['nd']),
					implode(', ', $bal),
					implode(', ', $jobb), 
					$endnode);
			}
			} // foreach
		} catch (Exception $e) {
			// csendben továbblépünk
			echo '<!-- way concat error: ' . $e->getMessage() . ' -->', "\n";
		}
	}
} // nonconcat

// jelzett turistautak címkézése

foreach ($ways as $way) {
	if (isset($way['deleted'])) continue;
	$tags = $way['tags'];
	if (trim($tags['Label']) == '') continue; // csak a jelzettek kaptak ilyet
	foreach (explode(' ', trim($tags['Label'])) as $counter => $jel) {

		$jel = trim($jel);
		
		if (preg_match('/^([KPSZVFE])(.*)$/iu', $jel, $regs)) {
			$szin = $regs[1];
			$forma = $regs[2];
		} else {
			$szin = '';
			$forma = $jel;
		}
		
		$szinek = array(
			'k' => 'blue',
			'p' => 'red',
			's' => 'yellow',
			'z' => 'green',
			'v' => 'purple',
			'f' => 'black',
			'e' => 'gray',
		);

		$formak = array(
			'' => array('bar', ''),
			'+' => array('cross', '+'),
			'3' => array('triangle', '▲'),
			'4' => array('rectangle', '■'),
			'q' => array('dot', '●'),
			'b' => array('arch', 'Ω'),
			'l' => array('L', '▙'),
			'c' => array('circle', '↺'),
			't' => array('T', ':T:'), // ???
		);

		$color = @$szinek[mb_strtolower($szin)];
		$symbol = @$formak[mb_strtolower($forma)];
		
		$name = isset($symbol[1]) ? ($szin . $symbol[1]) : mb_strtoupper($jel);
		$tags = array(
			'jel' => mb_strtolower($jel),
			'name' => $name,
			'network' => $forma == '' ? 'nwn' : 'lwn',
			'route' => 'hiking',
			'type' => 'route',
			'source' => 'turistautak.hu',
		);

		if ($symbol !== null) {
			$face = $symbol[0];
			$tags['osmc:symbol'] = sprintf(
				'%s:white:%s_%s',
				$color, $color, $face
			);
		}
		
		$members = array(
			array(
				'type' => 'way',
				'ref' => $way['attr']['id'],
			)
		);
		
		$attr = array(
			'id' => sprintf('-2%09d%02d', -$way['attr']['id'], $counter),
			// 'version' => '999999999',
		);
		
		$rel = array(
			'attr' => $attr,
			'members' => $members,
			'tags' => $tags,
			'endnodes' => $way['endnodes'],
		);
	
		$rels[] = $rel;

	}
}

// polygon

$where = array();
$where[] = 'deleted=0';
if ($bbox) $where[] = sprintf("lon_max>=%1.7f
		AND lat_max>=%1.7f
		AND lon_min<=%1.7f
		AND lat_min<=%1.7f",
			$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

if ($filter && !isset($params['polygon'])) {
	$where = false;

} else if (@$params['polygon'] == '') {
	// mindet kérjük

} else {

	$codes = typeFilter($params['polygon'], Polygon::getTypeArray());

	if (count($codes)) {
		$where[] = sprintf('polygons.code IN (%s)', implode(', ', $codes));
	} else { // volt valami megadva, de nem találtuk meg
		$where = false;
	}
}

if ($where !== false) {
	$sql = sprintf("SELECT 
		polygons.*,
		userinserted.member AS userinsertedname,
		usermodified.member AS usermodifiedname
		FROM polygons
		LEFT JOIN geocaching.users AS userinserted ON polygons.userinserted = userinserted.id
		LEFT JOIN geocaching.users AS usermodified ON polygons.usermodified = usermodified.id
		WHERE %s", implode(' AND ', $where));

	$rows = array_query($sql);
}

if ($where !== false && is_array($rows)) foreach ($rows as $myrow) {

	$nodes = explode("\n", trim($myrow['points']));
	$ndrefs = array();
	$members = array();
	$nodecount = count($nodes);
	foreach ($nodes as $node_id => $node) {
		if (count($coords = explode(';', $node)) >=2) {
			$node = sprintf('%1.7f,%1.7f', $coords[0], $coords[1]);
			$break = (int) @$coords[2];
			if ($break && count($ndrefs)) {
				// bezárjuk a vonalat
				if ($ndrefs[count($ndrefs)-1] != $ndrefs[0])
					$ndrefs[] = $ndrefs[0];
					
				$id = sprintf('-%d%s',
							1000000 + $myrow['id'],
							count($members));
							
				$attr = array(
					'id' => $id,
					// 'version' => '999999999',
				);

				$ways[] = array(
					'attr' => $attr,
					'nd' => $ndrefs,
					// 'tags' => $tags,
				);
				$members[] = array(
					'type' => 'way',
					'ref' => $id,
					'role' => count($members) ? 'inner' : 'outer',
				);
				$ndrefs = array();
			}
			if (isset($nd[$node])) {
				$ref = $nd[$node];
			} else {
				$ref = refFromNode($node);
				$nd[$node] = $ref;
			}
			$ndrefs[] = $ref;
			
		}
	}

	if (count($ndrefs)) {
		// bezárjuk a vonalat
		if ($ndrefs[count($ndrefs)-1] != $ndrefs[0])
			$ndrefs[] = $ndrefs[0];
		
		// ha többrészes, akkor ezt a részt is mentjük
		if (count($members)) {
			$id = sprintf('-%d%s',
						1000000 + $myrow['id'],
						count($members));
						
			$attr = array(
				'id' => $id,
				// 'version' => '999999999',
			);
				
			$ways[] = array(
				'attr' => $attr,
				'nd' => $ndrefs,
				// 'tags' => $tags,
			);

			$members[] = array(
				'type' => 'way',
				'ref' => $id,
				'role' => count($members) ? 'inner' : 'outer',
			);
			$ndrefs = array();
		}
	}
	
	$attr = array(
		'id' => -($myrow['id'] + 1000000),
		// 'version' => '999999999',
	);
	
	$tags = array();

	$tags['ID'] = $myrow['id'];
	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], Polygon::getNameFromCode($myrow['code']));
	
	$tags['Label'] = tr($myrow['label']);
	$tags['Letrehozva'] = $myrow['dateinserted'];
	$tags['Modositva'] = $myrow['datemodified'];
	$tags['Letrehozta'] = sprintf('%d %s', $myrow['userinserted'], tr($myrow['userinsertedname']));
	if (isset($tags['Modositotta']))
		$tags['Modositotta'] = sprintf('%d %s', $myrow['usermodified'], tr($myrow['usermodifiedname']));
		
	// forrás
	$tags['source'] = 'turistautak.hu';
	$tags['name'] = $tags['Label'];

	$tags['[----------]'] = '[----------]';

	switch ($myrow['code']) {
		case 0x81: // erdő
			$tags['landuse'] = 'forest';
			break;

		case 0x82: // fenyves
			$tags['landuse'] = 'forest';
			$tags['leaf_type'] = 'needleleaved';
			break;
			
		case 0x85: // bokros
			$tags['natural'] = 'scrub';
			break;
			
		case 0x86: // szőlő
			$tags['landuse'] = 'vineyard';
			break;
			
		case 0x87: // gyümölcsös
			$tags['landuse'] = 'orchard';
			break;
			
		case 0x90: // víz
		case 0x91: // tenger
		case 0x92: // tó
		case 0x93: // folyó
			$tags['natural'] = 'water';
			break;

		case 0xa0: // település
		case 0xa1: // megyeszékhely
		case 0xa2: // nagyváros
		case 0xa3: // kisváros
		case 0xa4: // nagyközség
		case 0xa5: // falu
		case 0xa6: // településrész
			$tags['landuse'] = 'residential';
			break;

		case 0xb2: // parkoló
			$tags['amenity'] = 'parking';
			break;

		case 0xb1: // épület
			$tags['building'] = 'yes';
			break;

		case 0xba: // temető
			$tags['landuse'] = 'cemetery';
			break;

	}

	$tags['name'] = tr(trim(@$myrow['Label']));
	
	if (count($members)) {
		$tags['type'] = 'multipolygon';
		$rels[] = array(
			'attr' => $attr,
			'members' => $members,
			'tags' => $tags,
		);

	} else {

		$ways[] = array(
			'attr' => $attr,
			'nd' => $ndrefs,
			'tags' => $tags,
		);
	}
		
}

// összefűzzük a jelzett turistautakat
$common = array();
foreach ($rels as $id => $rel) {
	if (!isset($rel['endnodes'])) continue; // csak a jelzés-kapcsolatok érdekelnek
	$common[$rel['tags']['jel']][$rel['endnodes'][0]][] = $id;
	$common[$rel['tags']['jel']][$rel['endnodes'][1]][] = $id;
}

$count = 0;
foreach ($common as $jel => $group) {

	// menet közben írjuk a $group tömböt, ezért élőben kell kiolvasnunk
	foreach (array_keys($group) as $node) { 
	
		try {
			$ids = $group[$node];
			if (count($ids) != 2) continue;

			// ellenőrizzük, nem csináltunk-e előzőleg butaságot
			if (isset($rels[$ids[0]]['deleted'])) throw new Exception('nincs 0');
			if (isset($rels[$ids[1]]['deleted'])) throw new Exception('nincs 1');

			$bal = refs($rels[$ids[0]]['members']);
			$jobb = refs($rels[$ids[1]]['members']);
		
			// megnézzük, mely végén illeszkedik az új kapcsolat
			if ($rels[$ids[1]]['endnodes'][0] == $node) {
				$members = $rels[$ids[1]]['members'];
				$endnode = $rels[$ids[1]]['endnodes'][1];
			} else if ($rels[$ids[1]]['endnodes'][1] == $node) {
				$members = array_reverse($rels[$ids[1]]['members']);
				$endnode = $rels[$ids[1]]['endnodes'][0];
			} else {
				throw new Exception('nem az van a másik kapcsolat végén, amit vártunk');
			}
		
			// önmagába záródik, nem szeretnénk szívni miatta
			if ($endnode == $node) continue;
		
			if (!is_array($members)) {
				throw new Exception('a members nem tömb');
			}

			if (!is_array($rels[$ids[0]]['members'])) {
				throw new Exception('a tagok nem tömb');
			}

			// megnézzük, hogy a megmaradó melyik végéhez illeszkedik
			if ($rels[$ids[0]]['endnodes'][0] == $node) {
				$rels[$ids[0]]['members'] = array_merge(array_reverse($members), $rels[$ids[0]]['members']);
				$rels[$ids[0]]['endnodes'][0] = $endnode;
			} else if ($rels[$ids[0]]['endnodes'][1] == $node) {
				$rels[$ids[0]]['members'] = array_merge($rels[$ids[0]]['members'], $members);
				$rels[$ids[0]]['endnodes'][1] = $endnode;
			} else {
				throw new Exception('nem az van az aktuális kapcsolat végén, amit vártunk');
			}

			// kicseréljük a megszűnő kapcsolat hivatkozását a túlsó végen a megmaradóra
			if (count($group[$endnode]) == 2) {
				if ($group[$endnode][0] == $ids[1]) {
					$group[$endnode][0] = $ids[0];
				} else if ($group[$endnode][1] == $ids[1]) {
					$group[$endnode][1] = $ids[0];
				} else {
					throw new Exception('nem az van a csomópontban, amit vártunk');
				}	
			}
	
			// megjelöljük töröltként
			$rels[$ids[1]]['deleted'] = true;
		
			if (false) {		
				$nodetags[$node][sprintf('illesztés:%d.', $count++)] = sprintf('%s [%s = %s + %s] %s',
					$jel,
					implode(', ', refs($rels[$ids[0]]['members'])),
					implode(', ', $bal),
					implode(', ', $jobb), 
					$endnode);
			}

		} catch (Exception $e) {
			// csendben továbblépünk
			echo '<!-- ' . $e->getMessage . ' -->', "\n";
		}	
	}

}

foreach ($nd as $node => $ref) {
	list($lat, $lon) = explode(',', $node);
	$attrs = array(
		'id' => $ref,
		'lat' => sprintf('%1.7f', $lat),
		'lon' => sprintf('%1.7f', $lon),
	);
	$attributes = attrs($attrs);
	if (!isset($nodetags[$ref])) {
		echo sprintf('<node %s />', $attributes), "\n";
	} else {
		echo sprintf('<node %s>', $attributes), "\n";
		print_tags($nodetags[$ref]);
		echo '</node>', "\n";
	}
}

foreach ($ways as $way) {
	if (isset($way['deleted'])) continue;
	echo sprintf('<way %s >', attrs($way['attr'])), "\n";
	foreach ($way['nd'] as $ref) {
		echo sprintf('<nd ref="%s" />', $ref), "\n";
	}
	print_tags($way['tags']);
	echo '</way>', "\n";
	
}

foreach ($rels as $rel) {
	if (isset($rel['deleted'])) continue;
	echo sprintf('<relation %s >', attrs($rel['attr'])), "\n";
	foreach ($rel['members'] as $member) {
		echo sprintf('<member %s />', attrs($member)), "\n";
	}
	print_tags($rel['tags']);
	echo '</relation>', "\n";
}

echo '</osm>', "\n";

} catch (Exception $e) {

	header('HTTP/1.1 400 Bad Request');
	echo $e->getMessage();	

}

function JarhatosagAutoval ($code) {
	
	$codes = array(
		'A' => 'good',
		'B' => 'bad',
		'C' => 'very_bad',
		'D' => 'very_horrible',
	);
	
	return @$codes[$code];

}

function attrs ($arr) {
	$attrs = array();
	foreach ($arr as $k => $v) {
		$attrs[] = sprintf('%s="%s"', $k, htmlspecialchars($v));
	}
	return implode(' ', $attrs);
}

function print_tags ($tags) {
	foreach ($tags as $k => $v) {
		if (trim(@$v) == '') continue;
		echo sprintf('<tag k="%s" v="%s" />', htmlspecialchars(trim($k)), htmlspecialchars(trim($v))), "\n";
	}
}

function refs ($arr) {
	$out = array();
	foreach ($arr as $item) {
		$out[] = $item['ref'];
	}
	return $out;
}


function refFromNode ($node) {

	return '-' . str_replace('.', '', str_replace(',', '', $node));

}

function nodeFromRef ($ref) {

	// lassú
	// return array_search($nodes[$ref], $nd);
	
	// nagyon csúnya átmeneti gyors megoldás
	return sprintf('%s.%s,%s.%s',
		substr($ref, 1, 2),
		substr($ref, 3, 7),
		substr($ref, 10, 2),
		substr($ref, 12, 7));
		
}

function typeFilter ($types, $names) {

	// előkészítjük a szűrőfeltételt
	if (!is_array($types)) $types = array($types);

	// értelmezzük a szűréseket
	$codes = array();
	foreach ($types as $type) {
		if (is_numeric($type)) {
			$codes[] = $type;
		} else if (preg_match('/^0x([0-9a-f]+)$/i', $type, $regs)) {
			$codes[] = hexdec($regs[1]);
		} else {
			$code = array_search($type, $names);
			if ($code !== false && is_numeric($code)) $codes[] = $code;
		}
	}
	
	return $codes;
}

