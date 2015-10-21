<?php

/**
 * poi beolvasása és átalakítása
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.01.28
 *
 */

function poi (&$nd, &$nodetags, $bbox, $filter, $params) {

	global $poi_types_array;
	global $poi_attributes_def;

	$where = array();
	$where[] = "poi.code NOT IN (0xad02, 0xad03, 0xad04, 0xad05, 0xad06, 0xad07, 0xad08, 0xad09, 0xad0a, 0xad00)";
	$where[] = 'lat<>0 and lon<>0'; // ezek eleve értelmetlenek, ráadásul a JOSM kiakad a nullás azonosítón
	$where[] = "poi.deleted = 0";
	if ($bbox) $where[] = sprintf("MBRIntersects(LineStringFromText('LINESTRING(%1.6f %1.6f, %1.6f %1.6f)'), g)",
			$bbox[1], $bbox[0], $bbox[3], $bbox[2]); // fordítva vannak az adatbázisban: lat, lon

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

	if ($where !== false && is_array($rows)) foreach ($rows as $row) {
		$node = sprintf('%1.7f,%1.7f', $row['lat'], $row['lon']);
		if (isset($nd[$node])) {
			$ref = $nd[$node];
		} else {
			$ref = refFromNode($node);
			$nd[$node] = $ref;
		}
		$ndrefs[] = $ref;
		$nodetags[$ref] = poiTags($row);
	}
}

