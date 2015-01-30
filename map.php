<?php 

require('../include_general.php');
require('../include_arrays.php');
require('../poi-type-array.inc.php');
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
mb_internal_encoding('UTF-8');

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
$ways = array();
$rels = array();
$nodetags = array();

echo "<?xml version='1.0' encoding='UTF-8'?>", "\n";
echo "<osm version='0.6' upload='false' generator='turistautak.hu'>", "\n";
echo sprintf("  <bounds minlat='%1.6f' minlon='%1.6f' maxlat='%1.6f' maxlon='%1.6f' origin='turistautak.hu' />", $bbox[1], $bbox[0], $bbox[3], $bbox[2]), "\n";

// poi
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
	WHERE poi.deleted=0
	AND poi.lon>=%1.6f
	AND poi.lat>=%1.6f
	AND poi.lon<=%1.6f
	AND poi.lat<=%1.6f
	AND poi.code NOT IN (0xad02, 0xad03, 0xad04, 0xad05, 0xad06, 0xad07, 0xad08, 0xad09, 0xad0a, 0xad00)
	",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

if (is_array($rows)) foreach ($rows as $myrow) {

	$node = sprintf('%1.6f,%1.6f', $myrow['lat'], $myrow['lon']);
	
	if (isset($nd[$node])) {
		$ref = $nd[$node];
	} else {
		$ref = str_replace('.', '', str_replace(',', '', $node));
		$nd[$node] = $ref;
	}
	$ndrefs[] = $ref;
	
	$tags = array(
		'Type' => sprintf('0x%02x %s', $myrow['code'], tr($myrow['typename'])),
		'Label' => tr($myrow['nickname']),
		'ID' => $myrow['id'],
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
	$name = null;
	
	switch (@$myrow['code']) {

		case 0xa006:
			$tags['place'] = 'suburb';
			break;

		case 0xa101:
			$tags['shop'] = 'convenience';
			break;

		case 0xa102:
			$tags['shop'] = 'mall';
			break;

		case 0xa103:
			$tags['amenity'] = 'restaurant';
			break;

		case 0xa104:
			$tags['amenity'] = 'fast_food';
			break;

		case 0xa105:
			$tags['amenity'] = 'pub';
			break;
		
		case 0xa106:
			$tags['amenity'] = 'cafe';
			break;

		case 0xa107:
			$tags['shop'] = 'confectionery';
			break;

		case 0xa108:
			$tags['craft'] = 'winery';
			break;

		case 0xa109:
			$tags['amenity'] = 'fast_food';
			break;

		case 0xa10a:
			$tags['shop'] = 'bakery';
			break;

		case 0xa10b:
			$tags['shop'] = 'greengrocer';
			break;

		case 0xa10c:
			$tags['shop'] = 'butcher';
			break;

		case 0xa202:
			$tags['natural'] = 'spring';
			break;

		case 0xa203:
			$tags['natural'] = 'spring';
			$tags['intermittent'] = 'yes';
			break;

		case 0xa205:
			$tags['amenity'] = 'drinking_water';
			$name = false;
			break;

		case 0xa206:
			$tags['disused:amenity'] = 'drinking_water';
			$name = false;
			break;

		case 0xa207:
			$tags['emergency'] = 'fire_hydrant';
			$name = false;
			break;

		case 0xa208:
			$tags['amenity'] = 'fountain';
			$name = false;
			break;

		case 0xa300:
		case 0xa301:
			$tags['building'] = 'yes';
			break;

		case 0xa302:
			$tags['tourism'] = 'museum';
			break;

		case 0xa303:
			$tags['amenity'] = 'place_of_worship';
			$tags['building'] = 'church';
			break;

		case 0xa304:
			$tags['building'] = 'chapel';
			break;

		case 0xa305:
			$tags['amenity'] = 'place_of_worship';
			$tags['religion'] = 'jewish';
			break;

		case 0xa306:
			$tags['amenity'] = 'school';
			break;

		case 0xa307:
		case 0xa308:
			$tags['historic'] = 'castle';
			break;

		case 0xa401:
			$tags['tourism'] = 'hotel';
			break;

		case 0xa400:
		case 0xa402:
		case 0xa403:
		case 0xa405:
			$tags['tourism'] = 'guest_house';
			break;

		case 0xa406:
			$tags['tourism'] = 'chalet';
			break;

		case 0xa404:
			$tags['tourism'] = 'camp_site';
			break;

		case 0xa501:
			$tags['historic'] = 'memorial';
			$tags['memorial'] = 'plaque';
			break;

		case 0xa502:
			$tags['historic'] = 'wayside_cross';
			if (in_array(@$tags['Label'], array('Kereszt', 'Feszület'))) $name = false;
			break;

		case 0xa503:
			$tags['historic'] = 'memorial';
			break;

		case 0xa504:
			$tags['tourism'] = 'artwork'; // ???
			break;

		case 0xa506:
			$tags['historic'] = 'wayside_shrine';
			break;

		case 0xa602:
			$tags['highway'] = 'bus_stop';
			break;

		case 0xa603:
			$tags['railway'] = 'tram_stop';
			break;

		case 0xa604:
		case 0xa605:
			$tags['railway'] = 'station';
			break;

		case 0xa606:
			$tags['railway'] = 'halt';
			break;

		case 0xa607:
			$tags['barrier'] = 'border_control';
			break;

		case 0xa608:
		case 0xa60a:
			$tags['amenity'] = 'ferry_terminal';
			break;

		case 0xa609:
			$tags['leisure'] = 'marina';
			break;

		case 0xa60a:
			$tags['railway'] = 'level_crossing';
			$name = false;
			break;

		case 0xa60b:
			$tags['aeroway'] = 'areodrome';
			break;

		case 0xa60e:
			$tags['highway'] = 'speed_camera';
			$name = false;
			break;

		case 0xa60f:
			$tags['amenity'] = 'bus_station';
			break;

		case 0xa610:
			$tags['railway'] = 'level_crossing';
			$name = false;
			break;

		case 0xa611:
			$tags['highway'] = 'motorway_junction';
			break;

		case 0xa612:
			$tags['amenity'] = 'taxi';
			$name = false;
			break;

		case 0xa701:
			$tags['shop'] = 'yes';
			break;

		case 0xa702:
			$tags['amenity'] = 'atm';
			break;

		case 0xa703:
			$tags['amenity'] = 'bank';
			break;

		case 0xa704:
			$tags['amenity'] = 'fuel';
			break;

		case 0xa705:
			$tags['amenity'] = 'hospital';
			break;

		case 0xa706:
			$tags['amenity'] = 'doctors';
			break;

		case 0xa707:
			$tags['amenity'] = 'pharmacy';
			break;

		case 0xa70a:
			$tags['amenity'] = 'telephone';
			$name = false;
			break;

		case 0xa70b:
			$tags['amenity'] = 'parking';
			if (@$tags['Label'] == 'Parkoló') $name = false;
			break;

		case 0xa70c:
			$tags['amenity'] = 'post_office';
			$name = false;
			break;

		case 0xa70d:
			$tags['amenity'] = 'post_box';
			$name = false;
			break;

		case 0xa708:
			$tags['office'] = 'government';
			break;

		case 0xa709:
			$tags['internet_access'] = 'wlan';
			break;

		case 0xa70f:
			$tags['amenity'] = 'police';
			break;

		case 0xa710:
			$tags['amenity'] = 'fire_station';
			break;

		case 0xa711:
			$tags['emergency'] = 'ambulance_station';
			break;

		case 0xa712:
			$tags['shop'] = 'car_repair';
			break;

		case 0xa713:
			$tags['shop'] = 'bicycle';
			break;

		case 0xa714:
			$tags['amenity'] = 'toilets';
			$name = false;
			break;

		case 0xa717:
			$tags['amenity'] = 'marketplace';
			break;

		case 0xa718:
			$tags['tourism'] = 'information';
			break;

		case 0xa71c:
			$tags['amenity'] = 'bureau_de_change';
			break;

		case 0xa806:
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'tennis';
			break;

		case 0xa809:
			$tags['sport'] = 'swimming';
			break;

		case 0xa810:
			$tags['leisure'] = 'pitch';
			break;

		case 0xa901:
			$tags['amenity'] = 'theatre';
			break;

		case 0xa902:
			$tags['amenity'] = 'cinema';
			break;

		case 0xa903:
			$tags['amenity'] = 'library';
			break;

		case 0xa905:
			$tags['tourism'] = 'zoo';
			break;

		case 0xa908:
			$tags['tourism'] = 'attraction';
			break;

		case 0xaa06:
			$tags['man_made'] = 'tower';
			$tags['tower_type'] = 'communication';
			$name = false;
			break;

		case 0xaa07:
			$tags['man_made'] = 'chimney';
			$name = false;
			break;

		case 0xaa08:
			$tags['man_made'] = 'water_tower';
			$name = false;
			break;

		case 0xaa14:
			$tags['barrier'] = 'lift_gate';
			$name = false;
			break;

		case 0xaa2a:
			$tags['highway'] = 'milestone';
			$name = false;
			break;

		case 0xaa03:
			$tags['man_made'] = 'works';
			$name = false;
			break;

		case 0xaa0a:
			$tags['amenity'] = 'shelter';
			$name = false;
			break;

		case 0xaa0c:
			$tags['information'] = 'board';
			$name = false;
			break;

		case 0xaa0e:
			$tags['barrier'] = 'gate';
			$name = false;
			break;

		case 0xaa0f:
			$tags['man_made'] = 'tower';
			$tags['tower_type'] = 'observation';
			$tags['tourism'] = 'viewpoint';
			break;

		case 0xaa10:
			$tags['amenity'] = 'hunting_stand';
			if (preg_match('/fedett/i', @$tags['Label'])) $tags['shelter'] = 'yes';
			$name = false;
			break;

		case 0xaa11:
			$tags['tourism'] = 'picnic_site';
			if (in_array(@$tags['Label'], array('Pihenőhely', 'Pihenő'))) $name = false;
			break;

		case 0xaa12:
			$tags['amenity'] = 'bench';
			if (@$tags['Label'] == 'Pad') $name = false;
			break;

		case 0xaa13:
			$tags['fireplace'] = 'yes';
			if (@$tags['Label'] == 'Tűzrakóhely') $name = false;
			break;

		case 0xaa16:
			$tags['man_made'] = 'survey_point';
			$name = false;
			break;

		case 0xaa17:
			$tags['historic'] = 'boundary_stone';
			if (@$tags['Label'] == 'Határkő') $name = false;
			break;

		case 0xaa2b:
			$tags['ruins'] = 'yes';
			break;

		case 0xaa2d:
			$tags['man_made'] = 'tower';
			break;

		case 0xaa34:
			$tags['man_made'] = 'water_works';
			$name = false;
			break;

		case 0xaa36:
			$tags['power'] = 'transformer';
			$name = false;
			break;

		case 0xaa37:
			$tags['leisure'] = 'playground';
			if (@$tags['Label'] == 'Játszótér') $name = false;
			break;

		case 0xab02:
			$tags['natural'] = 'tree';
			if (@$tags['Label'] == 'Fa') $name = false;
			break;

		case 0xab03:
			$tags['ford'] = 'yes';
			if (@$tags['Label'] == 'Gázló') $name = false;
			break;

		case 0xab05:
			$tags['natural'] = 'tree';
			break;

		case 0xab06:
			$tags['barrier'] = 'yes';
			break;

		case 0xab07:
			$tags['natural'] = 'cave_entrance';
			break;

		case 0xab0a:
			$tags['natural'] = 'peak';
			break;

		case 0xab0b:
			$tags['tourism'] = 'viewpoint';
			$name = false;
			break;

		case 0xab0c:
			$tags['natural'] = 'cliff';
			if (@$tags['Label'] == 'Szikla') $name = false;
			break;

		case 0xab0d:
			$tags['waterway'] = 'waterfall';
			if (@$tags['Label'] == 'Vízesés') $name = false;
			break;

		case 0xac02:
			$tags['amenity'] = 'recycling';
			$name = false;
			break;

		case 0xac03:
			$tags['amenity'] = 'waste_transfer_station';
			$name = false;
			break;
			
		case 0xac04:
			$tags['amenity'] = 'waste_basket';
			$name = false;
			break;

		case 0xac05:
			$tags['amenity'] = 'waste_disposal';
			$name = false;
			break;

		case 0xad01:
			$tags['checkpoint'] = 'hiking';
			$tags['checkpoint:type'] = 'stamp';
			break;
			
		case 0xae01:
		case 0xae06:
			$tags['place'] = 'locality';
			break;
						
	}
	
	if ($name !== false) $tags['name'] = preg_replace('/ \.\.$/', '', $tags['Label']);
	$tags['url'] = 'http://turistautak.hu/poi.php?id=' . $myrow['id'];
	
	$tags['email'] = @$tags['POI:email'];

	if (@$tags['POI:telefon'] != '' && $tags['POI:mobil'] != '' && $tags['POI:telefon'] != $tags['POI:mobil']) {
		$tags['phone'] = $tags['POI:telefon'] . '; ' . $tags['POI:mobil'];
	} else {
		$tags['phone'] = @$tags['POI:telefon'] != '' ? $tags['POI:telefon'] : $tags['POI:mobil'];
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
	
	// forrás
	$tags['source'] = 'turistautak.hu';

	$nodetags[$ref] = $tags;
}

// lines
$sql = sprintf("SELECT 
	segments.*,
	userinserted.member AS userinsertedname,
	usermodified.member AS usermodifiedname
	FROM segments
	LEFT JOIN geocaching.users AS userinserted ON segments.userinserted = userinserted.id
	LEFT JOIN geocaching.users AS usermodified ON segments.usermodified = usermodified.id
	WHERE deleted=0
	AND lon_max>=%1.6f
	AND lat_max>=%1.6f
	AND lon_min<=%1.6f
	AND lat_min<=%1.6f",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

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

	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], line_type($myrow['code']));

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
		case 0x81:
		case 0x82:
		case 0x83:
			$tags['highway'] = 'path';
			break;

		case 0x84:
		case 0x85:
			$tags['highway'] = 'track';
			break;

		case 0x86:
			$tags['highway'] = 'residential';
			$tags['surface'] = 'unpaved';
			break;

		case 0x87:
			$tags['highway'] = 'track';
			$tags['tracktype'] = 'grade1';
			break;

		case 0x91:
			$tags['highway'] = 'footway';
			break;

		case 0x92:
			$tags['highway'] = 'cycleway';
			break;

		case 0x93:
		case 0x94:
			$tags['highway'] = 'residential';
			break;

		case 0x95:
			$tags['highway'] = 'tertiary';
			break;

		case 0x96:
			$tags['highway'] = 'secondary';
			break;

		case 0x97:
			$tags['highway'] = 'primary';
			break;

		case 0x98:
			$tags['highway'] = 'trunk';
			break;

		case 0x99:
			$tags['highway'] = 'motorway';
			break;

		case 0x9a:
		case 0x9b:
			$tags['highway'] = 'unclassified';
			break;

		case 0xa2:
			$tags['junction'] = 'roundabout';
			break;

		case 0xa3:
			$tags['highway'] = 'steps';
			break;

		case 0xa4:
			$tags['aeroway'] = 'runway';
			break;

		case 0xb1:
			$tags['waterway'] = 'river';
			break;

		case 0xb2:
			$tags['waterway'] = 'stream';
			break;

		case 0xb3:
			$tags['waterway'] = 'stream';
			$tags['intermittent'] = 'yes';
			break;

		case 0xb4:
			$tags['route'] = 'ferry';
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

		case 0xc4:
			$tags['barrier'] = 'fence';
			break;

		case 0xc5:
			$tags['power'] = 'line';
			break;

		case 0xc6:
			$tags['man_made'] = 'pipeline';
			break;

		case 0xc7:
		case 0xc8:
		case 0xc9:
			$tags['aerialway'] = 'chair_lift';
			break;

		case 0xd3:
			$tags['natural'] = 'coastline';
			break;

		case 0xd4:
			$tags['natural'] = 'valley';
			break;

		case 0xd5:
			$tags['boundary'] = 'administrative';
			$tags['admin_level'] = '2';
			break;

		case 0xd6:
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
			
	$ways[] = array(
		'attr' => $attr,
		'nd' => $ndrefs,
		'tags' => $tags,
	);
	
	if (trim($tags['Label']) != '') {
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
					'ref' => $myrow['id'],
				)
			);
			
			$attr = array(
				'id' => sprintf('2%09d%02d', $myrow['id'], $counter),
				'version' => '999999999',
			);
			
			$rel = array(
				'attr' => $attr,
				'members' => $members,
				'tags' => $tags,
				'endnodes' => array($ndrefs[0], $ndrefs[count($ndrefs)-1]),
			);
		
			$rels[] = $rel;

		}
	}
		
}

