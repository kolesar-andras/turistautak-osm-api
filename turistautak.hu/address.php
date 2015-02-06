<?php

/**
 * házszámok
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.03
 *
 */

function address ($nd, $nodetags, $ways, $numbers, $wkt, $tags) {

	$távolság = 15; // házszámok a vonaltól
	$végétől = 25; // az utca végétől

	// N/A|0,O,1,17,E,2,24,8956,8956,Páka,Zala megye,Magyarország,Páka,Zala megye,Magyarország
	$parts = explode('|', numbers);

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
								$tags['ID'], ($oldal == 'bal' ? 1 : 2)),
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
	}
}