function poiTags ($row) {

	$tags = array(
		'Type' => sprintf('0x%02x %s', $row['code'], tr($row['typename'])),
		'Code' => $row['code'], // ezt a végén majd kivesszük, csak az OSM címkéző használja
		'Label' => tr($row['nickname']),
		'ID' => $row['id'],
		'Magassag' => $row['altitude'],
		'Letrehozta' => sprintf('%d %s', $row['owner'], tr($row['ownername'])),
		'Letrehozva' => $row['dateinserted'],
		'Modositotta' => sprintf('%d %s', $row['useruploaded'], tr($row['useruploadedname'])),
		'Modositva' => $row['dateuploaded'],
		'Leiras' => tr($row['fulldesc']),
		'Megjegyzes' => tr($row['notes']),
	);

	$attributes = array();
	foreach (explode("\n", $row['attributes']) as $attribute) {
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
	$tags['Attributes'] = $attributes;
	$tags = poiTagsOSM($tags);

	// ezeket nem adjuk ki, csak az OSM címkézőnek kellett
	unset($tags['Code']);
	unset($tags['Attributes']);
	return $tags;
}

function poiTagsOSM ($tags) {

	$tags['[----------]'] = '[----------]';
	$tags['name'] = trim(@$tags['Label']);
	$regexp = "/(?<!\.)\s?\.\.$/";
	if (preg_match($regexp, $tags['name'])) {
		$tags['name'] = preg_replace($regexp, '', $tags['name']);
		$tags['fixme'] = 'position';
	}
	$name = null;

	switch (@$tags['Code']) {

		case 0xa000: // település
			$tags['landuse'] = 'residental';
			break;

		case 0xa001: // megyeszékhely
			$tags['place'] = 'city';
			break;

		case 0xa002: // nagyváros
			$tags['place'] = 'town';
			break;

		case 0xa003: // kisváros
			$tags['place'] = 'town';
			break;

		case 0xa004: // nagyközség
			$tags['place'] = 'village';
			break;

		case 0xa005: // falu
			$tags['place'] = 'village';
			break;

		case 0xa006: // településrész
			$tags['place'] = 'suburb';
			break;

		case 0xa100: // étel/ital
			$tags['amenity'] = 'food';
			$tags['food'] = 'yes';
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

		case 0xa200: // víz
			$tags['natural'] = 'water';
			break;

		case 0xa201: // tó
			$tags['natural'] = 'water';
			$tags['water'] = 'lake';
			break;

		case 0xa202: // forrás
			$tags['natural'] = 'spring';
			break;

		case 0xa203: // időszakos forrás
			$tags['natural'] = 'spring';
			$tags['intermittent'] = 'yes';
			break;

		case 0xa204: // nem iható forrás
			$tags['natural'] = 'spring';
			$tags['drinking_water'] = 'no';
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

		case 0xa209: // gémeskút
			$tags['man_made'] = 'water_well';
			if (in_array(@$tags['Label'], array('Gémeskút'))) $name = false;
			if (preg_match('/nem i/', @$tags['Label'])) $tags['drinking_water'] = 'no';
			if (preg_match('/rom/', @$tags['Label'])) $tags['ruins'] = 'yes';
			break;

		case 0xa300: // épület
			$tags['building'] = 'yes';
			break;

		case 0xa301: // ház
			$tags['building'] = 'detached';
			break;

		case 0xa302: // múzeum
			$tags['tourism'] = 'museum';
			break;

		case 0xa303: // templom
			$tags['amenity'] = 'place_of_worship';
			$tags['building'] = 'church';
			if (preg_match('/\\b(g\\.? ?k|görög kat.*)\\b/iu', @$tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'greek_catholic';
			} else if (preg_match('/\\b(r\\.? ?k|kat\.|római kat.*)\\b/iu', @$tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'roman_catholic';
			} else if (preg_match('/\\b(református|ref\.)\\b/iu', @$tags['Label'])) {
				$tags['religion'] = 'christian';
				$tags['denomination'] = 'reformed';
			} else if (preg_match('/\\b(evangélikus|ev\.)\\b/iu', @$tags['Label'])) {
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
			$tags['historic'] = 'fort';
			break;

		case 0xa308: // kastély
			$tags['historic'] = 'castle';
			break;

		case 0xa400: // szállás
		case 0xa401: // szálloda
			$tags['tourism'] = 'hotel';
			break;

		case 0xa402: // panzió
			$tags['tourism'] = 'apartment';
			break;

		case 0xa403: // magánszállás
			$tags['tourism'] = 'guest_house';
			break;

		case 0xa404: // kemping
			$tags['tourism'] = 'camp_site';
			break;

		case 0xa405: // turistaszállás
			$tags['tourism'] = 'wilderness_hut';
			break;

		case 0xa406: // kulcsosház
			$tags['tourism'] = 'chalet';
			break;

		case 0xa500: // emlékhely
			$tags['historic'] = 'memorial';
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
			$tags['historic'] = 'monument';
			break;

		case 0xa504: // szobor
			$tags['tourism'] = 'artwork'; // ???
			break;

		case 0xa505: // temető
			$tags['landuse'] = 'cemetery';
			break;

		case 0xa506: // sír
			$tags['historic'] = 'wayside_shrine';
			break;

		case 0xa601: // alagút
			$tags['tunnel'] = 'yes';
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

		case 0xa60c: // kötélpálya
			$tags['aerialway'] = 'goods';
			break;

		case 0xa60d: // parkoló járművek veszélyben
			$tags['parking:lane:both'] = 'no_stopping';
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

		case 0xa600: // közlekedés
			$tags['amenity'] = 'traffic';
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

		case 0xa70e: // postafiókok
			$tags['amenity'] = 'post_box';
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

		case 0xa715: // kölcsönző
			$tags['amenity'] = 'rental';
			break;

		case 0xa716: // iparos
			$tags['craft'] = 'yes';
			break;

		case 0xa717: // piac
			$tags['amenity'] = 'marketplace';
			break;

		case 0xa718: // turistainformáció
			$tags['tourism'] = 'information';
			break;

		case 0xa719: // szórakozóhely
			$tags['leisure'] = 'common';
			break;

		case 0xa71a: // állatmenhely
			$tags['amenity'] = 'animal_shelter';
			break;

		case 0xa71b: // e-pont
			$tags['internet_access'] = 'yes';
			break;

		case 0xa71c: // pénzváltó
			$tags['amenity'] = 'bureau_de_change';
			break;

		case 0xa700: // szolgáltatás
			$tags['craft'] = 'yes';
			break;

		case 0xa801: // golfpálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'golf';
			break;

		case 0xa802: // focipálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'soccer';
			if (in_array(@$tags['Label'], array('Focipálya'))) $name = false;
			break;

		case 0xa803: // sípálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'skiing';
			break;

		case 0xa804: // szánkópálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'tobbogan';
			break;

		case 0xa805: // stadion
			$tags['leisure'] = 'stadium';
			break;

		case 0xa806: // teniszpálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'tennis';
			break;

		case 0xa807: // lovastanya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'equestrian';
			break;

		case 0xa808: // strand
			$tags['amenity'] = 'public_bath';
			break;

		case 0xa809: // uszoda
			$tags['sport'] = 'swimming';
			break;

		case 0xa80a: // gyógyfürdő
			$tags['amenity'] = 'spa';
			break;

		case 0xa810: // sportpálya
			$tags['leisure'] = 'pitch';
			break;

		case 0xa811: // technikai sportok
			$tags['sport'] = 'yes';
			break;

		case 0xa812: // vízi sportok
			$tags['sport'] = 'water_sports';
			break;

		case 0xa813: // jégpálya
			$tags['leisure'] = 'ice_rink';
			break;

		case 0xa814: // siklóernyős starthely
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'paragliding';
			break;

		case 0xa800: // sport
			$tags['sport'] = 'yes';
			break;

		case 0xa900: // kulturális intézmény
			$tags['amenity'] = 'arts_centre';
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

		case 0xa904: // közösségi ház
			$tags['amenity'] = 'community_centre';
			break;

		case 0xa905: // állatkert
			$tags['tourism'] = 'zoo';
			break;

		case 0xa906: // cirkusz
			$tags['amenity'] = 'theatre';
			$tags['theatre:genre'] = 'circus';
			break;

		case 0xa907: // vidámpark
			$tags['tourism'] = 'theme_park';
			break;

		case 0xa908: // látnivaló
			$tags['tourism'] = 'attraction';
			break;

		case 0xaa00: // épített tereptárgy
			// erdőhatár-jelek, leginkább fából
			if (preg_match('#^[0-9/]+$#', $tags['name'])) {
				$tags['ref'] = @$tags['name'];
				$tags['boundary'] = 'marker';
				$tags['marker'] = 'wood';
				$name = false;
			} else if ($tags['name'] == 'Harangláb') {
				$tags['man_made'] = 'campanile';
				$name = false;
			}
			break;

		case 0xaa01: // bánya
			$tags['landuse'] = 'industrial';
			$tags['man_made'] = 'adit';
			break;

		case 0xaa02: // feltárás
			$tags['landuse'] = 'quarry';
			break;

		case 0xaa03: // gyár
			$tags['man_made'] = 'works';
			$name = false;
			break;

		case 0xaa04: // katonai terület
			$tags['landuse'] = 'military';
			$name = false;
			break;

		case 0xaa05: // mezőgazdasági telep
			$tags['building'] = 'farm_auxiliary'; // ???
			if (in_array(@$tags['Label'], array(
				'Mezőgazdasági telep',
				'Állattartó telep',
				'Sertéstelep',
				'Major',
				'Tsz',
				'Tanya',
				'Mg. telep',
				))) $name = false;
			break;

		case 0xaa06: // rádiótorony
			$tags['man_made'] = 'tower';
			$tags['tower:type'] = 'communication';
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

		case 0xaa09: // szélkerék
			$tags['power'] = 'generator';
			$tags[' generator:source'] = 'wind';
			$tags[' generator:method'] = 'wind_turbine';
			$tags[' generator:output:electricity'] = 'yes';
			$name = false;
			break;

		case 0xaa0a: // esőház
			$tags['amenity'] = 'shelter';
			$name = false;
			break;

		case 0xaa0b: // híd
			// tudom, hogy az OSM-ben ezt pontra nem használják
			// viszont így fennakad az ellenőrzésen
			$tags['bridge'] = 'yes';
			if (in_array(@$tags['Label'], array('Híd'))) $name = false;
			break;

		case 0xaa0c: // információs tábla
			$tags['information'] = 'board';
			$name = false;
			break;

		case 0xaa0d: // létra
			$tags['ladder'] = 'yes';
			$name = false;
			break;

		case 0xaa0e: // kapu
			$tags['barrier'] = 'gate';
			$name = false;
			break;

		case 0xaa0f: // kilátó
			$tags['man_made'] = 'tower';
			$tags['tower:type'] = 'observation';
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

		case 0xaa15: // mérőtorony
			$tags['man_made'] = 'monitoring_station';
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

		case 0xaa18: // tanösvény-állomás
			$tags['information'] = 'board';
			$tags['board_type'] = 'nature';
			$name = false;
			break;

		case 0xaa19: // vadetető
			$tags['amenity'] = 'game_feeding';
			$name = false;
			break;

		case 0xaa2a: // km-/útjelzőkő
			$tags['highway'] = 'milestone';
			if (preg_match('/([0-9]+)/iu', @$tags['Label'], $regs)) {
				$tags['distance'] = $regs[1];
			}
			$name = false;
			break;

		case 0xaa2b: // rom
			$tags['ruins'] = 'yes';
			break;

		case 0xaa2c: // boksa
			// A boksára valójában product=charcoal kellene
			// de így jelölve mészkemence több van a térképen, mint boksa
			$tags['man_made'] = 'kiln';
			$tags['product'] = 'limestone';
			break;

		case 0xaa2d: // torony
			$tags['man_made'] = 'tower';
			break;

		case 0xaa2e: // gyaloghíd
			$tags['bridge'] = 'yes';
			$tags['foot'] = 'yes';
			$tags['vehicle'] = 'no';
			break;

		case 0xaa2f: // tornapálya
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'athletics';
			break;

		case 0xaa30: // zsilip
			$tags['natural'] = 'water';
			$tags['water'] = 'lock';
			break;

		case 0xaa31: // erdőirtás
			$tags['natural'] = 'scrub';
			$tags['man_made'] = 'clearcut';
			break;

		case 0xaa32: // olajkút
			$tags['landuse'] = 'industrial';
			$tags['man_made'] = 'petroleum_well';
			break;

		case 0xaa33: // lépcső
			$tags['highway'] = 'steps';
			break;

		case 0xaa34: // vízmű
			$tags['landuse'] = 'industrial';
			$tags['man_made'] = 'water_works';
			$name = false;
			break;

		case 0xaa35: // szennyvíztelep
			$tags['landuse'] = 'industrial';
			$tags['man_made'] = 'wastewater_plant';
			$name = false;
			break;

		case 0xaa36: // transzformátor
			$tags['power'] = 'transformer';
			if (preg_match('/([0-9]+)/', @$tags['Label'], $regs))
				$tags['ref'] = $regs[1];
			if (preg_match('/otr/iu', @$tags['Label'], $regs)) {
				$tags['power'] = 'pole';
				$tags['transformer'] = 'distribution';
			}
			$name = false;
			break;

		case 0xaa37: // játszótér
			$tags['leisure'] = 'playground';
			if (@$tags['Label'] == 'Játszótér') $name = false;
			break;

		case 0xab01: // kert
			$tags['leisure'] = 'garden';
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

		case 0xab08: // zsomboly
			$tags['natural'] = 'sinkhole';
			break;

		case 0xab09: // gyümölcsös
			$tags['natural'] = 'orchard';
			break;

		case 0xab0a: // magaslat
			$tags['natural'] = 'peak';
			$tags['ele'] = @$tags['Magassag']; // ezt más ponttípusok is megkaphatnák, melyek?
			break;

		case 0xab0b: // kilátás
			$tags['tourism'] = 'viewpoint';
			$name = false;
			break;

		case 0xab0c: // szikla
			$tags['natural'] = 'rock';
			if (@$tags['Label'] == 'Szikla') $name = false;
			break;

		case 0xab0d: // vízesés
			$tags['waterway'] = 'waterfall';
			if (@$tags['Label'] == 'Vízesés') $name = false;
			break;

		case 0xab0e: // szoros
			$tags['natural'] = 'cliff';
			if (@$tags['Label'] == 'Szoros') $name = false;
			if (@$tags['Label'] == 'Vízmosás') $name = false;
			if (@$tags['Label'] == 'Kanyon') $name = false;
			if (@$tags['Label'] == 'Szurdok') $name = false;
			if (@$tags['Label'] == 'Szurdokvölgy') $name = false;
			break;

		case 0xab0f: // veszély
			$tags['hazard'] = 'yes';
			break;

		case 0xab10: // sziklamászóhely
			$tags['leisure'] = 'pitch';
			$tags['sport'] = 'climbing';
			$name = false;
			break;

		case 0xab00: // természetes tereptárgy
			$tags['natural'] = 'yes';
			break;

		case 0xac01: // illegális szemétlerakó
			$tags['illegal:amenity'] = 'waste_disposal';
			$name = false;
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

		case 0xac00: // hulladék
			$tags['amenity'] = 'waste';
			$name = false;
			break;

		case 0xad01: // pecsételőhely
			$tags['checkpoint'] = 'hiking';
			$tags['checkpoint:type'] = 'stamp';
			break;

		case 0xae01: // névrajz
			$tags['place'] = 'locality';
			break;

		case 0xae02: // tájegység
			$tags['place'] = 'locality';
			break;

		case 0xae06: // turistaút csomópont, szakértője: modras
			$tags['noi'] = 'yes';
			$tags['hiking'] = 'yes';
			$tags['tourism'] = 'information';
			$tags['information'] = 'route_marker';
			$tags['ele'] = @$tags['Magassag'];
			break;

		default: // minden, ami nincs a fenti listában
			$tags['landuse'] = 'fixme';
			// erre a JOSM figyelmeztetést ad, mert node nem tartalmazhat landuse címkét
	}

	if ($name === false) unset($tags['name']);
	if (isset($tags['ID'])) $tags['url'] = 'http://turistautak.hu/poi.php?id=' . $tags['ID'];

	// ha magasságot találunk a nevében, akkor azt beállítjuk ele= címkeként
	$regexp = '/\s*\(\s*([0-9.,]+)\s*m\s*\)/';
	if (preg_match($regexp, $tags['name'], $regs)) {
		$tags['ele'] = str_replace(',', '.', $regs[1]);
		$tags['name'] = trim(preg_replace($regexp, '', $tags['name']));
	}

	$tags['email'] = @$tags['POI:email'];

	if (@$tags['POI:telefon'] != '' && @$tags['POI:mobil'] != '' && $tags['POI:telefon'] != $tags['POI:mobil']) {
		$tags['phone'] = $tags['POI:telefon'] . '; ' . $tags['POI:mobil'];
	} else if (@$tags['POI:telefon'] != '') {
		$tags['phone'] = $tags['POI:telefon'];
	} else if (@$tags['POI:mobil'] != '') {
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
	if (!isset($tags['opening_hours']) &&
		isset($tags['Leiras']) &&
		preg_match('/^Nyitva ?tartás ?:?(.+)$/imu', $tags['Leiras'], $regs)) {
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

	// $tags['Attributes'] egy kivétel
	// a turistautak.hu POI állomány értelmezésének mellékterméke
	// ráadásuk nem szöveges, mint a többi címke, hanem igazi tömbként jön
	if (is_array(@$tags['Attributes'])) foreach ($tags['Attributes'] as $key => $attribute) {
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
	switch (@$tags['POI:váróhelység']) {
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
	if (@$tags['Code'] == 0xa103 || @$tags['Code'] == 0xa100) switch (@$tags['POI:étterem típusa']) {
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
	if (@$tags['Code'] == 0xad01) switch (@$tags['POI:igazolás típusa']) {
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
	if (@$tags['Code'] == 0xa400) switch (@$tags['POI:szállás típusa']) {

		case 'szállás':
		case 'szálloda':
			$tags['tourism'] = 'hotel';
			break;

		case 'panzió':
			$tags['tourism'] = 'apartment';
			break;

		case 'vendégház':
			$tags['tourism'] = 'guest_house';
			break;

		case 'turistaház':
			$tags['tourism'] = 'wilderness_hut';
			break;

		case 'kulcsosház':
			$tags['tourism'] = 'chalet';
			break;

		case 'kemping':
			$tags['tourism'] = 'camp_site';
			break;
	}

	if (@$tags['POI:díjszabás'] == 'ingyenes') $tags['fee'] = 'no';
	if (preg_match('/ingyen/i', @$tags['Label'])) $tags['fee'] = 'no'; // poi 2838

	// forrás
	$tags['source'] = 'turistautak.hu';
	return $tags;
}
