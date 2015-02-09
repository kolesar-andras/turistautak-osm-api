<?php 

/**
 * turistautak.hu vonalak tulajdonságainak egyesítése
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2015.02.05
 *
 */

function getConcatTags ($tags) {
	$concatTags = array();
	foreach ($tags as $k => $v) {
		if (preg_match('/^[a-z]|^Label$|^Turamozgalom$/', $k)) {
			$concatTags[$k] = $v;
		}
	}
	return $concatTags;
}

function mergeConcatTags ($to, $from, $rt = false, $rf = false) {

	foreach ($from as $k => $fv) {
		$tv = $to[$k];
		switch ($k) {
			case 'Letrehozva':
			case 'Modositva':
				$fminmax = explode(' ... ', $fv);
				if (count($fminmax) == 1) $fminmax[1] = $fminmax[0];

				$tminmax = explode(' ... ', $tv);
				if (count($tminmax) == 1) $tminmax[1] = $tminmax[0];
				
				if ($fminmax[0] < $tminmax[0]) $tminmax[0] = $fminmax[0];
				if ($fminmax[1] > $tminmax[1]) $tminmax[1] = $fminmax[1];
				
				if ($tminmax[0] == $tminmax[1]) {
					$value = $tminmax[0];
				} else {
					$value = $tminmax[0] . ' ... ' . $tminmax[1];
				}
				break;
				
			default:
				if ($fv == '' && $tv == '') break;
				if ($fv == '') $fv = 'N/A';
				if ($tv == '') $tv = 'N/A';
				$farr = explode(', ', $fv);
				$tarr = explode(', ', $tv);
				if ($rf) $farr = array_reverse($farr);
				if ($tf) $tarr = array_reverse($tarr);
				$value = implode(', ', array_unique(array_merge($tarr, $farr)));
				break;
			
		}
		$to[$k] = $value;
	}
	
	return $to;
}

// út irányszöge az adott végpontban
function getWayAngle($way, $node) {

	$nodes = $way['nd'];
	$count = count($nodes);
	if ($nodes[0] == $node) {
		$idx0 = 0;
		$idx1 = 1;
	} else if ($nodes[$count-1] == $node) {
		$idx0 = $count-1;
		$idx1 = $count-2;
	} else {
		return false;
	}

	$latlon0 = nodeFromRef($nodes[$idx0]);
	$latlon1 = nodeFromRef($nodes[$idx1]);

	list($lat0, $lon0) = explode(',', $latlon0);
	list($lat1, $lon1) = explode(',', $latlon1);
		
	return azimuth($lat0, $lon0, $lat1, $lon1);

}

function azimuth ($lat1, $lon1, $lat2, $lon2) {

	$φ1 = $lat1 * pi() / 180;
	$φ2 = $lat2 * pi() / 180;
	$λ1 = $lon1 * pi() / 180;
	$λ2 = $lon2 * pi() / 180;

	$y = sin($λ2-$λ1) * cos($φ2);
	$x = cos($φ1)*sin($φ2) -
			 sin($φ1)*cos($φ2)*cos($λ2-$λ1);

	return atan2($y, $x) * 180 / pi();

}