// polygons
$sql = sprintf("SELECT 
	polygons.*,
	userinserted.member AS userinsertedname,
	usermodified.member AS usermodifiedname
	FROM polygons
	LEFT JOIN geocaching.users AS userinserted ON polygons.userinserted = userinserted.id
	LEFT JOIN geocaching.users AS usermodified ON polygons.usermodified = usermodified.id
	WHERE deleted=0
	AND lon_max>=%1.6f
	AND lat_max>=%1.6f
	AND lon_min<=%1.6f
	AND lat_min<=%1.6f",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

foreach ($rows as $myrow) {

	$nodes = explode("\n", trim($myrow['points']));
	$ndrefs = array();
	$members = array();
	$nodecount = count($nodes);
	foreach ($nodes as $node_id => $node) {
		if (count($coords = explode(';', $node)) >=2) {
			$node = sprintf('%1.6f,%1.6f', $coords[0], $coords[1]);
			$break = (int) @$coords[2];
			if ($break && count($ndrefs)) {
				// bezárjuk a vonalat
				if ($ndrefs[count($ndrefs)-1] != $ndrefs[0])
					$ndrefs[] = $ndrefs[0];
					
				$id = sprintf('%d%s',
							1000000 + $myrow['id'],
							count($members));
							
				$attr = array(
					'id' => $id,
					'version' => '999999999',
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
				$ref = str_replace('.', '', str_replace(',', '', $node));
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
			$id = sprintf('%d%s',
						1000000 + $myrow['id'],
						count($members));
						
			$attr = array(
				'id' => $id,
				'version' => '999999999',
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
		'id' => $myrow['id'] + 1000000,
		'version' => '999999999',
		// '' => ,	
	);
	
	$tags = array();

	$tags['ID'] = $myrow['id'];
	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], polygon_type($myrow['code']));
	
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
		case 0x81:
			$tags['landuse'] = 'forest';
			break;

		case 0x82:
			$tags['landuse'] = 'forest';
			$tags['leaf_type'] = 'needleleaved';
			break;
			
		case 0x85:
			$tags['natural'] = 'scrub';
			break;
			
		case 0x86:
			$tags['landuse'] = 'vineyard';
			break;
			
		case 0x87:
			$tags['landuse'] = 'orchard';
			break;
			
		case 0x90:
		case 0x91:
		case 0x92:
		case 0x93:
			$tags['natural'] = 'water';
			break;

		case 0xa0:
		case 0xa1:
		case 0xa2:
		case 0xa3:
		case 0xa4:
		case 0xa5:
		case 0xa6:
			$tags['landuse'] = 'residential';
			break;

		case 0xb2:
			$tags['amenity'] = 'parking';
			break;

		case 0xb1:
			$tags['building'] = 'yes';
			break;

		case 0xba:
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
		}	
	}

}

foreach ($nd as $node => $ref) {
	list($lat, $lon) = explode(',', $node);
	$attributes = sprintf('id="%s" lat="%1.6f" lon="%1.6f" version="999999999"', $ref, $lat, $lon);
	if (!isset($nodetags[$ref])) {
		echo sprintf('<node %s />', $attributes), "\n";
	} else {
		echo sprintf('<node %s>', $attributes), "\n";
		print_tags($nodetags[$ref]);
		echo '</node>', "\n";
	}
}

foreach ($ways as $way) {
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

function polygon_type ($code) {
	$codes = array(
		0x81 => 'erdő',
		0x82 => 'fenyves',
		0x83 => 'fiatalos',
		0x84 => 'erdőirtás',
		0x85 => 'bokros',
		0x86 => 'szőlő',
		0x87 => 'gyümölcsös',
		0x88 => 'rét',
		0x89 => 'park',
		0x8a => 'szántó',
		0x80 => 'zöldfelület',
		0x91 => 'tenger',
		0x92 => 'tó',
		0x93 => 'folyó',
		0x94 => 'mocsár',
		0x95 => 'nádas',
		0x96 => 'dagonya',
		0x90 => 'víz',
		0xa1 => 'megyeszékhely',
		0xa2 => 'nagyváros',
		0xa3 => 'kisváros',
		0xa4 => 'nagyközség',
		0xa5 => 'falu',
		0xa6 => 'településrész',
		0xa0 => 'település',
		0xb1 => 'épület',
		0xb2 => 'parkoló',
		0xb3 => 'ipari terület',
		0xb4 => 'bevásárlóközpont',
		0xb5 => 'kifutópálya',
		0xb6 => 'sípálya',
		0xb7 => 'szánkópálya',
		0xb8 => 'golfpálya',
		0xb9 => 'sportpálya',
		0xba => 'temető',
		0xbb => 'katonai terület',
		0xbc => 'pályaudvar',
		0xbd => 'iskola',
		0xbe => 'kórház',
		0xb0 => 'mesterséges terület',
		0xf1 => 'fokozottan védett terület',
		0xf2 => 'háttér',
	);
	
	return @$codes[$code];
}

function burkolat ($code) {
	
	$codes = array(
		'aszfalt' => 'asphalt',
		'rossz aszfalt' => 'asphalt',
		'beton' => 'concrete',
		'makadám' => 'compacted',
		'köves' => 'gravel',
		'kavics' => 'pebblestone',
		'homok' => 'sand',
		'föld' => 'dirt',
		'középen füves' => 'grass',
		'fű' => 'grass',

		'térkő' => 'paving_stones',
		'murva' => 'gravel',
		'kockakő' => 'cobblestone',
		'zúzottkő' => 'gravel',
		'sziklás' => 'rock',
		'fa' => 'wood',
		'gumi' => 'tartan',
		'föld k. fű' => 'grass',
		'homok k. fű' => 'grass',
		'fold' => 'dirt',
		'agyag' => 'clay',
		'kisszemcsés-zúzottkő' => 'fine_gravel',
		'kissz. zúzott' => 'fine_gravel',
		'füves' => 'grass',
		'vasbeton útpanel' => 'concrete:plates',
		'kő lépcső' => '',
		'kavics-kő' => 'gravel',
		'kő' => 'gravel',
		'terméskő, kitöltött' => 'gravel',
		'macskakő' => 'cobblestone',
		'kavicsos-köves' => 'gravel',
		'idomkő, beton térkő' => 'paving_stones',
		'Földes' => 'dirt',
		'kőzúzalék' => 'gravel',
		'fém' => 'metal',
		'földes, kavicsos' => 'dirt',
		'rossz beton' => 'concrete',
		'palló' => '',
		'beton lépcső' => 'concrete',
		'utcakő' => 'cobblestone',
		'agyagos homok' => 'sand',
		'sóderos föld' => 'dirt',
		'Földes, középen füve' => '',
		'tönkrement aszfalt' => 'asphalt',

	);
	
	return @$codes[$code];

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

function tr ($str) {
	return iconv('Windows-1250', 'UTF-8', $str);
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
