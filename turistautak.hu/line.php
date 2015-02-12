<?php 

/**
 * vonalak beolvasása és átalakítása
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

require_once('turistautak.hu/address.php');
require_once('turistautak.hu/line-attributes.php');

function line (&$nd, &$nodetags, &$ways, $bbox, $filter, $params) { 

	$where = array();
	$where[] = 'deleted=0';
					
	if ($bbox) $where[] = sprintf("MBRIntersects(LineStringFromText('LINESTRING(%1.6f %1.6f, %1.6f %1.6f)'), g)",
			$bbox[1], $bbox[0], $bbox[3], $bbox[2]); // fordítva vannak az adatbázisban: lat, lon

	if ($filter && !isset($params['line'])) {
		$where = false;

	} else if (@$params['line'] == '') {
		// mindet kérjük

	} else {

		$codes = typeFilter($params['line'], Line::getTypeArray());

		if (count($codes)) {
			$where[] = sprintf('segments.code IN (%s)', implode(', ', $codes));
		} else { // volt valami megadva, de nem találtuk meg
			return false;
		}
	}
	
	$sql = sprintf("SELECT
		segments.*,
		userinserted.member AS userinsertedname,
		usermodified.member AS usermodifiedname
		FROM segments
		LEFT JOIN geocaching.users AS userinserted ON segments.userinserted = userinserted.id
		LEFT JOIN geocaching.users AS usermodified ON segments.usermodified = usermodified.id
		WHERE %s", implode(' AND ', $where));

	$rows = array_query($sql);

	if (is_array($rows)) foreach ($rows as $myrow) {

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
			address($nd, $nodetags, $ways, $tags['Numbers'], $wkt, $tags);
		}		
	}
}
