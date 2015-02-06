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
}
