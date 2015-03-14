<?php 

/**
 * felületek beolvasása és átalakítása
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.01.30
 *
 */

function polygon (&$nd, &$nodetags, &$ways, &$rels, $bbox, $filter, $params) { 

	$where = array();
	$where[] = 'deleted=0';
	if ($bbox) $where[] = sprintf("MBRIntersects(LineStringFromText('LINESTRING(%1.6f %1.6f, %1.6f %1.6f)'), g)",
			$bbox[1], $bbox[0], $bbox[3], $bbox[2]); // fordítva vannak az adatbázisban: lat, lon

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
				$tags['leaf_type'] = 'broadleaved';
				$tags['leaf_cycle'] = 'deciduous';
				break;

			case 0x82: // fenyves
				$tags['landuse'] = 'forest';
				$tags['leaf_type'] = 'needleleaved';
				$tags['leaf_cycle'] = 'evergreen';
				break;
			
			case 0x8b: // vegyes erdő
				$tags['landuse'] = 'forest';
				$tags['leaf_type'] = 'mixed';
				$tags['leaf_cycle'] = 'mixed';
				break;
			
			case 0x83: // fiatalos
				$tags['natural'] = 'scrub';
				$tags['landcover'] = 'trees';
				break;
			
			case 0x84: // erdőirtás
				$tags['natural'] = 'scrub';
				$tags['man_made'] = 'clearcut';
				break;
			
			case 0x85: // bokros
				$tags['natural'] = 'scrub';
				$tags['landcover'] = 'bushes';
				break;
			
			case 0x86: // szőlő
				$tags['landuse'] = 'vineyard';
				break;
			
			case 0x87: // gyümölcsös
				$tags['landuse'] = 'orchard';
				break;
				
			case 0x88: // rét
				$tags['landuse'] = 'grassland';
				break;	
			
			case 0x89: // park
				$tags['leisure'] = 'park';
				break;	
			
			case 0x8a: // szántó
				$tags['landuse'] = 'meadow';
				break;	
			
			case 0x80: // zöldfelület
				$tags['natural'] = 'undefined';
				break;	
			
			case 0x90: // víz
			case 0x91: // tenger
				$tags['natural'] = 'water';
			
			case 0x92: // tó
				$tags['natural'] = 'water';
				$tags['water'] = 'lake';
			
			case 0x93: // folyó
				$tags['natural'] = 'water';
				$tags['water'] = 'river';
				break;
				
			case 0x94: // mocsár
				$tags['natural'] = 'wetland';
				$tags['water'] = 'swamp';
				break;
				
			case 0x95: // nádas
				$tags['natural'] = 'wetland';
				$tags['water'] = 'reedbed';
				break;
				
			case 0x96: // dagonya
				$tags['natural'] = 'mud';
				break;	
				
			case 0xa0: // település
				$tags['landuse'] = 'residential';
				break;
			
			case 0xa1: // megyeszékhely
				$tags['landuse'] = 'residential';
				$tags['place'] = 'city';
				break;
			
			case 0xa2: // nagyváros
			case 0xa3: // kisváros
				$tags['landuse'] = 'residential';
				$tags['place'] = 'town';
				break;
			
			case 0xa4: // nagyközség
			case 0xa5: // falu
				$tags['landuse'] = 'residential';
				$tags['place'] = 'village';
				break;
			
			case 0xa6: // településrész
				$tags['landuse'] = 'residential';
				$tags['place'] = 'suburb';
				break;
				
			case 0xa7: // üdülőövezet
				$tags['landuse'] = 'allotments';
				break;	

			case 0xb1: // épület
				$tags['building'] = 'yes';
				break;
			
			case 0xb2: // parkoló
				$tags['amenity'] = 'parking';
				break;

			case 0xb3: // ipari terület
				$tags['landuse'] = 'industrial';
				break;
				
			case 0xb4: // bevásárlóközpont
				$tags['shop'] = 'supermarket';
				break;	
				
			case 0xb5: // kifutópálya
				$tags['aeroway'] = 'runway';
				break;		
				
			case 0xb6: // sípálya
				$tags['leisure'] = 'pitch';
				$tags['sport'] = 'skiing';
				break;		

			case 0xb7: // szánkópálya
				$tags['leisure'] = 'pitch';
				$tags['sport'] = 'tobbogan';
				break;	
				
			case 0xb8: // golfpálya
				$tags['leisure'] = 'pitch';
				$tags['sport'] = 'golf';
				break;		
				
			case 0xb9: // sportpálya
				$tags['leisure'] = 'pitch';
				break;		

			case 0xba: // temető
				$tags['landuse'] = 'cemetery';
				break;
				
			case 0xbb: // katonai terület
				$tags['landuse'] = 'military';
				break;	
				
			case 0xbc: // pályaudvar
				$tags['landuse'] = 'railway';
				break;	
				
			case 0xbd: // iskola
				$tags['amenity'] = 'school';
				break;	
				
			case 0xbe: // kórház
				$tags['amenity'] = 'hospital';
				break;	
			
			case 0xbf: // külszíni fejtés
				$tags['landuse'] = 'quarry';
				break;	
				
			case 0xb0: // mesterséges terület
				$tags['man_made'] = 'yes';
				break;		
			
			case 0xf1: // fokozottan védett terület
				$tags['boundary'] = 'protected area';
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
}
