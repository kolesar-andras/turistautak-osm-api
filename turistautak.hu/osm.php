<?php 

/**
 * osm fájl kiírása
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

function osm (&$nd, &$nodetags, &$ways, &$rels, $bbox, $filter, $params) {

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
	
}

function attrs ($arr) {
	$attrs = array();
	foreach ($arr as $k => $v) {
		$attrs[] = sprintf('%s="%s"', $k, htmlspecialchars($v));
	}
	return implode(' ', $attrs);
}

function print_tags ($tags) {
	if (is_array($tags)) foreach ($tags as $k => $v) {
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
