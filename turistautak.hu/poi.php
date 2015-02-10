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
			
			case 0xa209: // gémeskút
				$tags['man_made'] = 'water_well';
				if (in_array(@$tags['Label'], array('Gémeskút'))) $name = false;
				if (preg_match('/nem i/', $tags['Label'])) $tags['drinking_water'] = 'no';
				if (preg_match('/rom/', $tags['Label'])) $tags['ruins'] = 'yes';
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

			case 0xa500: // emlékhely
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

			case 0xa802: // focipálya
				$tags['leisure'] = 'pitch';
				$tags['sport'] = 'soccer';
				if (in_array(@$tags['Label'], array('Focipálya'))) $name = false;
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

			case 0xaa2e: // gyaloghíd
				$tags['bridge'] = 'yes';
				$tags['foot'] = 'yes';
				$tags['vehicle'] = 'no';
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

			case 0xab0f: // veszély
				$tags['hazard'] = 'yes';
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

			default: // minden, ami nincs a fenti listában
				$tags['landuse'] = 'fixme';
				// erre a JOSM figyelmeztetést ad, mert node nem tartalmazhat landuse címkét
		}
	
		if ($name === false) unset($tags['name']);
		$tags['url'] = 'http://turistautak.hu/poi.php?id=' . $myrow['id'];
	
		// ha magasságot találunk a nevében, akkor azt beállítjuk ele= címkeként
		$regexp = '/\s*\(\s*([0-9.,]+)\s*m\s*\)/';
		if (preg_match($regexp, $tags['name'], $regs)) {
			$tags['ele'] = str_replace(',', '.', $regs[1]);
			$tags['name'] = trim(preg_replace($regexp, '', $tags['name']));
		}

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
}
